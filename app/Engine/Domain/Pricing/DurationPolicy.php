<?php

namespace App\Engine\Domain\Pricing;

use InvalidArgumentException;

/**
 * Regola di conteggio dei giorni (dal legacy):
 * - meno di 24 ore → 1 giorno;
 * - altrimenti un giorno ogni 24 ore, più uno se l'eccedenza supera la tolleranza
 *   (15 minuti esatti non fanno scattare il giorno in più);
 * - oltre il 30° giorno i giorni si contano a parte (extraDays).
 */
final readonly class DurationPolicy
{
    private const MINUTES_PER_DAY = 1440;

    public function __construct(
        public int $toleranceMinutes = 15,
        public int $maxIncludedDays = PriceTable::DAYS,
    ) {
        if ($toleranceMinutes < 0 || $toleranceMinutes >= self::MINUTES_PER_DAY) {
            throw new InvalidArgumentException('Tolleranza non valida.');
        }
    }

    public function measure(StayPeriod $stay): StayDuration
    {
        $minutes = $stay->wallClockMinutes();

        if ($minutes < self::MINUTES_PER_DAY) {
            $days = 1;
        } else {
            $days = intdiv($minutes, self::MINUTES_PER_DAY);
            if ($minutes % self::MINUTES_PER_DAY > $this->toleranceMinutes) {
                $days++;
            }
        }

        $included = min($days, $this->maxIncludedDays);

        return new StayDuration(
            minutes: max($minutes, 0),
            billableDays: $days,
            includedDays: $included,
            extraDays: $days - $included,
        );
    }
}
