<?php

use App\Engine\Domain\Pricing\Discount\Discount;
use App\Engine\Domain\Pricing\Discount\DiscountCode;
use App\Engine\Domain\Pricing\Discount\DiscountPolicy;
use App\Engine\Domain\Pricing\Discount\DiscountRejection;
use App\Engine\Domain\Pricing\Discount\DiscountType;
use App\Engine\Domain\Pricing\Discount\InMemoryDiscountFinder;
use App\Engine\Domain\Shared\Money;

function discountPolicy(Discount ...$discounts): DiscountPolicy
{
    return new DiscountPolicy(new InMemoryDiscountFinder(...$discounts));
}

function discountToday(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-10-31 12:00', new DateTimeZone('Europe/Rome'));
}

it('applica uno sconto in percentuale come il legacy', function (int $subtotal, int $percent, int $expectedTotal) {
    $result = discountPolicy(Discount::percentage('AUTUNNO', $percent))->apply('AUTUNNO', Money::cents($subtotal), discountToday());

    expect($result->applied)->toBeTrue()
        ->and($subtotal - $result->amount->cents)->toBe($expectedTotal);
})->with([
    '48 € al 10%' => [4800, 10, 4320],
    '52 € al 10%' => [5200, 10, 4680],
    '58 € al 10%' => [5800, 10, 5220],
    'arrotondamento' => [3333, 15, 2833],
    '100%' => [5800, 100, 0],
]);

it('applica uno sconto fisso senza mai andare sotto zero', function (int $subtotal, int $amount, int $expectedDiscount) {
    $result = discountPolicy(Discount::fixed('FISSO', $amount))->apply('FISSO', Money::cents($subtotal), discountToday());

    expect($result->amount?->cents)->toBe($expectedDiscount);
})->with([
    'parziale' => [5000, 1000, 1000],
    'pari al totale' => [5000, 5000, 5000],
    'oltre il totale' => [3000, 5000, 3000],
]);

it('confronta il codice senza distinguere maiuscole e spazi', function () {
    $result = discountPolicy(Discount::percentage('ESTATE10', 10))->apply('  estate10 ', Money::cents(1000), discountToday());

    expect($result->applied)->toBeTrue()->and($result->code)->toBe('ESTATE10');
});

it('segnala un codice inesistente', function () {
    $result = discountPolicy()->apply('NONESISTE', Money::cents(1000), discountToday());

    expect($result->applied)->toBeFalse()
        ->and($result->rejection)->toBe(DiscountRejection::NotFound)
        ->and($result->amount)->toBeNull();
});

it('segnala un codice scaduto, valido fino a tutto l\'ultimo giorno', function () {
    $make = fn (string $until) => new Discount(new DiscountCode('NATALE'), DiscountType::Percentage, percentage: 10,
        validUntil: new DateTimeImmutable($until));

    expect(discountPolicy($make('2026-10-30'))->apply('NATALE', Money::cents(1000), discountToday())->rejection)->toBe(DiscountRejection::Expired)
        ->and(discountPolicy($make('2026-10-31'))->apply('NATALE', Money::cents(1000), discountToday())->applied)->toBeTrue();
});

it('segnala un codice esaurito', function (?int $maxUses, int $used, ?DiscountRejection $expected) {
    $discount = new Discount(new DiscountCode('LIMITATO'), DiscountType::Percentage, percentage: 10,
        maxUses: $maxUses, timesUsed: $used);

    expect(discountPolicy($discount)->apply('LIMITATO', Money::cents(1000), discountToday())->rejection)->toBe($expected);
})->with([
    'illimitato' => [null, 500, null],
    'ancora disponibile' => [100, 99, null],
    'esaurito' => [100, 100, DiscountRejection::Exhausted],
    'zero utilizzi consentiti' => [0, 0, DiscountRejection::Exhausted],
]);

it('rifiuta percentuali fuori intervallo', function (int $percent) {
    Discount::percentage('X', $percent);
})->with([0, 101])->throws(InvalidArgumentException::class);
