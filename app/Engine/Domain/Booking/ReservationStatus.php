<?php

namespace App\Engine\Domain\Booking;

use DateTimeImmutable;

/**
 * Stati della prenotazione (vedi ReservationStatus nella spec OpenAPI).
 * Le transizioni consentite arrivano con la fase 6.
 */
enum ReservationStatus: string
{
    /** Creata, in attesa del pagamento online. */
    case PendingPayment = 'pending_payment';

    /** Confermata, da pagare in parcheggio. */
    case Confirmed = 'confirmed';

    /** Pagata online. */
    case Paid = 'paid';

    /** Sosta conclusa. */
    case Completed = 'completed';

    case Cancelled = 'cancelled';

    /** Pagamento online non completato in tempo. */
    case Expired = 'expired';

    /**
     * Prenotazioni "valide": contano per gli utilizzi dei codici sconto.
     *
     * @return list<self>
     */
    public static function valid(): array
    {
        return [self::Confirmed, self::Paid, self::Completed];
    }

    public function isValid(): bool
    {
        return in_array($this, self::valid(), true);
    }

    /**
     * Se la prenotazione occupa un posto: le prenotazioni valide sempre, quelle
     * in attesa del pagamento online solo fino al termine di pagamento.
     */
    public function holdsCapacity(?DateTimeImmutable $paymentDueBy, DateTimeImmutable $now): bool
    {
        if ($this === self::PendingPayment) {
            return $paymentDueBy !== null && $paymentDueBy > $now;
        }

        return $this->isValid();
    }
}
