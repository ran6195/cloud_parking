<?php

namespace App\Engine\Jobs;

use App\Engine\Domain\Receipts\ReceiptGenerator;
use App\Engine\Models\Reservation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Genera la ricevuta in coda e ne salva il percorso sulla prenotazione.
 */
final class GenerateReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $reservationId) {}

    public function handle(ReceiptGenerator $receipts): void
    {
        $path = $receipts->generate($this->reservationId);

        Reservation::query()->whereKey($this->reservationId)->update(['receipt_path' => $path]);
    }
}
