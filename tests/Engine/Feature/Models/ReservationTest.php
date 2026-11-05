<?php

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\QuoteLine;
use App\Engine\Domain\Pricing\QuoteLineType;
use App\Engine\Domain\Shared\Money;
use App\Engine\Models\Customer;
use App\Engine\Models\PaymentTransaction;
use App\Engine\Models\Quote;
use App\Engine\Models\Reservation;
use App\Engine\Models\ReservationLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshEngineDatabase;

uses(RefreshEngineDatabase::class);

it('ricava le date locali di Roma dagli istanti di inizio e fine', function () {
    // 23:30 UTC del 3 novembre = 00:30 del 4 novembre a Roma
    $reservation = Reservation::factory()->create([
        'starts_at' => CarbonImmutable::parse('2026-11-03T23:30:00Z'),
        'ends_at' => CarbonImmutable::parse('2026-11-06T22:59:00Z'),
    ])->fresh();

    expect($reservation?->start_date->format('Y-m-d'))->toBe('2026-11-04')
        ->and($reservation?->end_date->format('Y-m-d'))->toBe('2026-11-06')
        ->and($reservation?->starts_at->equalTo(CarbonImmutable::parse('2026-11-04 00:30', 'Europe/Rome')))->toBeTrue();
});

it('conserva l\'istante esatto a cavallo del cambio dell\'ora legale', function () {
    $reservation = Reservation::factory()->between('2026-10-24 08:00', '2026-10-26 08:00')->create()->fresh();

    expect($reservation?->starts_at->toIso8601String())->toBe('2026-10-24T06:00:00+00:00')
        ->and($reservation?->ends_at->toIso8601String())->toBe('2026-10-26T07:00:00+00:00');
});

it('assegna numero progressivo, token di cancellazione e targa normalizzata', function () {
    $first = Reservation::factory()->create(['plate' => 'ab 123-cd'])->fresh();
    $second = Reservation::factory()->create()->fresh();

    expect($first?->number)->toBeInt()
        ->and($second?->number)->toBe($first->number + 1)
        ->and($first->cancellation_token)->toMatch('/^[0-9a-f]{64}$/')
        ->and($first->plate)->toBe('AB123CD')
        ->and($first->id)->toBeUuid();
});

it('prosegue la numerazione da un numero legacy importato', function () {
    Reservation::factory()->create()->forceFill(['number' => 22170])->save();
    DB::connection('engine')->statement(
        "select setval(pg_get_serial_sequence('reservations', 'number'), (select max(number) from reservations))"
    );

    expect(Reservation::factory()->create()->fresh()?->number)->toBe(22171);
});

it('calcola lo sconto come differenza tra subtotale e totale', function () {
    $reservation = Reservation::factory()->create(['subtotal_cents' => 3880, 'total_cents' => 3492])->fresh();

    expect($reservation?->discount_cents)->toBe(388);

    expect(fn () => DB::connection('engine')->transaction(
        fn () => Reservation::factory()->create(['subtotal_cents' => 1000, 'total_cents' => 1200])
    ))->toThrow(QueryException::class, 'reservations_totals_check');
});

it('collega cliente, preventivo, righe e pagamenti', function () {
    $quote = Quote::factory()->create();
    $reservation = Reservation::factory()->status(ReservationStatus::Paid)->create(['quote_id' => $quote->id]);

    $reservation->lines()->create(ReservationLine::attributesFrom(
        new QuoteLine(QuoteLineType::Extra, 'exterior_wash', 'Lavaggio esterno', 1, Money::cents(1500), Money::cents(1500)),
        position: 2,
    ));
    $reservation->lines()->create(ReservationLine::factory()->make(['reservation_id' => null])->only([
        'position', 'type', 'code', 'description', 'quantity', 'unit_cents', 'total_cents',
    ]));
    PaymentTransaction::factory()->create(['reservation_id' => $reservation->id]);

    $reservation = $reservation->fresh(['customer', 'quote', 'lines', 'paymentTransactions']);

    expect($reservation?->customer)->toBeInstanceOf(Customer::class)
        ->and($reservation?->quote?->is($quote))->toBeTrue()
        ->and($quote->fresh()?->reservation?->is($reservation))->toBeTrue()
        ->and($reservation?->lines->pluck('code')->all())->toBe(['parking', 'exterior_wash'])
        ->and($reservation?->lines->last()?->type)->toBe(QuoteLineType::Extra)
        ->and($reservation?->paymentTransactions)->toHaveCount(1)
        ->and($reservation?->status)->toBe(ReservationStatus::Paid);
});

it('identifica il cliente per email normalizzata', function () {
    Customer::factory()->create(['email' => ' Mario.Rossi@Example.com ']);

    expect(Customer::query()->value('email_normalized'))->toBe('mario.rossi@example.com');

    expect(fn () => DB::connection('engine')->transaction(
        fn () => Customer::factory()->create(['email' => 'MARIO.ROSSI@example.com'])
    ))->toThrow(QueryException::class, 'customers_email_active_unique');
});

it('fa scadere il preventivo all\'ora indicata', function () {
    $quote = Quote::factory()->create(['expires_at' => CarbonImmutable::parse('2026-11-05 12:00', 'Europe/Rome')]);

    expect($quote->isExpired(CarbonImmutable::parse('2026-11-05 11:59', 'Europe/Rome')))->toBeFalse()
        ->and($quote->isExpired(CarbonImmutable::parse('2026-11-05 12:00', 'Europe/Rome')))->toBeTrue();
});
