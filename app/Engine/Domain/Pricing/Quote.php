<?php

namespace App\Engine\Domain\Pricing;

use App\Engine\Domain\Pricing\Discount\AppliedDiscount;
use App\Engine\Domain\Shared\Money;

final readonly class Quote
{
    /**
     * @param  list<ExtraId>  $extras
     * @param  list<QuoteLine>  $lines
     */
    public function __construct(
        public StayPeriod $stay,
        public StayDuration $duration,
        public array $extras,
        public PaymentMethod $paymentMethod,
        public array $lines,
        public Money $subtotal,
        public ?AppliedDiscount $discount,
        public Money $total,
        public PricingTrace $trace,
    ) {}

    public function line(string $code): ?QuoteLine
    {
        foreach ($this->lines as $line) {
            if ($line->code === $code) {
                return $line;
            }
        }

        return null;
    }
}
