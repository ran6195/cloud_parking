<?php

namespace App\Engine\Mail;

use App\Engine\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Avviso al gestore: prenotazione annullata dal cliente, con la richiesta di rimborso se c'è.
 */
final class ReservationCancelledNotice extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        $refund = ($this->reservation->refund['status'] ?? null) !== null && $this->reservation->refund['status'] !== 'refunded'
            ? ' · RIMBORSO DA FARE'
            : '';

        return new Envelope(subject: "Prenotazione n. {$this->reservation->number} annullata dal cliente{$refund}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'engine::mail.reservation-cancelled-notice');
    }
}
