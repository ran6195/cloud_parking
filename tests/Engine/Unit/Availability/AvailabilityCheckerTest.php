<?php

use App\Engine\Domain\Availability\AvailabilityChecker;
use App\Engine\Domain\Availability\AvailabilityReason;
use App\Engine\Domain\Availability\CapacityRules;
use App\Engine\Domain\Availability\DailyOccupancy;
use App\Engine\Domain\Availability\InMemoryAvailabilityData;
use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\StayPeriod;
use Tests\Engine\Support\Pricing;

/**
 * @param  list<string>  $blocked
 * @param  array<string, int>  $parking
 * @param  array<string, array<string, int>>  $extras
 */
function checker(array $blocked = [], array $parking = [], array $extras = [], bool $autoBlock = true, int $covered = 3, int $keys = 3): AvailabilityChecker
{
    return new AvailabilityChecker(
        new InMemoryAvailabilityData($blocked, new DailyOccupancy($parking, $extras)),
        new CapacityRules(totalSpots: 10, autoBlock: $autoBlock, extraCapacities: ['covered_parking' => $covered, 'key_drop' => $keys]),
        Pricing::catalog(),
    );
}

function stay(string $start = '2026-11-20 08:00', string $end = '2026-11-23 18:00'): StayPeriod
{
    return StayPeriod::fromStrings($start, $end);
}

it('è disponibile senza blocchi né posti occupati', function () {
    $availability = checker()->check(stay(), [ExtraId::CoveredParking]);

    expect($availability->isAvailable())->toBeTrue()
        ->and($availability->parking->reason)->toBeNull()
        ->and(array_keys($availability->extras))->toBe(['covered_parking']);
});

it('blocca se è bloccato un giorno del periodo, compreso quello di ritiro', function (string $blocked, bool $available) {
    $availability = checker(blocked: [$blocked])->check(stay());

    expect($availability->isAvailable())->toBe($available);
    if (! $available) {
        expect($availability->parking->reason)->toBe(AvailabilityReason::BlockedDate)
            ->and($availability->parking->unavailableDates)->toBe([$blocked]);
    }
})->with([
    'giorno prima' => ['2026-11-19', true],
    'consegna' => ['2026-11-20', false],
    'in mezzo' => ['2026-11-21', false],
    'ritiro' => ['2026-11-23', false],
    'giorno dopo' => ['2026-11-24', true],
]);

it('con il blocco automatico rifiuta i giorni pieni, compreso quello di ritiro', function () {
    $availability = checker(parking: ['2026-11-21' => 9, '2026-11-23' => 10])->check(stay());

    expect($availability->isAvailable())->toBeFalse()
        ->and($availability->parking->reason)->toBe(AvailabilityReason::CapacityFull)
        ->and($availability->parking->unavailableDates)->toBe(['2026-11-23']);
});

it('senza blocco automatico ignora i posti totali ma non le date bloccate', function () {
    $full = ['2026-11-21' => 50];

    expect(checker(parking: $full, autoBlock: false)->check(stay())->isAvailable())->toBeTrue()
        ->and(checker(blocked: ['2026-11-21'], parking: $full, autoBlock: false)->check(stay())->isAvailable())->toBeFalse();
});

it('per i servizi a capacità ignora il giorno di ritiro', function (ExtraId $extra) {
    $onReturnDay = checker(extras: [$extra->value => ['2026-11-23' => 3]])->check(stay(), [$extra]);
    $inside = checker(extras: [$extra->value => ['2026-11-22' => 3]])->check(stay(), [$extra]);

    expect($onReturnDay->isAvailable())->toBeTrue()
        ->and($inside->isAvailable())->toBeFalse()
        ->and($inside->extras[$extra->value]->reason)->toBe(AvailabilityReason::CapacityFull)
        ->and($inside->extras[$extra->value]->unavailableDates)->toBe(['2026-11-22'])
        ->and($inside->parking->available)->toBeTrue();
})->with([ExtraId::CoveredParking, ExtraId::KeyDrop]);

it('per una sosta nello stesso giorno i servizi a capacità sono sempre liberi, come nel legacy', function () {
    $availability = checker(extras: ['covered_parking' => ['2026-11-20' => 3]])
        ->check(stay('2026-11-20 06:00', '2026-11-20 22:00'), [ExtraId::CoveredParking]);

    expect($availability->isAvailable())->toBeTrue();
});

it('segnala come disattivato un servizio con capacità 0 o spento dal gestore', function () {
    $zero = checker(covered: 0)->check(stay(), [ExtraId::CoveredParking]);

    expect($zero->isAvailable())->toBeFalse()
        ->and($zero->extras['covered_parking']->reason)->toBe(AvailabilityReason::ServiceDisabled)
        ->and($zero->extras['covered_parking']->unavailableDates)->toBe([]);
});

it('riporta solo i servizi a capacità limitata richiesti, senza duplicati', function () {
    $availability = checker()->check(stay(), [ExtraId::KeyDrop, ExtraId::ExteriorWash, ExtraId::CoveredParking, ExtraId::KeyDrop]);

    expect(array_keys($availability->extras))->toBe(['covered_parking', 'key_drop']);
});

it('le prenotazioni in attesa di pagamento occupano il posto solo fino al termine', function () {
    $now = new DateTimeImmutable('2026-11-05 12:00');

    expect(ReservationStatus::PendingPayment->holdsCapacity(new DateTimeImmutable('2026-11-05 12:01'), $now))->toBeTrue()
        ->and(ReservationStatus::PendingPayment->holdsCapacity(new DateTimeImmutable('2026-11-05 12:00'), $now))->toBeFalse()
        ->and(ReservationStatus::PendingPayment->holdsCapacity(null, $now))->toBeFalse()
        ->and(ReservationStatus::Confirmed->holdsCapacity(null, $now))->toBeTrue()
        ->and(ReservationStatus::Cancelled->holdsCapacity(null, $now))->toBeFalse()
        ->and(ReservationStatus::Expired->holdsCapacity(null, $now))->toBeFalse();
});
