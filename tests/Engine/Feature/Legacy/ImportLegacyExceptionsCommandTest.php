<?php

use App\Engine\Models\PriceList;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshEngineDatabase;

uses(RefreshEngineDatabase::class);

/**
 * DB legacy finto (SQLite in memoria) con la tabella exceptions.
 *
 * @param  array<string, array<int, float|int|null>>  $months  "Y-m" → giorno → costo
 */
function fakeLegacyExceptions(array $months): void
{
    config(['database.connections.legacy_fixture' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::purge('legacy_fixture');

    Schema::connection('legacy_fixture')->create('exceptions', function (Blueprint $table) {
        $table->id();
        $table->date('data')->nullable();
        $table->float('costo')->nullable();
    });

    foreach ($months as $yearMonth => $costs) {
        foreach ($costs as $day => $cost) {
            DB::connection('legacy_fixture')->table('exceptions')->insert([
                'data' => sprintf('%s-%02d', $yearMonth, $day),
                'costo' => $cost,
            ]);
        }
    }
}

/** @return array<int, int|float> */
function legacyCosts(int $days, int|float $base = 15): array
{
    $costs = [];
    foreach (range(1, $days) as $day) {
        $costs[$day] = $base + 5 * $day;
    }

    return $costs;
}

it('importa i mesi come listini, riempie i mesi corti e scarta quelli vuoti', function () {
    fakeLegacyExceptions([
        '2026-02' => legacyCosts(28),
        '2026-11' => legacyCosts(30),
        '2027-01' => array_fill(1, 31, null),
    ]);

    $this->artisan('engine:import-legacy-exceptions', ['--connection' => 'legacy_fixture'])
        ->expectsOutputToContain('Mesi senza prezzi (vale il fallback sugli anni precedenti): 2027-01')
        ->expectsOutputToContain('Listini: 2 creato, 0 aggiornato, 0 invariato, 0 in conflitto; mesi scartati: 1.')
        ->assertSuccessful();

    $february = PriceList::query()->where('starts_on', '2026-02-01')->firstOrFail();

    expect(PriceList::query()->pluck('name')->all())->toBe(['Febbraio 2026', 'Novembre 2026'])
        ->and($february->source)->toBe(PriceList::SOURCE_LEGACY)
        ->and($february->ends_on->format('Y-m-d'))->toBe('2026-02-28')
        ->and($february->extra_day_cents)->toBe(1000)
        ->and($february->toPriceTable()->totalFor(30)->cents)->toBe(16500);
});

it('è idempotente e aggiorna solo i mesi cambiati', function () {
    fakeLegacyExceptions(['2026-10' => legacyCosts(31), '2026-11' => legacyCosts(30)]);
    $this->artisan('engine:import-legacy-exceptions', ['--connection' => 'legacy_fixture'])->assertSuccessful();
    $ids = PriceList::query()->orderBy('starts_on')->pluck('id')->all();

    $this->artisan('engine:import-legacy-exceptions', ['--connection' => 'legacy_fixture'])
        ->expectsOutputToContain('Listini: 0 creato, 0 aggiornato, 2 invariato')
        ->assertSuccessful();

    DB::connection('legacy_fixture')->table('exceptions')->where('data', '2026-11-03')->update(['costo' => 33]);

    $this->artisan('engine:import-legacy-exceptions', ['--connection' => 'legacy_fixture', '--extra-day-cents' => 900])
        ->expectsOutputToContain('Listini: 0 creato, 2 aggiornato, 0 invariato')
        ->assertSuccessful();

    $november = PriceList::query()->where('starts_on', '2026-11-01')->firstOrFail();

    expect(PriceList::query()->orderBy('starts_on')->pluck('id')->all())->toBe($ids)
        ->and($november->toPriceTable()->totalFor(3)->cents)->toBe(3300)
        ->and($november->extra_day_cents)->toBe(900)
        ->and($november->prices()->count())->toBe(30);
});

it('non scrive nulla in dry-run', function () {
    fakeLegacyExceptions(['2026-11' => legacyCosts(30)]);

    $this->artisan('engine:import-legacy-exceptions', ['--connection' => 'legacy_fixture', '--dry-run' => true])
        ->expectsOutputToContain('[dry-run] Nessuna modifica salvata. Listini: 1 creato')
        ->assertSuccessful();

    expect(PriceList::query()->count())->toBe(0);
});

it('non tocca i mesi coperti da un listino creato a mano', function () {
    PriceList::factory()->period('2026-11-15', '2026-12-15')->create(['name' => 'Manuale']);
    fakeLegacyExceptions(['2026-10' => legacyCosts(31), '2026-11' => legacyCosts(30)]);

    $this->artisan('engine:import-legacy-exceptions', ['--connection' => 'legacy_fixture'])
        ->expectsOutputToContain('Scartato 2026-11: si sovrappone a un listino creato a mano')
        ->expectsOutputToContain('Listini: 1 creato, 0 aggiornato, 0 invariato, 1 in conflitto')
        ->assertSuccessful();

    expect(PriceList::query()->orderBy('starts_on')->pluck('name')->all())->toBe(['Ottobre 2026', 'Manuale']);
});

it('rifiuta un prezzo del giorno aggiuntivo non valido', function () {
    $this->artisan('engine:import-legacy-exceptions', ['--extra-day-cents' => '-5'])->assertFailed();
});
