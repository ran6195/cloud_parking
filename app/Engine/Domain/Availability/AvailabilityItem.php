<?php

namespace App\Engine\Domain\Availability;

/**
 * Disponibilità del parcheggio o di un servizio a capacità limitata.
 */
final readonly class AvailabilityItem
{
    /**
     * @param  list<string>  $unavailableDates  date Y-m-d che rendono il periodo non disponibile
     */
    private function __construct(
        public bool $available,
        public ?AvailabilityReason $reason,
        public array $unavailableDates,
    ) {}

    public static function available(): self
    {
        return new self(true, null, []);
    }

    /**
     * @param  list<string>  $dates
     */
    public static function unavailable(AvailabilityReason $reason, array $dates = []): self
    {
        return new self(false, $reason, $dates);
    }
}
