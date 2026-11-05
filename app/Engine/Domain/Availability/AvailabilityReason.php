<?php

namespace App\Engine\Domain\Availability;

enum AvailabilityReason: string
{
    /** Il gestore ha bloccato almeno un giorno del periodo. */
    case BlockedDate = 'blocked_date';

    /** Posti esauriti in almeno un giorno. */
    case CapacityFull = 'capacity_full';

    /** Servizio non attivo o con capacità 0. */
    case ServiceDisabled = 'service_disabled';
}
