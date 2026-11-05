<?php

namespace App\Engine\Domain\Booking;

use DateTimeImmutable;

final readonly class CancellationDecision
{
    public function __construct(
        public bool $cancellable,
        public ?CancellationReason $reason,
        public DateTimeImmutable $deadline,
        public bool $refundRequired,
    ) {}
}
