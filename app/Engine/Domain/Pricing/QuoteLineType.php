<?php

namespace App\Engine\Domain\Pricing;

enum QuoteLineType: string
{
    case Parking = 'parking';
    case Extra = 'extra';
    case PaymentFee = 'payment_fee';
    case Info = 'info';
}
