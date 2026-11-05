<?php

namespace App\Engine\Http\V1\Presenters;

use App\Engine\Domain\Shared\Money;

final class MoneyJson
{
    /**
     * @return array{amount_cents: int, currency: string}|null
     */
    public static function cents(?int $cents): ?array
    {
        return $cents === null ? null : Money::cents($cents)->jsonSerialize();
    }
}
