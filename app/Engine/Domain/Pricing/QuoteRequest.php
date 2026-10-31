<?php

namespace App\Engine\Domain\Pricing;

final readonly class QuoteRequest
{
    /**
     * @param  list<ExtraId>  $extras
     */
    public function __construct(
        public StayPeriod $stay,
        public array $extras = [],
        public PaymentMethod $paymentMethod = PaymentMethod::Online,
        public ?string $discountCode = null,
    ) {}
}
