<?php

use App\Engine\Models\Discount;
use App\Engine\Models\Quote;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Concerns\ValidatesOpenApi;
use Tests\Engine\Support\EngineApi;

uses(RefreshEngineDatabase::class, ValidatesOpenApi::class);

beforeEach(function () {
    EngineApi::freezeClock();
    EngineApi::novemberPriceList();
    ['client' => $this->client, 'headers' => $this->headers] = EngineApi::client();
});

/**
 * Esempio di docs/pricing.md: 3 giorni a novembre 2026, posto coperto, pagamento in parcheggio, sconto 10%.
 *
 * @return array<string, mixed>
 */
function pricingExample(array $overrides = []): array
{
    return $overrides + [
        'start' => '2026-11-20T08:00:00+01:00',
        'end' => '2026-11-23T07:00:00+01:00',
        'extras' => ['covered_parking'],
        'payment_method' => 'on_site',
        'discount_code' => 'estate10',
    ];
}

it('calcola, salva e restituisce il preventivo dell\'esempio di docs/pricing.md', function () {
    Discount::factory()->create(['code' => 'ESTATE10', 'percentage' => 10]);

    $response = $this->postJson('/api/v1/quotes', pricingExample(), $this->headers);

    $this->assertMatchesOpenApi($response, '/quotes', 'post');
    $quote = Quote::query()->sole();

    $response->assertCreated()
        ->assertHeader('Location', '/api/v1/quotes/'.$quote->id)
        ->assertExactJson([
            'id' => $quote->id,
            'status' => 'active',
            'expires_at' => '2026-11-05T12:30:00+01:00',
            'stay' => [
                'start' => '2026-11-20T08:00:00+01:00', 'end' => '2026-11-23T07:00:00+01:00',
                'duration_minutes' => 4260, 'billable_days' => 3, 'days_after_30' => 0,
            ],
            'extras' => ['covered_parking'],
            'payment_method' => 'on_site',
            'lines' => [
                ['type' => 'parking', 'code' => 'parking', 'description' => 'Parcheggio', 'quantity' => 3, 'unit_price' => null, 'total' => ['amount_cents' => 2700, 'currency' => 'EUR']],
                ['type' => 'extra', 'code' => 'covered_parking', 'description' => 'Posto coperto (+40%)', 'quantity' => 3, 'unit_price' => null, 'total' => ['amount_cents' => 1080, 'currency' => 'EUR']],
                ['type' => 'payment_fee', 'code' => 'on_site', 'description' => 'Pagamento in parcheggio', 'quantity' => 1, 'unit_price' => ['amount_cents' => 100, 'currency' => 'EUR'], 'total' => ['amount_cents' => 100, 'currency' => 'EUR']],
            ],
            'subtotal' => ['amount_cents' => 3880, 'currency' => 'EUR'],
            'discount' => [
                'code' => 'ESTATE10', 'applied' => true, 'reason' => null, 'type' => 'percentage',
                'percentage' => 10, 'amount' => ['amount_cents' => 388, 'currency' => 'EUR'],
            ],
            'total' => ['amount_cents' => 3492, 'currency' => 'EUR'],
            'bookable' => true,
            'availability' => [
                'available' => true,
                'parking' => ['available' => true, 'reason' => null, 'unavailable_dates' => []],
                'extras' => ['covered_parking' => ['available' => true, 'reason' => null, 'unavailable_dates' => []]],
            ],
            'cancellation' => ['free_until' => '2026-11-19T08:00:00+01:00'],
        ]);

    expect($quote->api_client_id)->toBe($this->client->id)
        ->and($quote->discount_id)->toBe(Discount::query()->value('id'))
        ->and($quote->trace)->toEqual(['price_table' => 'Novembre 2026', 'requested_date' => '2026-11-20', 'matched_date' => '2026-11-20', 'fallback_years' => 0]);
});

it('calcola senza sconto se il codice non vale, senza fallire', function () {
    $response = $this->postJson('/api/v1/quotes', pricingExample(['discount_code' => 'boh', 'extras' => [], 'payment_method' => 'online']), $this->headers);

    $this->assertMatchesOpenApi($response, '/quotes', 'post');
    $response->assertCreated()
        ->assertJsonPath('discount', ['code' => 'BOH', 'applied' => false, 'reason' => 'not_found', 'type' => null, 'percentage' => null, 'amount' => null])
        ->assertJsonPath('lines.0.description', 'Parcheggio scoperto')
        ->assertJsonPath('total.amount_cents', 2700);

    $this->postJson('/api/v1/quotes', pricingExample(['discount_code' => null]), $this->headers)
        ->assertCreated()
        ->assertJsonPath('discount', null);
});

it('restituisce il prezzo anche se il periodo non è prenotabile', function () {
    app(EngineSettings::class)->set('extras.covered_parking', ['capacity' => 1]);
    Reservation::factory()->between('2026-11-21 08:00', '2026-11-22 08:00')->withExtra('covered_parking')->create();

    $response = $this->postJson('/api/v1/quotes', pricingExample(['discount_code' => null]), $this->headers);

    $this->assertMatchesOpenApi($response, '/quotes', 'post');
    $response->assertCreated()
        ->assertJsonPath('bookable', false)
        ->assertJsonPath('availability.extras.covered_parking.reason', 'capacity_full')
        ->assertJsonPath('total.amount_cents', 3880);
});

it('rifiuta servizi incompatibili, disattivati e periodi senza listino', function (array $body, string $code, ?string $errorField) {
    $response = $this->postJson('/api/v1/quotes', pricingExample($body), $this->headers);

    $this->assertMatchesOpenApi($response, '/quotes', 'post');
    $response->assertUnprocessable()->assertJsonPath('code', $code);
    if ($errorField !== null) {
        $response->assertJsonPath("errors.{$errorField}.0", 'Posto coperto e Tieni le chiavi non possono essere scelti insieme.');
    }
    expect(Quote::query()->count())->toBe(0);
})->with([
    'coperto e chiavi' => [['extras' => ['covered_parking', 'key_drop']], 'extras_incompatible', 'extras'],
    'lavaggio spento' => [['extras' => ['exterior_wash']], 'extra_unavailable', null],
    'nessun listino' => [['start' => '2031-12-20T08:00:00+01:00', 'end' => '2031-12-21T08:00:00+01:00'], 'price_list_not_found', null],
    'metodo di pagamento' => [['payment_method' => 'bitcoin'], 'validation_failed', null],
]);

it('rilegge il preventivo con lo stato aggiornato', function () {
    $id = $this->postJson('/api/v1/quotes', pricingExample(), $this->headers)->json('id');

    $active = $this->getJson("/api/v1/quotes/{$id}", $this->headers);
    $this->assertMatchesOpenApi($active, '/quotes/{quoteId}', 'get');
    $active->assertOk()->assertJsonPath('status', 'active')->assertJsonPath('total.amount_cents', 3880);

    EngineApi::freezeClock('2026-11-05 12:30:00 Europe/Rome');
    $this->getJson("/api/v1/quotes/{$id}", $this->headers)->assertJsonPath('status', 'expired');

    Quote::query()->whereKey($id)->update(['used_at' => now()]);
    $this->getJson("/api/v1/quotes/{$id}", $this->headers)->assertJsonPath('status', 'used');
});

it('non mostra i preventivi di un altro client né id non validi', function () {
    $id = $this->postJson('/api/v1/quotes', pricingExample(), $this->headers)->json('id');
    $other = EngineApi::client('pwa')['headers'];

    $response = $this->getJson("/api/v1/quotes/{$id}", $other);

    $this->assertMatchesOpenApi($response, '/quotes/{quoteId}', 'get');
    $response->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->getJson('/api/v1/quotes/non-un-uuid', $this->headers)->assertNotFound()->assertJsonPath('code', 'not_found');
});

it('applica le impostazioni del gestore a prezzi e cancellazione', function () {
    $settings = app(EngineSettings::class);
    $settings->set('payments.on_site_fee_cents', 200);
    $settings->set('extras.covered_parking', ['percentage' => 50]);
    $settings->set('cancellation.free_hours_before_start', 48);

    $this->postJson('/api/v1/quotes', pricingExample(['discount_code' => null]), $this->headers)
        ->assertJsonPath('lines.1.total.amount_cents', 1350)
        ->assertJsonPath('lines.2.total.amount_cents', 200)
        ->assertJsonPath('cancellation.free_until', '2026-11-18T08:00:00+01:00');
});
