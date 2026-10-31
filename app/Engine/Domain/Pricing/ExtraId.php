<?php

namespace App\Engine\Domain\Pricing;

/**
 * Servizi aggiuntivi. L'ordine dei casi è l'ordine delle righe nel preventivo.
 */
enum ExtraId: string
{
    case CoveredParking = 'covered_parking';
    case ExteriorWash = 'exterior_wash';
    case InteriorWash = 'interior_wash';
    case KeyDrop = 'key_drop';
    case EvCharging = 'ev_charging';
    case TyreRepair = 'tyre_repair';

    public function position(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
