<?php

namespace App\Engine\Domain\Booking;

/**
 * Perché una prenotazione non si può annullare. Il valore è anche il "code" dell'errore 409.
 */
enum CancellationReason: string
{
    case WindowClosed = 'cancellation_window_closed';
    case AlreadyCancelled = 'already_cancelled';
    case NotCancellableStatus = 'not_cancellable_status';
}
