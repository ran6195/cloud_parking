<?php

namespace App\Engine\Domain\Availability;

use App\Engine\Domain\Pricing\ExtraId;
use DateTimeImmutable;

/**
 * Dati per il controllo di disponibilità su un intervallo di date locali (estremi inclusi).
 */
interface AvailabilityData
{
    /**
     * @return list<string> date Y-m-d bloccate nell'intervallo, in ordine
     */
    public function blockedDates(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @param  list<ExtraId>  $extras
     */
    public function occupancy(DateTimeImmutable $from, DateTimeImmutable $to, array $extras): DailyOccupancy;
}
