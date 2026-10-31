<?php

/*
 * Golden test: il nuovo motore deve dare, al centesimo, gli stessi importi del
 * legacy sulle prenotazioni web reali (fixture generata dal dump del 2026-10-31,
 * prenotazioni create a listini invariati; vedi "_source" e "_skipped").
 *
 * Mappatura legacy → motore: coperto → covered_parking, tieni_le_chiavi → key_drop,
 * in_aeroporto → payment_method on_site.
 */

use App\Engine\Domain\Pricing\Discount\Discount;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\InMemoryTariffSource;
use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Domain\Pricing\PriceTable;
use App\Engine\Domain\Pricing\QuoteRequest;
use App\Engine\Domain\Pricing\StayPeriod;
use Tests\Engine\Support\Pricing;

/**
 * @return array<string, mixed>
 */
function legacyGolden(): array
{
    static $fixture = null;

    return $fixture ??= json_decode(
        (string) file_get_contents(__DIR__.'/../../Fixtures/legacy_golden.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

function legacyTariffs(): InMemoryTariffSource
{
    $fixture = legacyGolden();
    $source = new InMemoryTariffSource;

    foreach ($fixture['tariffs'] as $month => $prices) {
        $source->addMonth($month, PriceTable::fromCents(
            array_combine(array_map(intval(...), array_keys($prices)), array_values($prices)),
            $fixture['_rules']['extra_day_cents'],
            "Legacy {$month}",
        ));
    }

    return $source;
}

dataset('legacy_cases', function () {
    foreach (legacyGolden()['cases'] as $case) {
        yield "#{$case['legacy_id']} {$case['start']} → {$case['end']}" => [$case];
    }
});

it('ha abbastanza casi reali nella fixture', function () {
    expect(count(legacyGolden()['cases']))->toBeGreaterThanOrEqual(20);
});

it('riproduce al centesimo il prezzo del legacy', function (array $case) {
    $discounts = $case['discount_percent'] === null ? [] : [Discount::percentage('LEGACY', $case['discount_percent'])];

    $quote = Pricing::calculator(legacyTariffs(), null, '2026-10-20 12:00 Europe/Rome', ...$discounts)->quote(new QuoteRequest(
        StayPeriod::fromStrings($case['start'], $case['end']),
        array_map(ExtraId::from(...), $case['extras']),
        PaymentMethod::from($case['payment_method']),
        $case['discount_percent'] === null ? null : 'LEGACY',
    ));

    $expected = $case['expected'];

    expect($quote->duration->billableDays)->toBe($expected['billable_days'])
        ->and($quote->line('parking')?->total->cents)->toBe($expected['parking_cents'])
        ->and($quote->line('covered_parking')?->total->cents)->toBe($expected['covered_parking_cents'] ?? null)
        ->and($quote->line('key_drop')?->total->cents)->toBe($expected['key_drop_cents'] ?? null)
        ->and($quote->line('on_site')?->total->cents)->toBe($expected['on_site_fee_cents'] ?? null)
        ->and($quote->subtotal->cents)->toBe($expected['subtotal_cents'])
        ->and($quote->total->cents)->toBe($expected['total_cents']);
})->with('legacy_cases');

it('rispetta gli esempi di docs/pricing.md', function () {
    $quote = Pricing::calculator(legacyTariffs(), null, '2026-10-20 12:00 Europe/Rome', Discount::percentage('DIECI', 10))
        ->quote(new QuoteRequest(
            StayPeriod::fromStrings('2026-11-10 08:00', '2026-11-13 08:00'),
            [ExtraId::CoveredParking],
            PaymentMethod::OnSite,
            'dieci',
        ));

    expect($quote->line('parking')?->total->cents)->toBe(2700)
        ->and($quote->line('covered_parking')?->total->cents)->toBe(1080)
        ->and($quote->subtotal->cents)->toBe(3880)
        ->and($quote->discount?->amount?->cents)->toBe(388)
        ->and($quote->total->cents)->toBe(3492);

    $long = Pricing::calculator(legacyTariffs())->quote(new QuoteRequest(
        StayPeriod::fromStrings('2026-11-01 08:00', '2026-12-03 08:00'),
    ));

    expect($long->duration->billableDays)->toBe(32)
        ->and($long->total->cents)->toBe(18500);
});
