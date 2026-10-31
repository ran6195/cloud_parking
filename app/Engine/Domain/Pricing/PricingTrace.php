<?php

namespace App\Engine\Domain\Pricing;

use DateTimeImmutable;

/**
 * Come è stato calcolato il prezzo: per il simulatore del pannello e per i log.
 */
final readonly class PricingTrace
{
    public function __construct(
        public string $priceTable,
        public DateTimeImmutable $requestedDate,
        public DateTimeImmutable $matchedDate,
        public int $fallbackYears,
    ) {}
}
