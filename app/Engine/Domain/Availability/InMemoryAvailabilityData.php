<?php

namespace App\Engine\Domain\Availability;

use DateTimeImmutable;

/**
 * Dati di disponibilità in memoria, per test e simulazioni.
 */
final class InMemoryAvailabilityData implements AvailabilityData
{
    /**
     * @param  list<string>  $blocked  date Y-m-d bloccate
     */
    public function __construct(
        private array $blocked = [],
        private DailyOccupancy $occupancy = new DailyOccupancy([]),
    ) {}

    public function blockedDates(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $blocked = array_values(array_filter(
            $this->blocked,
            fn (string $day) => $from->format('Y-m-d') <= $day && $day <= $to->format('Y-m-d'),
        ));
        sort($blocked);

        return $blocked;
    }

    public function occupancy(DateTimeImmutable $from, DateTimeImmutable $to, array $extras): DailyOccupancy
    {
        return $this->occupancy;
    }
}
