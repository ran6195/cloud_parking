<?php

namespace App\Engine\Domain\Pricing\Discount;

enum DiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
