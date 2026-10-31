<?php

namespace App\Engine\Domain\Pricing;

use App\Engine\Domain\Shared\Money;

/**
 * Riga del preventivo. $unitPrice è null per le voci forfettarie
 * (parcheggio, supplementi in percentuale).
 */
final readonly class QuoteLine
{
    public function __construct(
        public QuoteLineType $type,
        public string $code,
        public string $description,
        public int $quantity,
        public ?Money $unitPrice,
        public Money $total,
    ) {}
}
