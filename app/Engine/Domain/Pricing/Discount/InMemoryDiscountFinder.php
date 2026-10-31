<?php

namespace App\Engine\Domain\Pricing\Discount;

final readonly class InMemoryDiscountFinder implements DiscountFinder
{
    /** @var array<string, Discount> */
    private array $discounts;

    public function __construct(Discount ...$discounts)
    {
        $indexed = [];
        foreach ($discounts as $discount) {
            $indexed[$discount->code->value] = $discount;
        }
        $this->discounts = $indexed;
    }

    public function find(DiscountCode $code): ?Discount
    {
        return $this->discounts[$code->value] ?? null;
    }
}
