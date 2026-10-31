<?php

namespace App\Engine\Domain\Pricing;

use App\Engine\Domain\Pricing\Exceptions\InvalidStay;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Periodo di sosta: dalla consegna al ritiro dell'auto, espresso nel fuso del parcheggio.
 */
final readonly class StayPeriod
{
    public const TIMEZONE = 'Europe/Rome';

    public const MAX_DAYS = 365;

    public DateTimeImmutable $start;

    public DateTimeImmutable $end;

    public function __construct(DateTimeImmutable $start, DateTimeImmutable $end)
    {
        $timezone = new DateTimeZone(self::TIMEZONE);
        $start = $start->setTimezone($timezone);
        $end = $end->setTimezone($timezone);

        if ($end <= $start) {
            throw InvalidStay::endNotAfterStart();
        }

        if ($start->modify('+'.self::MAX_DAYS.' days') < $end) {
            throw InvalidStay::tooLong(self::MAX_DAYS);
        }

        $this->start = $start;
        $this->end = $end;
    }

    public static function fromStrings(string $start, string $end): self
    {
        $timezone = new DateTimeZone(self::TIMEZONE);

        return new self(new DateTimeImmutable($start, $timezone), new DateTimeImmutable($end, $timezone));
    }

    /**
     * Minuti "da orologio": 08:00 → 08:00 del giorno dopo vale sempre 1440,
     * anche a cavallo del cambio dell'ora legale.
     */
    public function wallClockMinutes(): int
    {
        $utc = new DateTimeZone('UTC');
        $start = new DateTimeImmutable($this->start->format('Y-m-d H:i:s'), $utc);
        $end = new DateTimeImmutable($this->end->format('Y-m-d H:i:s'), $utc);

        return intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
    }

    /**
     * Data (locale) di inizio sosta, usata per scegliere il listino.
     */
    public function startDate(): DateTimeImmutable
    {
        return $this->start->setTime(0, 0);
    }
}
