<?php

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\Discount\DiscountCode;
use App\Engine\Domain\Pricing\Discount\DiscountRejection;
use App\Engine\Domain\Pricing\Discount\DiscountType;
use App\Engine\Infrastructure\Pricing\DatabaseDiscountFinder;
use App\Engine\Models\Discount;
use App\Engine\Models\Reservation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshEngineDatabase;

uses(RefreshEngineDatabase::class);

it('salva il codice normalizzato e lo ritrova senza distinguere le maiuscole', function () {
    Discount::factory()->create(['code' => '  estate10 ', 'percentage' => 10]);

    $found = (new DatabaseDiscountFinder)->find(new DiscountCode('Estate10'));

    expect(Discount::query()->value('code'))->toBe('ESTATE10')
        ->and($found?->type)->toBe(DiscountType::Percentage)
        ->and($found?->percentage)->toBe(10)
        ->and($found?->maxUses)->toBeNull();
});

it('converte uno sconto fisso con scadenza', function () {
    Discount::factory()->fixed(500)->create(['code' => 'CINQUE', 'valid_until' => '2026-12-31']);

    $found = (new DatabaseDiscountFinder)->find(new DiscountCode('cinque'));

    expect($found?->amount?->cents)->toBe(500)
        ->and($found?->validUntil?->format('Y-m-d'))->toBe('2026-12-31')
        ->and($found?->rejectionAt(new DateTimeImmutable('2027-01-01')))->toBe(DiscountRejection::Expired);
});

it('conta come utilizzi solo le prenotazioni valide', function () {
    $discount = Discount::factory()->create(['code' => 'LIMITATO', 'max_uses' => 2]);

    foreach (ReservationStatus::cases() as $status) {
        Reservation::factory()->status($status)->create(['discount_id' => $discount->id]);
    }

    $found = (new DatabaseDiscountFinder)->find(new DiscountCode('LIMITATO'));

    expect($found?->timesUsed)->toBe(3)
        ->and($found?->rejectionAt(new DateTimeImmutable('2026-11-05')))->toBe(DiscountRejection::Exhausted);
});

it('non trova i codici cancellati e ne permette il riuso', function () {
    Discount::factory()->create(['code' => 'PARK01'])->delete();

    expect((new DatabaseDiscountFinder)->find(new DiscountCode('PARK01')))->toBeNull();

    Discount::factory()->create(['code' => 'PARK01']);

    expect(fn () => DB::connection('engine')->transaction(fn () => Discount::factory()->create(['code' => 'park01'])))
        ->toThrow(QueryException::class, 'discounts_code_active_unique');
});

it('rifiuta sconti incoerenti a livello di database', function () {
    expect(fn () => DB::connection('engine')->transaction(
        fn () => Discount::factory()->create(['type' => DiscountType::Percentage, 'percentage' => 120])
    ))->toThrow(QueryException::class, 'discounts_type_check');
});
