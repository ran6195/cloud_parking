<?php

namespace App\Engine\Application;

use App\Engine\Domain\Availability\Availability;
use App\Engine\Domain\Availability\AvailabilityItem;
use App\Engine\Domain\Pricing\Discount\AppliedDiscount;
use App\Engine\Domain\Pricing\Quote;
use App\Engine\Domain\Pricing\QuoteLine;
use DateTimeImmutable;

/**
 * Forma salvata (jsonb) di preventivi e prenotazioni: importi in centesimi,
 * date ISO 8601 con offset di Europe/Rome.
 */
final class QuoteSnapshot
{
    public static function dateTime(DateTimeImmutable $instant): string
    {
        return $instant->setTimezone(new \DateTimeZone('Europe/Rome'))->format(DATE_ATOM);
    }

    /**
     * @return array{start: string, end: string, duration_minutes: int, billable_days: int, days_after_30: int}
     */
    public static function stay(Quote $quote): array
    {
        return [
            'start' => self::dateTime($quote->stay->start),
            'end' => self::dateTime($quote->stay->end),
            'duration_minutes' => $quote->duration->minutes,
            'billable_days' => $quote->duration->billableDays,
            'days_after_30' => $quote->duration->extraDays,
        ];
    }

    /**
     * @return list<array{type: string, code: string, description: string, quantity: int, unit_cents: int|null, total_cents: int}>
     */
    public static function lines(Quote $quote): array
    {
        return array_map(fn (QuoteLine $line) => [
            'type' => $line->type->value,
            'code' => $line->code,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_cents' => $line->unitPrice?->cents,
            'total_cents' => $line->total->cents,
        ], $quote->lines);
    }

    /**
     * @return array{code: string, applied: bool, reason: string|null, type: string|null, percentage: int|null, amount_cents: int|null}|null
     */
    public static function discount(?AppliedDiscount $discount): ?array
    {
        return $discount === null ? null : [
            'code' => $discount->code,
            'applied' => $discount->applied,
            'reason' => $discount->rejection?->value,
            'type' => $discount->type?->value,
            'percentage' => $discount->percentage,
            'amount_cents' => $discount->amount?->cents,
        ];
    }

    /**
     * @return array{price_table: string, requested_date: string, matched_date: string, fallback_years: int}
     */
    public static function trace(Quote $quote): array
    {
        return [
            'price_table' => $quote->trace->priceTable,
            'requested_date' => $quote->trace->requestedDate->format('Y-m-d'),
            'matched_date' => $quote->trace->matchedDate->format('Y-m-d'),
            'fallback_years' => $quote->trace->fallbackYears,
        ];
    }

    /**
     * @return array{available: bool, parking: array<string, mixed>, extras: array<string, array<string, mixed>>}
     */
    public static function availability(Availability $availability): array
    {
        return [
            'available' => $availability->isAvailable(),
            'parking' => self::availabilityItem($availability->parking),
            'extras' => array_map(self::availabilityItem(...), $availability->extras),
        ];
    }

    /**
     * @return array{available: bool, reason: string|null, unavailable_dates: list<string>}
     */
    private static function availabilityItem(AvailabilityItem $item): array
    {
        return [
            'available' => $item->available,
            'reason' => $item->reason?->value,
            'unavailable_dates' => $item->unavailableDates,
        ];
    }
}
