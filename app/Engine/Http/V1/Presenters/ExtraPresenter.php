<?php

namespace App\Engine\Http\V1\Presenters;

use App\Engine\Domain\Pricing\ExtraDefinition;
use App\Engine\Domain\Pricing\ExtraId;

/**
 * Servizio aggiuntivo nel formato della spec (schema Extra).
 */
final class ExtraPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(ExtraDefinition $extra): array
    {
        return [
            'id' => $extra->id->value,
            'name' => $extra->name,
            'description' => $extra->description,
            'pricing' => $extra->pricing->value,
            'price' => $extra->price?->jsonSerialize(),
            'percentage' => $extra->percentage,
            'capacity_limited' => $extra->capacityLimited,
            'incompatible_with' => array_map(fn (ExtraId $id) => $id->value, $extra->incompatibleWith),
            'message' => $extra->message,
        ];
    }
}
