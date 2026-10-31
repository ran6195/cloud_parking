<?php

namespace App\Engine\Domain\Pricing;

/**
 * Esito del calcolo della durata.
 *
 * - billableDays: giorni fatturati (quelli mostrati al cliente)
 * - includedDays: giorni coperti dal prezzo forfettario del listino (al massimo 30)
 * - extraDays: giorni oltre il 30°, pagati con la tariffa giornaliera aggiuntiva
 */
final readonly class StayDuration
{
    public function __construct(
        public int $minutes,
        public int $billableDays,
        public int $includedDays,
        public int $extraDays,
    ) {}
}
