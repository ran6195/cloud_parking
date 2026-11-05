<?php

namespace App\Engine\Domain\Availability;

use App\Engine\Domain\Pricing\ExtraId;

/**
 * Posti occupati giorno per giorno, per il parcheggio e per i servizi richiesti.
 * Una prenotazione occupa i giorni da start_date incluso a end_date escluso.
 */
final readonly class DailyOccupancy
{
    /**
     * @param  array<string, int>  $parking  data Y-m-d → posti occupati
     * @param  array<string, array<string, int>>  $extras  ExtraId → data → posti occupati
     */
    public function __construct(
        public array $parking,
        public array $extras = [],
    ) {}

    public function parkingOn(string $date): int
    {
        return $this->parking[$date] ?? 0;
    }

    public function extraOn(ExtraId $id, string $date): int
    {
        return $this->extras[$id->value][$date] ?? 0;
    }
}
