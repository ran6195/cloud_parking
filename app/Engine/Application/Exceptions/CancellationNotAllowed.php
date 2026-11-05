<?php

namespace App\Engine\Application\Exceptions;

use App\Engine\Domain\Booking\CancellationReason;
use App\Engine\Domain\Shared\EngineException;
use DateTimeImmutable;
use DateTimeZone;

/**
 * La prenotazione non si può annullare (409): già annullata, fuori tempo o in uno stato finale.
 */
final class CancellationNotAllowed extends EngineException
{
    private function __construct(string $message, public readonly CancellationReason $reason)
    {
        parent::__construct($message);
    }

    public static function because(CancellationReason $reason, DateTimeImmutable $deadline): self
    {
        $local = $deadline->setTimezone(new DateTimeZone('Europe/Rome'));

        return new self(match ($reason) {
            CancellationReason::WindowClosed => 'La cancellazione gratuita era possibile fino al '.$local->format('d/m/Y').' alle '.$local->format('H:i').'.',
            CancellationReason::AlreadyCancelled => 'La prenotazione è già stata annullata.',
            CancellationReason::NotCancellableStatus => 'La prenotazione non si può più annullare: è scaduta o conclusa.',
        }, $reason);
    }

    public function errorCode(): string
    {
        return $this->reason->value;
    }
}
