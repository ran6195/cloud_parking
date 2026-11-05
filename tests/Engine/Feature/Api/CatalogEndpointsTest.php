<?php

use App\Engine\Models\PriceList;
use App\Engine\Settings\EngineSettings;
use Database\Engine\Factories\PriceListFactory;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Concerns\ValidatesOpenApi;
use Tests\Engine\Support\EngineApi;

uses(RefreshEngineDatabase::class, ValidatesOpenApi::class);

beforeEach(function () {
    EngineApi::freezeClock();
    $this->headers = EngineApi::client()['headers'];
});

it('elenca i servizi attivi con le impostazioni predefinite del legacy', function () {
    $response = $this->getJson('/api/v1/extras', $this->headers);

    $this->assertMatchesOpenApi($response, '/extras', 'get');
    $response->assertOk()->assertExactJson(['data' => [
        [
            'id' => 'covered_parking', 'name' => 'Posto coperto', 'description' => null,
            'pricing' => 'percentage_of_parking', 'price' => null, 'percentage' => 40,
            'capacity_limited' => true, 'incompatible_with' => ['key_drop'], 'message' => null,
        ],
        [
            'id' => 'key_drop', 'name' => 'Tieni le chiavi', 'description' => null,
            'pricing' => 'fixed', 'price' => ['amount_cents' => 700, 'currency' => 'EUR'], 'percentage' => null,
            'capacity_limited' => true, 'incompatible_with' => ['covered_parking'], 'message' => null,
        ],
    ]]);
});

it('segue le modifiche del gestore e nasconde i servizi a capacità 0', function () {
    $settings = app(EngineSettings::class);
    $this->getJson('/api/v1/extras', $this->headers)->assertJsonCount(2, 'data');

    $settings->set('extras.exterior_wash', ['active' => true, 'price_cents' => 1200]);
    $settings->set('extras.key_drop', ['capacity' => 0]);

    $this->getJson('/api/v1/extras', $this->headers)
        ->assertJsonPath('data.*.id', ['covered_parking', 'exterior_wash'])
        ->assertJsonPath('data.1.price.amount_cents', 1200)
        ->assertJsonPath('data.1.name', 'Lavaggio esterno');
});

it('riassume il listino valido alla data indicata', function () {
    EngineApi::novemberPriceList();

    $response = $this->getJson('/api/v1/tariffs/summary?date=2026-11-20', $this->headers);

    $this->assertMatchesOpenApi($response, '/tariffs/summary', 'get');
    $response->assertOk()->assertExactJson([
        'price_list' => ['valid_from' => '2026-11-01', 'valid_to' => '2026-11-30'],
        'prices' => [
            ['days' => 1, 'price' => ['amount_cents' => 1700, 'currency' => 'EUR']],
            ['days' => 3, 'price' => ['amount_cents' => 2700, 'currency' => 'EUR']],
            ['days' => 7, 'price' => ['amount_cents' => 4700, 'currency' => 'EUR']],
            ['days' => 14, 'price' => ['amount_cents' => 8550, 'currency' => 'EUR']],
        ],
        'price_per_day_after_30' => ['amount_cents' => 1000, 'currency' => 'EUR'],
        'covered_parking_percentage' => 40,
    ]);
});

it('usa oggi come data predefinita e il fallback sugli anni precedenti', function () {
    EngineApi::novemberPriceList();
    PriceList::factory()->period('2026-01-01', '2026-01-31')->withPrices(PriceListFactory::linearCents())->create();

    $this->getJson('/api/v1/tariffs/summary', $this->headers)->assertJsonPath('price_list.valid_from', '2026-11-01');
    $this->getJson('/api/v1/tariffs/summary?date=2027-01-10', $this->headers)
        ->assertOk()
        ->assertJsonPath('price_list.valid_from', '2026-01-01')
        ->assertJsonPath('prices.0.price.amount_cents', 1500);
});

it('risponde 404 senza listino e 422 con una data non valida', function () {
    $missing = $this->getJson('/api/v1/tariffs/summary?date=2031-12-20', $this->headers);

    $this->assertMatchesOpenApi($missing, '/tariffs/summary', 'get');
    $missing->assertNotFound()
        ->assertJsonPath('code', 'price_list_not_found')
        ->assertJsonPath('detail', 'Non esiste un listino per il 20/12/2031.');

    $this->getJson('/api/v1/tariffs/summary?date=20-12-2031', $this->headers)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('errors.date.0', 'La data deve essere nel formato AAAA-MM-GG.');
});
