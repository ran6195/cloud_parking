<?php

namespace App\Engine\Domain\Pricing\Discount;

use App\Engine\Domain\Shared\Money;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Applica un codice sconto al subtotale (tutte le righe, commissioni comprese).
 *
 * - percentuale: totale = subtotale × (100 − p) / 100 arrotondato al centesimo (come il legacy);
 * - importo fisso: sconto = min(importo, subtotale).
 *
 * Il totale scontato resta sempre tra 0 e il subtotale.
 */
final readonly class DiscountPolicy
{
    public function __construct(private DiscountFinder $finder) {}

    public function apply(string $rawCode, Money $subtotal, DateTimeImmutable $today): AppliedDiscount
    {
        try {
            $code = new DiscountCode($rawCode);
        } catch (InvalidArgumentException) {
            return AppliedDiscount::rejected(mb_strtoupper(trim($rawCode)), DiscountRejection::NotFound);
        }

        $discount = $this->finder->find($code);

        if ($discount === null) {
            return AppliedDiscount::rejected($code->value, DiscountRejection::NotFound);
        }

        $rejection = $discount->rejectionAt($today);

        if ($rejection !== null) {
            return AppliedDiscount::rejected($code->value, $rejection);
        }

        return AppliedDiscount::applied($discount, $this->amount($discount, $subtotal));
    }

    private function amount(Discount $discount, Money $subtotal): Money
    {
        $amount = match ($discount->type) {
            DiscountType::Percentage => $subtotal->minus($subtotal->percentage(100 - (int) $discount->percentage)),
            DiscountType::FixedAmount => $discount->amount ?? Money::zero(),
        };

        return $amount->max(Money::zero())->min($subtotal->max(Money::zero()));
    }
}
