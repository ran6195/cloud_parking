<?php

namespace App\Engine\Filament\Support;

use App\Engine\Domain\Shared\Money;

/**
 * Nel pannello gli importi si scrivono in euro, nel database stanno in centesimi.
 */
final class Euro
{
    public static function toCents(mixed $euro): int
    {
        return Money::fromDecimal(str_replace(',', '.', (string) $euro))->cents;
    }

    public static function fromCents(?int $cents): ?string
    {
        return $cents === null ? null : Money::cents($cents)->toDecimalString();
    }

    public static function format(?int $cents): string
    {
        return $cents === null ? '—' : number_format($cents / 100, 2, ',', '.').' €';
    }
}
