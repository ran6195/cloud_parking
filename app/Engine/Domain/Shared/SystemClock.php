<?php

namespace App\Engine\Domain\Shared;

use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemClock implements Clock
{
    public function __construct(private DateTimeZone $timezone) {}

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->timezone);
    }
}
