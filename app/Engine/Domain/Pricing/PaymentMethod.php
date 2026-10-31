<?php

namespace App\Engine\Domain\Pricing;

enum PaymentMethod: string
{
    case Online = 'online';
    case OnSite = 'on_site';
}
