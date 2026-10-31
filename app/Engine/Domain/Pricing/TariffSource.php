<?php

namespace App\Engine\Domain\Pricing;

use DateTimeImmutable;

/**
 * Fornisce il listino valido per la data (locale) di inizio sosta.
 */
interface TariffSource
{
    public function lookup(DateTimeImmutable $date): ?TariffMatch;
}
