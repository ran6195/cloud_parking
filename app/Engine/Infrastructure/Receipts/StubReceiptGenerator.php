<?php

namespace App\Engine\Infrastructure\Receipts;

use App\Engine\Domain\Receipts\ReceiptGenerator;
use App\Engine\Models\Reservation;
use Illuminate\Support\Facades\Storage;

/**
 * Ricevuta provvisoria in HTML, finché non c'è il modello definitivo (PDF o fattura elettronica).
 * Il disco è config("engine.receipts_disk"): in locale "local", su Cloud l'Object Storage.
 */
final class StubReceiptGenerator implements ReceiptGenerator
{
    public function generate(string $reservationId): string
    {
        $reservation = Reservation::query()->with(['customer', 'lines'])->findOrFail($reservationId);
        $path = sprintf('engine/receipts/%s/%d.html', $reservation->created_at->format('Y'), $reservation->number);

        Storage::disk((string) config('engine.receipts_disk'))->put($path, view('engine::receipts.stub', [
            'reservation' => $reservation,
        ])->render());

        return $path;
    }
}
