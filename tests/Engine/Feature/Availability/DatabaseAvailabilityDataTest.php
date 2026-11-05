<?php

use App\Engine\Domain\Availability\AvailabilityChecker;
use App\Engine\Domain\Availability\AvailabilityReason;
use App\Engine\Domain\Availability\CapacityRules;
use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\StayPeriod;
use App\Engine\Domain\Shared\FrozenClock;
use App\Engine\Infrastructure\Availability\DatabaseAvailabilityData;
use App\Engine\Models\BlockedDate;
use App\Engine\Models\Reservation;
use Carbon\CarbonImmutable;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Engine\Support\Pricing;

uses(RefreshEngineDatabase::class);

const NOW = '2026-11-05 12:00:00 Europe/Rome';

function availabilityData(): DatabaseAvailabilityData
{
    return new DatabaseAvailabilityData(FrozenClock::at(NOW));
}

function day(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date, new DateTimeZone('Europe/Rome'));
}

it('conta i posti occupati dal giorno di consegna al giorno prima del ritiro', function () {
    Reservation::factory()->between('2026-11-20 08:00', '2026-11-22 18:00')->create();
    Reservation::factory()->between('2026-11-21 23:30', '2026-11-23 06:00')->withExtra('covered_parking')->create();

    $occupancy = availabilityData()->occupancy(day('2026-11-19'), day('2026-11-23'), [ExtraId::CoveredParking]);

    expect($occupancy->parking)->toBe([
        '2026-11-19' => 0, '2026-11-20' => 1, '2026-11-21' => 2, '2026-11-22' => 1, '2026-11-23' => 0,
    ])->and($occupancy->extras['covered_parking'])->toBe([
        '2026-11-19' => 0, '2026-11-20' => 0, '2026-11-21' => 1, '2026-11-22' => 1, '2026-11-23' => 0,
    ]);
});

it('ignora prenotazioni annullate, scadute, cancellate e quelle con il termine di pagamento passato', function () {
    $period = ['2026-11-20 08:00', '2026-11-22 08:00'];
    $now = CarbonImmutable::parse(NOW);

    Reservation::factory()->between(...$period)->status(ReservationStatus::Cancelled)->create();
    Reservation::factory()->between(...$period)->status(ReservationStatus::Expired)->create();
    Reservation::factory()->between(...$period)->create()->delete();
    Reservation::factory()->between(...$period)->status(ReservationStatus::PendingPayment, $now->subMinute())->create();
    Reservation::factory()->between(...$period)->status(ReservationStatus::PendingPayment, $now->addMinutes(20))->create();
    Reservation::factory()->between(...$period)->status(ReservationStatus::Paid)->create();
    Reservation::factory()->between(...$period)->status(ReservationStatus::Completed)->create();

    expect(availabilityData()->occupancy(day('2026-11-20'), day('2026-11-20'), [])->parking)->toBe(['2026-11-20' => 3]);
});

it('restituisce le date bloccate dell\'intervallo, estremi inclusi', function () {
    foreach (['2026-11-19', '2026-11-23', '2026-11-20', '2026-11-24'] as $date) {
        BlockedDate::factory()->create(['date' => $date]);
    }

    expect(availabilityData()->blockedDates(day('2026-11-20'), day('2026-11-23')))->toBe(['2026-11-20', '2026-11-23']);
});

it('controlla parcheggio e servizi con i dati del database', function () {
    $rules = new CapacityRules(totalSpots: 2, autoBlock: true, extraCapacities: ['covered_parking' => 1, 'key_drop' => 1]);
    $checker = new AvailabilityChecker(availabilityData(), $rules, Pricing::catalog());

    Reservation::factory()->between('2026-11-20 08:00', '2026-11-25 08:00')->withExtra('covered_parking')->create();
    Reservation::factory()->between('2026-11-22 08:00', '2026-11-23 08:00')->create();

    $result = $checker->check(StayPeriod::fromStrings('2026-11-21 08:00', '2026-11-22 20:00'), [ExtraId::CoveredParking, ExtraId::KeyDrop]);

    expect($result->isAvailable())->toBeFalse()
        ->and($result->parking->reason)->toBe(AvailabilityReason::CapacityFull)
        ->and($result->parking->unavailableDates)->toBe(['2026-11-22'])
        ->and($result->extras['covered_parking']->unavailableDates)->toBe(['2026-11-21'])
        ->and($result->extras['key_drop']->available)->toBeTrue();
});

it('dà gli stessi conteggi dell\'algoritmo giorno per giorno del legacy', function (int $seed) {
    mt_srand($seed);
    $now = CarbonImmutable::parse(NOW);
    $base = CarbonImmutable::parse('2026-11-01 00:00', 'Europe/Rome');

    foreach (range(1, 80) as $i) {
        $start = $base->addMinutes(mt_rand(0, 40 * 1440));
        $status = ReservationStatus::cases()[mt_rand(0, count(ReservationStatus::cases()) - 1)];
        $factory = Reservation::factory()
            ->state(['starts_at' => $start, 'ends_at' => $start->addMinutes(mt_rand(60, 12 * 1440))])
            ->status($status, $status === ReservationStatus::PendingPayment ? $now->addMinutes(mt_rand(-60, 60)) : null);

        $extra = ['covered_parking', 'key_drop', null][mt_rand(0, 2)];
        $reservation = ($extra === null ? $factory : $factory->withExtra($extra))->create();

        if (mt_rand(0, 9) === 0) {
            $reservation->delete();
        }
    }

    $reservations = Reservation::query()->with('lines')->get();
    $extras = [ExtraId::CoveredParking, ExtraId::KeyDrop];

    foreach (range(1, 10) as $_) {
        $from = $base->addDays(mt_rand(0, 45));
        $to = $from->addDays(mt_rand(0, 15));

        $occupancy = availabilityData()->occupancy($from->toDateTimeImmutable(), $to->toDateTimeImmutable(), $extras);

        for ($current = $from; $current->lte($to); $current = $current->addDay()) {
            $date = $current->format('Y-m-d');
            $present = $reservations->filter(fn (Reservation $r) => $r->status->holdsCapacity($r->payment_due_by?->toDateTimeImmutable(), $now->toDateTimeImmutable())
                && $r->start_date->format('Y-m-d') <= $date
                && $r->end_date->format('Y-m-d') > $date);

            expect($occupancy->parkingOn($date))->toBe($present->count(), "parcheggio il {$date}");
            foreach ($extras as $extra) {
                $withExtra = $present->filter(fn (Reservation $r) => $r->lines->contains('code', $extra->value));
                expect($occupancy->extraOn($extra, $date))->toBe($withExtra->count(), "{$extra->value} il {$date}");
            }
        }
    }
})->with([1, 2, 3]);
