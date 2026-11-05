<?php

namespace App\Engine\Application\Exceptions;

use App\Engine\Domain\Shared\EngineException;
use App\Engine\Models\Quote;

/**
 * Prezzo o sconto cambiati dal preventivo: $newQuote è il preventivo aggiornato da mostrare al cliente (409).
 */
final class QuoteMismatch extends EngineException
{
    public function __construct(string $message, public readonly Quote $newQuote)
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'quote_mismatch';
    }
}
