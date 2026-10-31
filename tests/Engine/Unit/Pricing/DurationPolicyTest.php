<?php

use App\Engine\Domain\Pricing\DurationPolicy;
use App\Engine\Domain\Pricing\Exceptions\InvalidStay;
use App\Engine\Domain\Pricing\StayPeriod;

function measureStay(string $start, string $end): array
{
    $duration = (new DurationPolicy)->measure(StayPeriod::fromStrings($start, $end));

    return [$duration->billableDays, $duration->includedDays, $duration->extraDays];
}

it('conta i giorni con la tolleranza di 15 minuti', function (string $end, int $days) {
    expect(measureStay('2026-11-02 08:00', $end)[0])->toBe($days);
})->with([
    '1 minuto' => ['2026-11-02 08:01', 1],
    '23h59' => ['2026-11-03 07:59', 1],
    '24h00' => ['2026-11-03 08:00', 1],
    '24h15' => ['2026-11-03 08:15', 1],
    '24h16' => ['2026-11-03 08:16', 2],
    '48h00' => ['2026-11-04 08:00', 2],
    '71h00' => ['2026-11-05 07:00', 3],
]);

it('oltre i 30 giorni conta a parte i giorni in più', function () {
    expect(measureStay('2026-11-01 08:00', '2026-12-01 08:16'))->toBe([31, 30, 1])
        ->and(measureStay('2026-11-01 08:00', '2026-12-01 08:00'))->toBe([30, 30, 0])
        ->and(measureStay('2026-11-01 08:00', '2026-12-11 08:00'))->toBe([40, 30, 10]);
});

it('ignora il cambio dell\'ora legale: conta l\'orologio, non i minuti reali', function (string $start, string $end, int $days) {
    expect(measureStay($start, $end)[0])->toBe($days);
})->with([
    // 25/10/2026: si torna all'ora solare (la giornata dura 25 ore reali)
    'fine ora legale 24h' => ['2026-10-24 08:00', '2026-10-25 08:00', 1],
    'fine ora legale 24h15' => ['2026-10-24 08:00', '2026-10-25 08:15', 1],
    'fine ora legale 24h16' => ['2026-10-24 08:00', '2026-10-25 08:16', 2],
    // 29/03/2026: si passa all'ora legale (la giornata dura 23 ore reali)
    'inizio ora legale 24h' => ['2026-03-28 08:00', '2026-03-29 08:00', 1],
    'inizio ora legale 48h' => ['2026-03-28 08:00', '2026-03-30 08:00', 2],
]);

it('lavora nel fuso del parcheggio anche se l\'input ha un altro offset', function () {
    $stay = StayPeriod::fromStrings('2026-11-02T07:00:00Z', '2026-11-03T07:00:00Z');

    expect($stay->start->format('Y-m-d H:i P'))->toBe('2026-11-02 08:00 +01:00')
        ->and((new DurationPolicy)->measure($stay)->billableDays)->toBe(1);
});

it('rifiuta un rientro non successivo alla partenza', function (string $end) {
    StayPeriod::fromStrings('2026-11-02 08:00', $end);
})->with(['2026-11-02 08:00', '2026-11-01 08:00'])->throws(InvalidStay::class);

it('rifiuta soste oltre un anno', function () {
    StayPeriod::fromStrings('2026-11-02 08:00', '2027-11-03 08:00');
})->throws(InvalidStay::class);

it('ha una tolleranza configurabile', function () {
    $policy = new DurationPolicy(toleranceMinutes: 60);

    expect($policy->measure(StayPeriod::fromStrings('2026-11-02 08:00', '2026-11-03 09:00'))->billableDays)->toBe(1)
        ->and($policy->measure(StayPeriod::fromStrings('2026-11-02 08:00', '2026-11-03 09:01'))->billableDays)->toBe(2);
});
