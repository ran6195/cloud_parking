<?php

use App\Engine\Domain\Pricing\Discount\Discount;
use App\Engine\Domain\Pricing\Discount\DiscountRejection;
use App\Engine\Domain\Pricing\Exceptions\IncompatibleExtras;
use App\Engine\Domain\Pricing\Exceptions\PriceListNotFound;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\InMemoryTariffSource;
use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Domain\Pricing\QuoteLine;
use App\Engine\Domain\Pricing\QuoteLineType;
use App\Engine\Domain\Pricing\QuoteRequest;
use App\Engine\Domain\Pricing\StayPeriod;
use Tests\Engine\Support\Pricing;

function novemberTariffs(): InMemoryTariffSource
{
    // 3 giorni = 25 €, come nell'esempio della spec (10 € + 5 € × giorni)
    return (new InMemoryTariffSource)->addMonth('2026-11', Pricing::linearTable('Novembre 2026'));
}

function quoteStay(string $start = '2026-11-20 08:00', string $end = '2026-11-23 07:00'): StayPeriod
{
    return StayPeriod::fromStrings($start, $end);
}

function lineSummary(QuoteLine $line): array
{
    return [$line->type, $line->code, $line->description, $line->quantity, $line->unitPrice?->cents, $line->total->cents];
}

it('calcola il solo parcheggio', function () {
    $quote = Pricing::calculator(novemberTariffs())->quote(new QuoteRequest(quoteStay()));

    expect($quote->duration->billableDays)->toBe(3)
        ->and(array_map(lineSummary(...), $quote->lines))->toBe([
            [QuoteLineType::Parking, 'parking', 'Parcheggio scoperto', 3, null, 2500],
        ])
        ->and($quote->subtotal->cents)->toBe(2500)
        ->and($quote->discount)->toBeNull()
        ->and($quote->total->cents)->toBe(2500)
        ->and($quote->trace->priceTable)->toBe('Novembre 2026')
        ->and($quote->trace->fallbackYears)->toBe(0);
});

it('compone parcheggio, coperto, servizi, commissione e sconto', function () {
    $quote = Pricing::calculator(novemberTariffs(), null, '2026-10-31 12:00 Europe/Rome', Discount::percentage('ESTATE10', 10))
        ->quote(new QuoteRequest(
            quoteStay(),
            [ExtraId::ExteriorWash, ExtraId::CoveredParking, ExtraId::EvCharging],
            PaymentMethod::OnSite,
            'estate10',
        ));

    expect(array_map(lineSummary(...), $quote->lines))->toBe([
        [QuoteLineType::Parking, 'parking', 'Parcheggio', 3, null, 2500],
        [QuoteLineType::Extra, 'covered_parking', 'Posto coperto (+40%)', 3, null, 1000],
        [QuoteLineType::Extra, 'exterior_wash', 'Lavaggio esterno', 1, 1500, 1500],
        [QuoteLineType::Info, 'ev_charging', 'Ricarica auto elettrica', 1, 0, 0],
        [QuoteLineType::PaymentFee, 'on_site', 'Pagamento in parcheggio', 1, 100, 100],
    ])
        ->and($quote->subtotal->cents)->toBe(5100)
        ->and($quote->discount?->applied)->toBeTrue()
        ->and($quote->discount?->amount?->cents)->toBe(510)
        ->and($quote->total->cents)->toBe(4590)
        ->and($quote->extras)->toBe([ExtraId::CoveredParking, ExtraId::ExteriorWash, ExtraId::EvCharging]);
});

it('calcola il coperto sul parcheggio compresi i giorni oltre il 30°', function () {
    $tariffs = (new InMemoryTariffSource)->addMonth('2026-11', Pricing::linearTable(extraDayCents: 333));

    $quote = Pricing::calculator($tariffs)->quote(new QuoteRequest(
        quoteStay('2026-11-01 08:00', '2026-12-03 08:00'),
        [ExtraId::CoveredParking],
    ));

    // 30 giorni = 160 €, + 2 × 3,33 € = 166,66 €; 40% = 66,664 → 66,66 €
    expect($quote->duration->billableDays)->toBe(32)
        ->and($quote->line('parking')?->total->cents)->toBe(16666)
        ->and($quote->line('parking')?->quantity)->toBe(32)
        ->and($quote->line('covered_parking')?->total->cents)->toBe(6666)
        ->and($quote->line('covered_parking')?->quantity)->toBe(32);
});

it('non aggiunge commissioni al pagamento online', function () {
    $quote = Pricing::calculator(novemberTariffs())->quote(new QuoteRequest(quoteStay(), [], PaymentMethod::Online));

    expect($quote->line('on_site'))->toBeNull()->and($quote->total->cents)->toBe(2500);
});

it('calcola comunque il preventivo con un codice sconto non valido', function () {
    $quote = Pricing::calculator(novemberTariffs())->quote(new QuoteRequest(quoteStay(), [], PaymentMethod::Online, 'NONESISTE'));

    expect($quote->discount?->applied)->toBeFalse()
        ->and($quote->discount?->rejection)->toBe(DiscountRejection::NotFound)
        ->and($quote->total->cents)->toBe(2500);
});

it('ignora un codice sconto vuoto', function () {
    $quote = Pricing::calculator(novemberTariffs())->quote(new QuoteRequest(quoteStay(), [], PaymentMethod::Online, '  '));

    expect($quote->discount)->toBeNull();
});

it('usa il listino dell\'anno precedente se manca quello dell\'anno richiesto', function () {
    $quote = Pricing::calculator(novemberTariffs())->quote(new QuoteRequest(quoteStay('2027-11-20 08:00', '2027-11-23 07:00')));

    expect($quote->total->cents)->toBe(2500)
        ->and($quote->trace->fallbackYears)->toBe(1)
        ->and($quote->trace->matchedDate->format('Y-m-d'))->toBe('2026-11-20');
});

it('sceglie il listino del giorno di inizio sosta', function () {
    $tariffs = novemberTariffs()->addMonth('2026-12', Pricing::linearTable('Dicembre 2026', baseCents: 2000));

    $quote = Pricing::calculator($tariffs)->quote(new QuoteRequest(quoteStay('2026-11-30 22:00', '2026-12-03 20:00')));

    expect($quote->trace->priceTable)->toBe('Novembre 2026');
});

it('fallisce senza un listino applicabile', function () {
    Pricing::calculator(new InMemoryTariffSource)->quote(new QuoteRequest(quoteStay()));
})->throws(PriceListNotFound::class);

it('fallisce con servizi incompatibili', function () {
    Pricing::calculator(novemberTariffs())->quote(new QuoteRequest(quoteStay(), [ExtraId::CoveredParking, ExtraId::KeyDrop]));
})->throws(IncompatibleExtras::class);
