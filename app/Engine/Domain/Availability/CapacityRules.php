<?php

namespace App\Engine\Domain\Availability;

use App\Engine\Domain\Pricing\ExtraId;
use InvalidArgumentException;

/**
 * Capacità configurate dal gestore.
 *
 * - totalSpots: posti del parcheggio, controllati solo con il blocco automatico attivo
 * - extraCapacities: posti per i servizi a capacità limitata (0 = servizio non disponibile)
 */
final readonly class CapacityRules
{
    /**
     * @param  array<string, int>  $extraCapacities  indicizzate per ExtraId
     */
    public function __construct(
        public int $totalSpots,
        public bool $autoBlock,
        public array $extraCapacities = [],
    ) {
        if ($totalSpots < 0) {
            throw new InvalidArgumentException('Il numero di posti non può essere negativo.');
        }

        foreach ($extraCapacities as $id => $capacity) {
            if ($capacity < 0) {
                throw new InvalidArgumentException("Capacità negativa per {$id}.");
            }
        }
    }

    public function capacityFor(ExtraId $id): int
    {
        return $this->extraCapacities[$id->value] ?? 0;
    }
}
