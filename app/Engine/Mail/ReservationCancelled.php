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
 * Conferma di cancellazione al cliente, con lo stato dell'eventuale rimborso.
 */
final class ReservationCancelled extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Reservation $reservation, public string $brand, public string $phone) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Prenotazione n. {$this->reservation->number} annullata · {$this->brand}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'engine::mail.reservation-cancelled');
    }
}
