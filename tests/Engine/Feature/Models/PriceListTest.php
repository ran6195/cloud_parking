<?php

use App\Engine\Domain\Pricing\PreviousYearsTariffSource;
use App\Engine\Infrastructure\Pricing\DatabaseTariffSource;
use App\Engine\Models\PriceList;
use Database\Engine\Factories\PriceListFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshEngineDatabase;

uses(RefreshEngineDatabase::class);

/** Esegue in un savepoint: in Postgres un errore annullerebbe la transazione del test. */
function engineSavepoint(Closure $callback): mixed
{
    return DB::connection('engine')->transaction($callback);
}

it('converte il listino salvato in un listino di dominio completo', function () {
    $priceList = PriceList::factory()->create(['name' => 'Novembre 2026', 'extra_day_cents' => 900]);

    $table = $priceList->fresh()?->toPriceTable();

    expect($table?->label)->toBe('Novembre 2026')
        ->and($table?->totalFor(1)->cents)->toBe(1500)
        ->and($table?->totalFor(30)->cents)->toBe(16000)
        ->and($table?->extraDayPrice->cents)->toBe(900);
});

it('rifiuta un listino incompleto quando lo si usa', function () {
    $priceList = PriceList::factory()->withPrices([1 => 1000, 2 => 1500])->create();

    expect(fn () => $priceList->fresh()?->toPriceTable())->toThrow(InvalidArgumentException::class, 'incompleto');
});

it('impedisce periodi sovrapposti tra listini attivi', function () {
    PriceList::factory()->period('2026-11-01', '2026-11-30')->create();

    expect(fn () => engineSavepoint(fn () => PriceList::factory()->period('2026-11-30', '2026-12-31')->create()))
        ->toThrow(QueryException::class, 'price_lists_no_overlap');
});

it('ammette periodi contigui e la sovrapposizione con un listino cancellato', function () {
    $november = PriceList::factory()->period('2026-11-01', '2026-11-30')->create();
    PriceList::factory()->period('2026-12-01', '2026-12-31')->create();

    $november->delete();
    PriceList::factory()->period('2026-11-15', '2026-11-30')->create();

    expect(PriceList::query()->count())->toBe(2);
});

it('rifiuta righe fuori da 1..30 giorni e periodi rovesciati', function (Closure $write, string $constraint) {
    expect(fn () => engineSavepoint($write))->toThrow(QueryException::class, $constraint);
})->with([
    '31 giorni' => [fn () => PriceList::factory()->create()->prices()->create(['days' => 31, 'amount_cents' => 1]), 'prices_days_check'],
    'fine prima dell\'inizio' => [fn () => PriceList::factory()->period('2026-11-30', '2026-11-01')->create(), 'price_lists_period_check'],
]);

it('trova il listino per la data di inizio sosta, estremi inclusi', function () {
    PriceList::factory()->period('2026-11-01', '2026-11-30')->create(['name' => 'Novembre']);
    PriceList::factory()->period('2026-12-01', '2026-12-31')->withPrices(PriceListFactory::linearCents(2000))->create(['name' => 'Dicembre']);

    $source = new DatabaseTariffSource;

    expect($source->lookup(new DateTimeImmutable('2026-11-30 23:59'))?->table->label)->toBe('Novembre')
        ->and($source->lookup(new DateTimeImmutable('2026-12-01 00:00'))?->table->label)->toBe('Dicembre')
        ->and($source->lookup(new DateTimeImmutable('2027-01-01')))->toBeNull();
});

it('ripiega sull\'anno precedente e ignora i listini cancellati', function () {
    PriceList::factory()->period('2026-01-01', '2026-01-31')->create(['name' => 'Gennaio 2026']);
    PriceList::factory()->period('2027-01-01', '2027-01-31')->create(['name' => 'Gennaio 2027'])->delete();

    $match = (new PreviousYearsTariffSource(new DatabaseTariffSource))->lookup(new DateTimeImmutable('2027-01-15'));

    expect($match?->table->label)->toBe('Gennaio 2026')
        ->and($match?->yearsBack)->toBe(1);
});
