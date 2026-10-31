<?php

namespace App\Engine\Domain\Pricing;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Se manca il listino per la data, riprova con la stessa data negli anni
 * precedenti, fino a $maxYears (comportamento legacy). Il 29/02 diventa il 28/02.
 * Con $maxYears = 0 il fallback è disattivato.
 */
final readonly class PreviousYearsTariffSource implements TariffSource
{
    public function __construct(
        private TariffSource $inner,
        private int $maxYears = 5,
    ) {
        if ($maxYears < 0) {
            throw new InvalidArgumentException('Il numero di anni di fallback non può essere negativo.');
        }
    }

    public function lookup(DateTimeImmutable $date): ?TariffMatch
    {
        for ($years = 0; $years <= $this->maxYears; $years++) {
            $candidate = self::subYearsNoOverflow($date, $years);
            $match = $this->inner->lookup($candidate);

            if ($match !== null) {
                return new TariffMatch($match->table, $date, $candidate, $years);
            }
        }

        return null;
    }

    private static function subYearsNoOverflow(DateTimeImmutable $date, int $years): DateTimeImmutable
    {
        $year = (int) $date->format('Y') - $years;
        $month = (int) $date->format('n');
        $daysInMonth = (int) $date->setDate($year, $month, 1)->format('t');
        $day = min((int) $date->format('j'), $daysInMonth);

        return $date->setDate($year, $month, $day);
    }
}
