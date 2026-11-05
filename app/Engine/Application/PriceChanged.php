<?php

namespace App\Engine\Application;

use App\Engine\Domain\Pricing\QuoteRequest;
use RuntimeException;

/**
 * Segnale interno di ReservationService: annulla la transazione, poi il
 * servizio salva un nuovo preventivo e lancia QuoteMismatch.
 *
 * @internal
 */
final class PriceChanged extends RuntimeException
{
    public function __construct(string $message, public readonly QuoteRequest $request)
    {
        parent::__construct($message);
    }
}
