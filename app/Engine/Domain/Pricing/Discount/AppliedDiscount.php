<?php

namespace App\Engine\Domain\Pricing\Discount;

use App\Engine\Domain\Shared\Money;

/**
 * Esito dell'applicazione di un codice sconto a un preventivo.
 * Se non applicato, $rejection dice perché e $amount è null.
 */
final readonly class AppliedDiscount
{
    private function __construct(
        public string $code,
        public bool $applied,
        public ?DiscountRejection $rejection,
        public ?DiscountType $type,
        public ?int $percentage,
        public ?Money $amount,
    ) {}

    public static function applied(Discount $discount, Money $amount): self
    {
        return new self($discount->code->value, true, null, $discount->type, $discount->percentage, $amount);
    }

    public static function rejected(string $code, DiscountRejection $reason): self
    {
        return new self($code, false, $reason, null, null, null);
    }
}
