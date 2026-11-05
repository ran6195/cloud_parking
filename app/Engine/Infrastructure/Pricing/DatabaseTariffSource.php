<?php

namespace App\Engine\Infrastructure\Pricing;

use App\Engine\Domain\Pricing\TariffMatch;
use App\Engine\Domain\Pricing\TariffSource;
use App\Engine\Models\PriceList;
use DateTimeImmutable;

/**
 * Listini salvati nello schema del motore. Va avvolto in PreviousYearsTariffSource
 * per il fallback sugli anni precedenti.
 */
final class DatabaseTariffSource implements TariffSource
{
    public function lookup(DateTimeImmutable $date): ?TariffMatch
    {
        $priceList = PriceList::query()
            ->covering($date->format('Y-m-d'))
            ->with('prices')
            ->first();

        return $priceList === null ? null : new TariffMatch($priceList->toPriceTable(), $date, $date);
    }
}
