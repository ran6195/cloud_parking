<?php

use App\Engine\Domain\Shared\Money;

it('somma, sottrae e moltiplica in centesimi', function () {
    $money = Money::cents(1050)->plus(Money::cents(250))->minus(Money::cents(100))->times(3);

    expect($money->cents)->toBe(3600);
});

it('converte da decimale senza errori di virgola mobile', function (string|float $decimal, int $cents) {
    expect(Money::fromDecimal($decimal)->cents)->toBe($cents);
})->with([
    ['0.1', 10],
    ['85.5', 8550],
    [2.625, 263],
    [19.99, 1999],
    ['0', 0],
]);

it('calcola le percentuali arrotondando la metà per eccesso', function (int $cents, int $percent, int $expected) {
    expect(Money::cents($cents)->percentage($percent)->cents)->toBe($expected);
})->with([
    'esatto' => [3000, 40, 1200],
    'per difetto' => [1733, 40, 693],
    'per eccesso' => [1737, 40, 695],
    'metà esatta' => [10, 35, 4],
    'zero' => [0, 40, 0],
]);

it('formatta come decimale', function () {
    expect(Money::cents(5220)->toDecimalString())->toBe('52.20')
        ->and(Money::cents(5)->toDecimalString())->toBe('0.05')
        ->and(Money::cents(-150)->toDecimalString())->toBe('-1.50');
});

it('si serializza come nel contratto API', function () {
    expect(json_encode(Money::cents(4500)))->toBe('{"amount_cents":4500,"currency":"EUR"}');
});

it('rifiuta importi testuali non numerici', function () {
    Money::fromDecimal('12,50');
})->throws(InvalidArgumentException::class);
