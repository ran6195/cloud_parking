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
 * Conferma al cliente, con il riepilogo e il link per annullare.
 */
final class ReservationConfirmed extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public string $brand,
        public string $phone,
        public ?string $cancellationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Prenotazione n. {$this->reservation->number} confermata · {$this->brand}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'engine::mail.reservation-confirmed');
    }
}
