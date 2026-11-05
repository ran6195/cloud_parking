<?php

use App\Engine\Models\BlockedDate;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Concerns\ValidatesOpenApi;
use Tests\Engine\Support\EngineApi;

uses(RefreshEngineDatabase::class, ValidatesOpenApi::class);

beforeEach(function () {
    EngineApi::freezeClock();
    $this->headers = EngineApi::client()['headers'];
});

it('dice se il periodo è disponibile', function () {
    $response = $this->postJson('/api/v1/availability', [
        'start' => '2026-12-20T08:00:00+01:00',
        'end' => '2026-12-27T20:00:00+01:00',
        'extras' => ['covered_parking'],
    ], $this->headers);

    $this->assertMatchesOpenApi($response, '/availability', 'post');
    $response->assertOk()->assertExactJson([
        'available' => true,
        'parking' => ['available' => true, 'reason' => null, 'unavailable_dates' => []],
        'extras' => ['covered_parking' => ['available' => true, 'reason' => null, 'unavailable_dates' => []]],
    ]);
});

it('restituisce un oggetto vuoto per i servizi se non ne sono richiesti', function () {
    $response = $this->postJson('/api/v1/availability', [
        'start' => '2026-12-20T08:00:00+01:00',
        'end' => '2026-12-21T08:00:00+01:00',
    ], $this->headers);

    $this->assertMatchesOpenApi($response, '/availability', 'post');
    expect($response->getContent())->toContain('"extras":{}');
});

it('segnala date bloccate e posti coperti esauriti', function () {
    BlockedDate::factory()->create(['date' => '2026-12-27']);
    app(EngineSettings::class)->set('extras.covered_parking', ['capacity' => 1]);
    Reservation::factory()->between('2026-12-24 08:00', '2026-12-26 08:00')->withExtra('covered_parking')->create();

    $response = $this->postJson('/api/v1/availability', [
        'start' => '2026-12-20T08:00:00+01:00',
        'end' => '2026-12-27T20:00:00+01:00',
        'extras' => ['covered_parking', 'exterior_wash'],
    ], $this->headers);

    $this->assertMatchesOpenApi($response, '/availability', 'post');
    $response->assertOk()->assertExactJson([
        'available' => false,
        'parking' => ['available' => false, 'reason' => 'blocked_date', 'unavailable_dates' => ['2026-12-27']],
        'extras' => ['covered_parking' => ['available' => false, 'reason' => 'capacity_full', 'unavailable_dates' => ['2026-12-24', '2026-12-25']]],
    ]);
});

it('valida date, ordine, passato e servizi', function (array $body, string $field, string $message) {
    $response = $this->postJson('/api/v1/availability', $body + [
        'start' => '2026-12-20T08:00:00+01:00',
        'end' => '2026-12-21T08:00:00+01:00',
    ], $this->headers);

    $this->assertMatchesOpenApi($response, '/availability', 'post');
    $response->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'validation_failed');
    expect($response->json('errors')[$field] ?? null)->toBe([$message]);
})->with([
    'senza offset' => [['start' => '2026-12-20T08:00:00'], 'start', 'Data e ora devono essere in formato ISO 8601 con offset, ad es. 2026-12-20T08:00:00+01:00.'],
    'data inesistente' => [['start' => '2026-02-30T08:00:00+01:00'], 'start', 'Data e ora devono essere in formato ISO 8601 con offset, ad es. 2026-12-20T08:00:00+01:00.'],
    'rientro prima della partenza' => [['end' => '2026-12-19T08:00:00+01:00'], 'end', 'Il rientro deve essere successivo alla partenza.'],
    'oltre un anno' => [['end' => '2027-12-21T08:00:00+01:00'], 'end', 'La sosta non può superare 365 giorni.'],
    'nel passato' => [['start' => '2026-11-05T11:40:00+01:00'], 'start', 'La consegna non può essere nel passato.'],
    'servizio sconosciuto' => [['extras' => ['jacuzzi']], 'extras.0', 'Servizio sconosciuto.'],
    'servizio ripetuto' => [['extras' => ['key_drop', 'key_drop']], 'extras.0', 'Il servizio è ripetuto.'],
]);

it('accetta una consegna appena passata, entro la tolleranza di 15 minuti', function () {
    $this->postJson('/api/v1/availability', [
        'start' => '2026-11-05T11:50:00+01:00',
        'end' => '2026-11-06T11:50:00+01:00',
    ], $this->headers)->assertOk();
});
