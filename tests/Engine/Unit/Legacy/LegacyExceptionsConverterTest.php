<?php

use App\Engine\Legacy\LegacyExceptionsConverter;

/**
 * @param  array<int, float|int|null>  $costs  prezzo per giorno del mese
 * @return list<array{date: string, cost: float|int|null}>
 */
function legacyMonth(string $yearMonth, array $costs): array
{
    return array_map(
        fn (int $day, $cost) => ['date' => sprintf('%s-%02d', $yearMonth, $day), 'cost' => $cost],
        array_keys($costs),
        $costs,
    );
}

/** @return array<int, int> giorno → prezzo lineare in euro */
function linearMonth(int $days, int $base = 15, int $step = 5): array
{
    $costs = [];
    foreach (range(1, $days) as $day) {
        $costs[$day] = $base + $step * $day;
    }

    return $costs;
}

it('crea un listino per mese, in centesimi, ignorando il 31° giorno', function () {
    $costs = linearMonth(31);
    $costs[14] = 85.5;

    $result = (new LegacyExceptionsConverter)->convert(legacyMonth('2026-10', $costs));
    $october = $result->priceLists[0];

    expect($result->priceLists)->toHaveCount(1)
        ->and($october->name)->toBe('Ottobre 2026')
        ->and($october->startsOn)->toBe('2026-10-01')
        ->and($october->endsOn)->toBe('2026-10-31')
        ->and($october->centsByDays)->toHaveCount(30)
        ->and($october->centsByDays[1])->toBe(2000)
        ->and($october->centsByDays[14])->toBe(8550)
        ->and($october->centsByDays[30])->toBe(16500)
        ->and($october->filledDays)->toBe([]);
});

it('nei mesi corti prosegue l\'ultimo incremento fino a 30 giorni', function () {
    // Febbraio 2026 nel legacy: 27 giorni 150 €, 28 giorni 155 €
    $costs = linearMonth(28);

    $february = (new LegacyExceptionsConverter)->convert(legacyMonth('2026-02', $costs))->priceLists[0];

    expect($february->centsByDays[28])->toBe(15500)
        ->and($february->centsByDays[29])->toBe(16000)
        ->and($february->centsByDays[30])->toBe(16500)
        ->and($february->filledDays)->toBe([29, 30])
        ->and($february->endsOn)->toBe('2026-02-28');
});

it('nei mesi di 30 giorni non riempie nulla e negli anni bisestili riempie solo il 30', function () {
    $converter = new LegacyExceptionsConverter;

    expect($converter->convert(legacyMonth('2026-11', linearMonth(30)))->priceLists[0]->filledDays)->toBe([])
        ->and($converter->convert(legacyMonth('2024-02', linearMonth(29)))->priceLists[0]->filledDays)->toBe([30]);
});

it('non riempie con un incremento negativo', function () {
    $costs = linearMonth(28);
    $costs[28] = 140;

    $result = (new LegacyExceptionsConverter)->convert(legacyMonth('2026-02', $costs));

    expect($result->priceLists[0]->centsByDays[29])->toBe(14000)
        ->and($result->priceLists[0]->centsByDays[30])->toBe(14000)
        ->and($result->warnings)->toBe(['2026-02: 28 giorni costano meno di 27']);
});

it('scarta i mesi senza prezzi e quelli con dei buchi', function () {
    $holes = linearMonth(31);
    $holes[3] = null;
    $holes[10] = null;

    $result = (new LegacyExceptionsConverter)->convert([
        ...legacyMonth('2027-01', array_fill(1, 31, null)),
        ...legacyMonth('2026-12', $holes),
        ...legacyMonth('2026-11', linearMonth(30)),
    ]);

    expect(array_map(fn ($list) => $list->yearMonth, $result->priceLists))->toBe(['2026-11'])
        ->and($result->skipped)->toBe([
            '2026-12: mancano i prezzi dei giorni 3, 10',
            '2027-01: nessun prezzo',
        ]);
});
