<?php

namespace App\Engine\Application\Exceptions;

use App\Engine\Domain\Shared\EngineException;

/**
 * Il preventivo non si può più convertire in prenotazione (409).
 */
final class QuoteNotUsable extends EngineException
{
    private function __construct(string $message, private readonly string $code_)
    {
        parent::__construct($message);
    }

    public static function expired(): self
    {
        return new self('Il preventivo è scaduto: calcolane uno nuovo.', 'quote_expired');
    }

    public static function used(): self
    {
        return new self('Il preventivo è già stato usato per una prenotazione.', 'quote_used');
    }

    public function errorCode(): string
    {
        return $this->code_;
    }
}
