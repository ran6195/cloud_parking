<?php

namespace App\Engine\Application\Exceptions;

use App\Engine\Domain\Shared\EngineException;

/**
 * L'ordine non risulta pagato per l'importo della prenotazione (422).
 */
final class PaymentNotVerified extends EngineException
{
    public function errorCode(): string
    {
        return 'payment_not_verified';
    }
}
