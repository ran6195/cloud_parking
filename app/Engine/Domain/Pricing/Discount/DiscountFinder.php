<?php

namespace App\Engine\Domain\Pricing\Discount;

interface DiscountFinder
{
    public function find(DiscountCode $code): ?Discount;
}
