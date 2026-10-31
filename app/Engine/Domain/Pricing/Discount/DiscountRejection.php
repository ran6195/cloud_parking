<?php

namespace App\Engine\Domain\Pricing\Discount;

enum DiscountRejection: string
{
    case NotFound = 'not_found';
    case Expired = 'expired';
    case Exhausted = 'exhausted';
}
