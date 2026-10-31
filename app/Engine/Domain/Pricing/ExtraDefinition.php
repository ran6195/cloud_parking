<?php

namespace App\Engine\Domain\Pricing;

use App\Engine\Domain\Shared\Money;
use InvalidArgumentException;

final readonly class ExtraDefinition
{
    /**
     * @param  list<ExtraId>  $incompatibleWith
     */
    public function __construct(
        public ExtraId $id,
        public string $name,
        public ExtraPricing $pricing,
        public bool $active = true,
        public ?Money $price = null,
        public ?int $percentage = null,
        public bool $capacityLimited = false,
        public array $incompatibleWith = [],
        public ?string $description = null,
        public ?string $message = null,
    ) {
        if ($pricing === ExtraPricing::Fixed && ($price === null || $price->isNegative())) {
            throw new InvalidArgumentException("{$id->value}: un servizio a prezzo fisso richiede un prezzo valido.");
        }

        if ($pricing === ExtraPricing::PercentageOfParking && ($percentage === null || $percentage < 0)) {
            throw new InvalidArgumentException("{$id->value}: un servizio in percentuale richiede una percentuale valida.");
        }
    }

    public function lineDescription(): string
    {
        return $this->pricing === ExtraPricing::PercentageOfParking
            ? "{$this->name} (+{$this->percentage}%)"
            : $this->name;
    }
}
