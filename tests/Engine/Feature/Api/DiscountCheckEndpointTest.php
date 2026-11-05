<?php

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Models\Discount;
use App\Engine\Models\Reservation;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Concerns\ValidatesOpenApi;
use Tests\Engine\Support\EngineApi;

uses(RefreshEngineDatabase::class, ValidatesOpenApi::class);

beforeEach(function () {
    EngineApi::freezeClock();
    $this->headers = EngineApi::client()['headers'];
});

it('conferma un codice valido, senza distinguere le maiuscole', function () {
    Discount::factory()->create(['code' => 'ESTATE10', 'percentage' => 10, 'valid_until' => '2026-12-31']);

    $response = $this->postJson('/api/v1/discounts/check', ['code' => ' estate10 '], $this->headers);

    $this->assertMatchesOpenApi($response, '/discounts/check', 'post');
    $response->assertOk()->assertExactJson([
        'code' => 'ESTATE10', 'valid' => true, 'reason' => null, 'type' => 'percentage',
        'percentage' => 10, 'amount' => null, 'valid_until' => '2026-12-31',
    ]);
});

it('mostra l\'importo di uno sconto fisso', function () {
    Discount::factory()->fixed(500)->create(['code' => 'CINQUE']);

    $this->postJson('/api/v1/discounts/check', ['code' => 'cinque'], $this->headers)
        ->assertJsonPath('type', 'fixed_amount')
        ->assertJsonPath('amount', ['amount_cents' => 500, 'currency' => 'EUR']);
});

it('spiega perché un codice non vale', function (Closure $setup, string $code, string $reason) {
    $setup();

    $response = $this->postJson('/api/v1/discounts/check', ['code' => $code], $this->headers);

    $this->assertMatchesOpenApi($response, '/discounts/check', 'post');
    $response->assertOk()->assertExactJson([
        'code' => $code, 'valid' => false, 'reason' => $reason, 'type' => null,
        'percentage' => null, 'amount' => null, 'valid_until' => null,
    ]);
})->with([
    'inesistente' => [fn () => null, 'BOH', 'not_found'],
    'scaduto' => [fn () => Discount::factory()->create(['code' => 'NATALE', 'valid_until' => '2026-11-04']), 'NATALE', 'expired'],
    'esaurito' => [function () {
        $discount = Discount::factory()->create(['code' => 'UNO', 'max_uses' => 1]);
        Reservation::factory()->status(ReservationStatus::Paid)->create(['discount_id' => $discount->id]);
    }, 'UNO', 'exhausted'],
]);

it('rifiuta un codice vuoto o troppo lungo', function (string $code) {
    $response = $this->postJson('/api/v1/discounts/check', ['code' => $code], $this->headers);

    $this->assertMatchesOpenApi($response, '/discounts/check', 'post');
    $response->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
})->with(['   ', str_repeat('A', 51)]);
