<?php

namespace App\Engine\Domain\Pricing;

use DateTimeImmutable;

/**
 * Listino trovato per una data, con l'indicazione di un eventuale fallback.
 */
final readonly class TariffMatch
{
    public function __construct(
        public PriceTable $table,
        public DateTimeImmutable $requestedDate,
        public DateTimeImmutable $matchedDate,
        public int $yearsBack = 0,
    ) {}

    public function isFallback(): bool
    {
        return $this->yearsBack > 0;
    }
}
