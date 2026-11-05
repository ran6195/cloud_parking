<?php

namespace App\Engine\Domain\Booking;

use DateTimeImmutable;

/**
 * Cancellazione gratuita da parte del cliente:
 *
 * - possibile fino a N ore prima della consegna, estremo incluso (con 24 ore: a 24h01 sì, a 23h59 no);
 * - solo per prenotazioni in attesa di pagamento, confermate o pagate;
 * - se pagata online serve un rimborso, e quindi i dati di chi ha pagato.
 */
final readonly class CancellationPolicy
{
    public function __construct(private int $hoursBeforeStart) {}

    public function decide(ReservationStatus $status, DateTimeImmutable $start, bool $paidOnline, DateTimeImmutable $now): CancellationDecision
    {
        $deadline = $start->modify("-{$this->hoursBeforeStart} hours");

        $reason = match (true) {
            $status === ReservationStatus::Cancelled => CancellationReason::AlreadyCancelled,
            ! $status->canTransitionTo(ReservationStatus::Cancelled) => CancellationReason::NotCancellableStatus,
            $now > $deadline => CancellationReason::WindowClosed,
            default => null,
        };

        return new CancellationDecision($reason === null, $reason, $deadline, $paidOnline);
    }
}
