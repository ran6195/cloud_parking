<?php

namespace App\Engine\Domain\Shared;

use DateTimeImmutable;

/**
 * Orologio fermo, per test e simulazioni.
 */
final readonly class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now) {}

    public static function at(string $dateTime): self
    {
        return new self(new DateTimeImmutable($dateTime));
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
