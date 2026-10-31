<?php

namespace App\Engine\Domain\Pricing;

enum ExtraPricing: string
{
    /** Prezzo fisso, quantità 1. */
    case Fixed = 'fixed';

    /** Percentuale sul costo del parcheggio, quantità = giorni fatturati. */
    case PercentageOfParking = 'percentage_of_parking';

    /** Nessun costo: la riga resta a 0 e mostra un messaggio. */
    case Informational = 'informational';
}
