<?php

namespace App\Engine\Domain\Receipts;

/**
 * Genera la ricevuta di una prenotazione e ne restituisce il percorso sul disco delle ricevute.
 */
interface ReceiptGenerator
{
    public function generate(string $reservationId): string;
}
