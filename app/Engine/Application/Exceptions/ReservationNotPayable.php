<?php

namespace App\Engine\Application\Exceptions;

use App\Engine\Domain\Shared\EngineException;

/**
 * La prenotazione non si può pagare online (409): già pagata, scaduta, annullata,
 * da pagare in parcheggio, oppure l'ordine non le appartiene.
 */
final class ReservationNotPayable extends EngineException
{
    public function errorCode(): string
    {
        return 'reservation_not_payable';
    }
}
