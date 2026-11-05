<?php

namespace App\Engine\Domain\Payments;

use App\Engine\Domain\Shared\EngineException;

/**
 * Il provider non risponde o risponde con un errore: la richiesta si può ritentare (502).
 */
final class PaymentProviderError extends EngineException
{
    public static function because(string $reason): self
    {
        return new self("PayPal non è raggiungibile in questo momento ({$reason}). Riprova fra poco.");
    }

    public function errorCode(): string
    {
        return 'payment_provider_error';
    }
}
