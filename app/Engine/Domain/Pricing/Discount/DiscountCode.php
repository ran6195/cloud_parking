<?php

namespace App\Engine\Domain\Pricing\Discount;

use InvalidArgumentException;

/**
 * Codice sconto normalizzato: senza spazi esterni, in maiuscolo (confronto case-insensitive).
 */
final readonly class DiscountCode
{
    public string $value;

    public function __construct(string $code)
    {
        $normalized = mb_strtoupper(trim($code));

        if ($normalized === '' || mb_strlen($normalized) > 50) {
            throw new InvalidArgumentException('Codice sconto non valido.');
        }

        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
