<?php

use App\Engine\Models\ApiClient;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Concerns\ValidatesOpenApi;
use Tests\Engine\Support\EngineApi;

uses(RefreshEngineDatabase::class, ValidatesOpenApi::class);

beforeEach(fn () => EngineApi::freezeClock());

it('rifiuta le richieste senza chiave o con una chiave sbagliata', function (?string $key) {
    $headers = $key === null ? [] : ['Authorization' => "Bearer {$key}"];

    $response = $this->getJson('/api/v1/extras', $headers);

    $this->assertMatchesOpenApi($response, '/extras', 'get');
    $response->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'unauthenticated')
        ->assertJsonPath('type', 'https://docs.parcheggio.example/errors/unauthenticated');
})->with([
    'nessuna chiave' => null,
    'chiave inventata' => 'sk_inventata',
    'formato sconosciuto' => 'abc',
]);

it('accetta la chiave segreta e rifiuta quella di un client revocato', function () {
    ['client' => $client, 'headers' => $headers] = EngineApi::client();

    $this->getJson('/api/v1/extras', $headers)->assertOk();
    expect($client->fresh()?->last_used_at)->not->toBeNull();

    $client->update(['revoked_at' => now()]);

    $this->getJson('/api/v1/extras', $headers)->assertUnauthorized();
});

it('accetta la chiave pubblicabile solo dalle origini registrate', function () {
    ['client' => $client] = EngineApi::client();
    $key = ['Authorization' => 'Bearer '.$client->publishable_key];

    $this->getJson('/api/v1/extras', $key)->assertUnauthorized();
    $this->getJson('/api/v1/extras', $key + ['Origin' => 'https://altro.example'])->assertUnauthorized();

    $this->getJson('/api/v1/extras', $key + ['Origin' => EngineApi::ORIGIN])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', EngineApi::ORIGIN)
        ->assertHeader('Vary', 'Origin');
});

it('risponde al preflight CORS solo per le origini registrate', function () {
    EngineApi::client();
    $preflight = ['Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'authorization,content-type'];

    $allowed = $this->withHeaders($preflight + ['Origin' => EngineApi::ORIGIN.'/'])->options('/api/v1/quotes');
    $refused = $this->withHeaders($preflight + ['Origin' => 'https://altro.example'])->options('/api/v1/quotes');

    $allowed->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', EngineApi::ORIGIN.'/')
        ->assertHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, Accept, Idempotency-Key');
    $refused->assertNoContent()->assertHeaderMissing('Access-Control-Allow-Origin');
});

it('dimentica le origini di un client revocato', function () {
    ['client' => $client] = EngineApi::client();
    expect(ApiClient::allAllowedOrigins())->toBe([EngineApi::ORIGIN]);

    $client->update(['revoked_at' => now()]);

    expect(ApiClient::allAllowedOrigins())->toBe([]);
});

it('espone i limiti di richieste e risponde 429 oltre il limite', function () {
    ['headers' => $headers] = EngineApi::client(rateLimit: 2);

    $this->getJson('/api/v1/extras', $headers)
        ->assertHeader('RateLimit-Limit', '2')
        ->assertHeader('RateLimit-Remaining', '1');
    $this->getJson('/api/v1/extras', $headers)->assertHeader('RateLimit-Remaining', '0');

    $response = $this->getJson('/api/v1/extras', $headers);

    $this->assertMatchesOpenApi($response, '/extras', 'get');
    $response->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('code', 'rate_limited');
});

it('ha un limite più stretto per la verifica dei codici sconto', function () {
    config(['engine.rate_limits.discounts' => 1]);
    ['headers' => $headers] = EngineApi::client();

    $this->postJson('/api/v1/discounts/check', ['code' => 'A'], $headers)->assertOk();
    $this->postJson('/api/v1/discounts/check', ['code' => 'A'], $headers)->assertTooManyRequests();
    $this->getJson('/api/v1/extras', $headers)->assertOk();
});

it('crea un client da riga di comando e mostra le chiavi una volta sola', function () {
    $this->artisan('engine:client', ['name' => 'Form del CMS', 'channel' => 'cms', '--origin' => ['https://Sostablq.it/']])
        ->expectsOutputToContain('Client #')
        ->expectsOutputToContain('pk_')
        ->expectsOutputToContain('sk_')
        ->assertSuccessful();

    $client = ApiClient::query()->sole();

    expect($client->channel)->toBe('cms')
        ->and($client->allowed_origins)->toBe(['https://sostablq.it'])
        ->and($client->secret_key_hash)->toHaveLength(64);

    $this->artisan('engine:client', ['name' => 'X', 'channel' => 'Canale Non Valido'])->assertFailed();
    $this->artisan('engine:client', ['name' => 'X', 'channel' => 'pwa', '--origin' => ['sostablq.it']])->assertFailed();
});

it('risponde con un problem+json 404 ai percorsi sconosciuti di /api/v1', function () {
    ['headers' => $headers] = EngineApi::client();

    $this->getJson('/api/v1/inesistente', $headers)
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'not_found');
});
