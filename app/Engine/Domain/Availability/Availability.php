<?php

namespace App\Engine\Domain\Availability;

/**
 * Esito del controllo: parcheggio e servizi a capacità limitata richiesti.
 */
final readonly class Availability
{
    /**
     * @param  array<string, AvailabilityItem>  $extras  indicizzati per ExtraId
     */
    public function __construct(
        public AvailabilityItem $parking,
        public array $extras,
    ) {}

    public function isAvailable(): bool
    {
        foreach ($this->extras as $extra) {
            if (! $extra->available) {
                return false;
            }
        }

        return $this->parking->available;
    }
}
