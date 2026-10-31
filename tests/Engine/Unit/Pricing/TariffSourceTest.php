<?php

use App\Engine\Domain\Pricing\InMemoryTariffSource;
use App\Engine\Domain\Pricing\PreviousYearsTariffSource;
use App\Engine\Domain\Pricing\PriceTable;
use App\Engine\Domain\Pricing\StayDuration;
use Tests\Engine\Support\Pricing;

it('usa il prezzo forfettario per N giorni', function () {
    $table = Pricing::linearTable();

    expect($table->priceFor(new StayDuration(0, 1, 1, 0))->cents)->toBe(1500)
        ->and($table->priceFor(new StayDuration(0, 7, 7, 0))->cents)->toBe(4500)
        ->and($table->priceFor(new StayDuration(0, 30, 30, 0))->cents)->toBe(16000);
});

it('aggiunge i giorni oltre il 30° con la tariffa giornaliera', function () {
    $table = Pricing::linearTable(extraDayCents: 800);

    expect($table->priceFor(new StayDuration(0, 33, 30, 3))->cents)->toBe(16000 + 3 * 800);
});

it('rifiuta un listino incompleto', function () {
    $totals = array_fill(1, 28, 1000);

    PriceTable::fromCents($totals, 1000, 'Febbraio');
})->throws(InvalidArgumentException::class, 'mancano i giorni [29, 30]');

it('rifiuta prezzi negativi', function () {
    $totals = array_fill(1, 30, 1000);
    $totals[5] = -1;

    PriceTable::fromCents($totals, 1000, 'Errato');
})->throws(InvalidArgumentException::class);

it('sceglie il listino in base alla data, estremi inclusi', function () {
    $source = (new InMemoryTariffSource)
        ->addMonth('2026-11', Pricing::linearTable('Novembre 2026'))
        ->addMonth('2026-12', Pricing::linearTable('Dicembre 2026'));

    expect($source->lookup(new DateTimeImmutable('2026-11-30'))?->table->label)->toBe('Novembre 2026')
        ->and($source->lookup(new DateTimeImmutable('2026-12-01'))?->table->label)->toBe('Dicembre 2026')
        ->and($source->lookup(new DateTimeImmutable('2027-01-01')))->toBeNull();
});

it('rifiuta periodi sovrapposti', function () {
    (new InMemoryTariffSource)
        ->add('2026-11-01', '2026-11-30', Pricing::linearTable())
        ->add('2026-11-30', '2026-12-31', Pricing::linearTable());
})->throws(InvalidArgumentException::class);

it('ripiega sugli anni precedenti fino al limite', function () {
    $source = new PreviousYearsTariffSource(
        (new InMemoryTariffSource)->addMonth('2024-11', Pricing::linearTable('Novembre 2024')),
        maxYears: 2,
    );

    $match = $source->lookup(new DateTimeImmutable('2026-11-15'));

    expect($match?->table->label)->toBe('Novembre 2024')
        ->and($match?->yearsBack)->toBe(2)
        ->and($match?->isFallback())->toBeTrue()
        ->and($match?->matchedDate->format('Y-m-d'))->toBe('2024-11-15')
        ->and($source->lookup(new DateTimeImmutable('2027-11-15')))->toBeNull();
});

it('nel fallback il 29 febbraio diventa il 28', function () {
    $source = new PreviousYearsTariffSource(
        (new InMemoryTariffSource)->add('2027-02-28', '2027-02-28', Pricing::linearTable('28/02/2027')),
    );

    $match = $source->lookup(new DateTimeImmutable('2028-02-29'));

    expect($match?->matchedDate->format('Y-m-d'))->toBe('2027-02-28')
        ->and($match?->yearsBack)->toBe(1);
});

it('senza fallback cerca solo la data richiesta', function () {
    $source = new PreviousYearsTariffSource(
        (new InMemoryTariffSource)->addMonth('2025-11', Pricing::linearTable()),
        maxYears: 0,
    );

    expect($source->lookup(new DateTimeImmutable('2026-11-15')))->toBeNull();
});
