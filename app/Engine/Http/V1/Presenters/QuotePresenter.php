<?php

namespace App\Engine\Http\V1\Presenters;

use App\Engine\Application\QuoteSnapshot;
use App\Engine\Models\Quote;
use Carbon\CarbonImmutable;

/**
 * Preventivo nel formato della spec (schema Quote).
 */
final class QuotePresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Quote $quote, CarbonImmutable $now): array
    {
        return [
            'id' => $quote->id,
            'status' => self::status($quote, $now),
            'expires_at' => QuoteSnapshot::dateTime($quote->expires_at->toDateTimeImmutable()),
            'stay' => self::stay($quote->stay),
            'extras' => $quote->extras,
            'payment_method' => $quote->payment_method->value,
            'lines' => array_map(self::line(...), $quote->lines),
            'subtotal' => MoneyJson::cents($quote->subtotal_cents),
            'discount' => self::discount($quote->discount),
            'total' => MoneyJson::cents($quote->total_cents),
            'bookable' => $quote->bookable,
            'availability' => self::availability($quote->availability),
            'cancellation' => [
                'free_until' => QuoteSnapshot::dateTime($quote->cancellation_free_until->toDateTimeImmutable()),
            ],
        ];
    }

    public static function status(Quote $quote, CarbonImmutable $now): string
    {
        return match (true) {
            $quote->used_at !== null => 'used',
            $quote->isExpired($now) => 'expired',
            default => 'active',
        };
    }

    /**
     * Il jsonb di Postgres non conserva l'ordine delle chiavi: qui lo si ricostruisce.
     *
     * @param  array<string, mixed>  $stay
     * @return array<string, mixed>
     */
    public static function stay(array $stay): array
    {
        return [
            'start' => $stay['start'],
            'end' => $stay['end'],
            'duration_minutes' => $stay['duration_minutes'],
            'billable_days' => $stay['billable_days'],
            'days_after_30' => $stay['days_after_30'],
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public static function line(array $line): array
    {
        return [
            'type' => $line['type'],
            'code' => $line['code'],
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'unit_price' => MoneyJson::cents(isset($line['unit_cents']) ? (int) $line['unit_cents'] : null),
            'total' => MoneyJson::cents((int) $line['total_cents']),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $discount
     * @return array<string, mixed>|null
     */
    public static function discount(?array $discount): ?array
    {
        if ($discount === null) {
            return null;
        }

        $amount = $discount['amount_cents'] ?? null;

        return [
            'code' => $discount['code'],
            'applied' => $discount['applied'],
            'reason' => $discount['reason'],
            'type' => $discount['type'],
            'percentage' => $discount['percentage'],
            'amount' => MoneyJson::cents($amount === null ? null : (int) $amount),
        ];
    }

    /**
     * Gli oggetti vuoti restano oggetti JSON ({}), non liste ([]).
     *
     * @param  array<string, mixed>  $availability
     * @return array<string, mixed>
     */
    public static function availability(array $availability): array
    {
        /** @var array<string, array<string, mixed>> $extras */
        $extras = $availability['extras'] ?? [];
        /** @var array<string, mixed> $parking */
        $parking = $availability['parking'];

        return [
            'available' => $availability['available'],
            'parking' => self::availabilityItem($parking),
            'extras' => $extras === [] ? new \stdClass : array_map(self::availabilityItem(...), $extras),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function availabilityItem(array $item): array
    {
        return [
            'available' => $item['available'],
            'reason' => $item['reason'],
            'unavailable_dates' => $item['unavailable_dates'],
        ];
    }
}
