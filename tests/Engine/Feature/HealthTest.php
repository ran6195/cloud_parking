<?php

use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshEngineDatabase;
use Tests\Concerns\ValidatesOpenApi;

uses(RefreshEngineDatabase::class, ValidatesOpenApi::class);

it('risponde ok quando il database del motore è raggiungibile', function () {
    $response = $this->getJson('/api/v1/health');

    $this->assertMatchesOpenApi($response, '/health', 'get');

    $response->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'api_version' => 'v1',
            'database' => 'ok',
        ]);
});

it('lavora sullo schema dedicato del database di test', function () {
    $connection = DB::connection('engine');

    expect($connection->getDriverName())->toBe('pgsql')
        ->and($connection->getDatabaseName())->toBe('parking_test')
        ->and($connection->scalar('select current_schema()'))->toBe('engine')
        ->and((int) $connection->scalar('show server_version_num'))->toBeGreaterThanOrEqual(180000);
});

it('tiene le migrazioni del motore in una tabella propria nello schema engine', function () {
    $exists = DB::connection('engine')->scalar(
        "select exists (select 1 from information_schema.tables where table_schema = 'engine' and table_name = 'migrations')"
    );

    expect($exists)->toBeTrue();
});
