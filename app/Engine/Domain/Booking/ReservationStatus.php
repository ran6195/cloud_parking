<?php

namespace App\Engine\Domain\Booking;

use DateTimeImmutable;

/**
 * Stati della prenotazione (vedi ReservationStatus nella spec OpenAPI).
 *
 *   pending_payment → paid | expired | cancelled
 *   confirmed       → completed | cancelled
 *   paid            → completed | cancelled
 *   expired         → paid (pagamento arrivato in ritardo, se il posto è ancora libero)
 *   completed, cancelled: finali
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
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Paid, self::Expired, self::Cancelled],
            self::Confirmed, self::Paid => [self::Completed, self::Cancelled],
            self::Expired => [self::Paid],
            self::Completed, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

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
