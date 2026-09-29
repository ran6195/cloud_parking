<?php

namespace App\Services;

use App\Settings\ParkingSettings;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class PriceCalculator
{
    public const MINUTES_PER_DAY = 1440;

    public function __construct(
        protected float $upTo3Days,
        protected float $week,
        protected float $twoWeeks,
        protected float $extraDay,
        protected ?float $coveredPerDay = null,
    ) {}

    public static function fromSettings(ParkingSettings $settings): self
    {
        return new self(
            upTo3Days: $settings->price_up_to_3_days,
            week: $settings->price_week,
            twoWeeks: $settings->price_two_weeks,
            extraDay: $settings->price_extra_day,
            coveredPerDay: $settings->price_covered_per_day,
        );
    }

    /**
     * Ogni periodo di 24 ore iniziato conta come un giorno intero.
     */
    public function days(CarbonInterface $from, CarbonInterface $to): int
    {
        if ($to->lessThanOrEqualTo($from)) {
            throw new InvalidArgumentException('La data di rientro deve essere successiva alla partenza.');
        }

        return (int) ceil($from->diffInMinutes($to) / self::MINUTES_PER_DAY);
    }

    /**
     * Tariffa a pacchetti: fino a 3 giorni, fino a 7, fino a 14, poi un importo per ogni giorno in piu'.
     */
    public function priceForDays(int $days, bool $covered = false): float
    {
        if ($days < 1) {
            throw new InvalidArgumentException('Il numero di giorni deve essere almeno 1.');
        }

        $price = match (true) {
            $days <= 3 => $this->upTo3Days,
            $days <= 7 => $this->week,
            $days <= 14 => $this->twoWeeks,
            default => $this->twoWeeks + ($days - 14) * $this->extraDay,
        };

        if ($covered && $this->coveredPerDay !== null) {
            $price += $days * $this->coveredPerDay;
        }

        return round($price, 2);
    }

    /**
     * @return array{days: int, price: float}
     */
    public function quote(CarbonInterface $from, CarbonInterface $to, bool $covered = false): array
    {
        $days = $this->days($from, $to);

        return ['days' => $days, 'price' => $this->priceForDays($days, $covered)];
    }
}
