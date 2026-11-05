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
 * Avviso al gestore di una nuova prenotazione.
 */
final class ReservationReceived extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Nuova prenotazione n. {$this->reservation->number} ({$this->reservation->channel})");
    }

    public function content(): Content
    {
        return new Content(markdown: 'engine::mail.reservation-received');
    }
}
