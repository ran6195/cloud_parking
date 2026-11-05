<?php

namespace App\Engine\Infrastructure\Pricing;

use App\Engine\Domain\Pricing\Discount\Discount;
use App\Engine\Domain\Pricing\Discount\DiscountCode;
use App\Engine\Domain\Pricing\Discount\DiscountFinder;
use App\Engine\Models\Discount as DiscountModel;

/**
 * Codici sconto attivi (non cancellati), con gli utilizzi contati sulle prenotazioni valide.
 */
final class DatabaseDiscountFinder implements DiscountFinder
{
    public function find(DiscountCode $code): ?Discount
    {
        return DiscountModel::query()->where('code', $code->value)->first()?->toDomain();
    }
}
