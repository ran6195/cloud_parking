<?php

namespace App\Engine\Legacy;

/**
 * Listino mensile ricavato dalla tabella legacy "exceptions".
 */
final readonly class LegacyMonthlyPriceList
{
    /**
     * @param  array<int, int>  $centsByDays  1..30
     * @param  list<int>  $filledDays  giorni assenti nel legacy e riempiti con la regola dell'ultimo incremento
     */
    public function __construct(
        public string $yearMonth,
        public string $startsOn,
        public string $endsOn,
        public string $name,
        public array $centsByDays,
        public array $filledDays,
    ) {}
}
