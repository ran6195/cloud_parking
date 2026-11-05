<?php

namespace App\Engine\Http\V1\Presenters;

use App\Engine\Application\QuoteSnapshot;
use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Domain\Pricing\StayPeriod;
use App\Engine\Models\Reservation;
use App\Engine\Models\ReservationLine;
use Carbon\CarbonImmutable;

/**
 * Prenotazione nel formato della spec (schema Reservation).
 */
final class ReservationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(Reservation $reservation, int $cancellationHours): array
    {
        $reservation->loadMissing(['customer', 'lines']);
        $stay = new StayPeriod($reservation->starts_at->toDateTimeImmutable(), $reservation->ends_at->toDateTimeImmutable());

        return [
            'id' => $reservation->id,
            'number' => $reservation->number,
            'status' => $reservation->status->value,
            'channel' => $reservation->channel,
            'created_at' => self::dateTime($reservation->created_at),
            'stay' => [
                'start' => QuoteSnapshot::dateTime($stay->start),
                'end' => QuoteSnapshot::dateTime($stay->end),
                'duration_minutes' => $stay->wallClockMinutes(),
                'billable_days' => $reservation->billable_days,
                'days_after_30' => max(0, $reservation->billable_days - 30),
            ],
            'lines' => $reservation->lines->map(fn (ReservationLine $line) => [
                'type' => $line->type->value,
                'code' => $line->code,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => MoneyJson::cents($line->unit_cents),
                'total' => MoneyJson::cents($line->total_cents),
            ])->all(),
            'subtotal' => MoneyJson::cents($reservation->subtotal_cents),
            'discount' => QuotePresenter::discount($reservation->discount),
            'total' => MoneyJson::cents($reservation->total_cents),
            'customer' => [
                'first_name' => $reservation->owner()->first_name,
                'last_name' => $reservation->owner()->last_name,
                'email' => $reservation->owner()->email,
                'phone' => $reservation->owner()->phone,
            ],
            'vehicle' => [
                'plate' => $reservation->plate,
                'make' => $reservation->vehicle_make,
                'model' => $reservation->vehicle_model,
            ],
            'travel' => [
                'outbound_flight' => $reservation->outbound_flight,
                'return_flight' => $reservation->return_flight,
                'passengers' => $reservation->passengers,
            ],
            'payment' => [
                'method' => $reservation->payment_method->value,
                'status' => self::paymentStatus($reservation),
                'amount' => MoneyJson::cents($reservation->total_cents),
                'due_by' => self::dateTime($reservation->payment_due_by),
                'paid_at' => self::dateTime($reservation->paid_at),
            ],
            'cancellation' => [
                'free_until' => self::dateTime($reservation->starts_at->subHours($cancellationHours)),
                'cancelled_at' => self::dateTime($reservation->cancelled_at),
            ],
            'notifications' => ['email_sent' => $reservation->email_sent],
        ];
    }

    /**
     * pending (in attesa online), paid, due_on_site (da pagare in parcheggio), none (annullata o scaduta senza pagamento).
     * "refunded" arriverà con i rimborsi.
     */
    public static function paymentStatus(Reservation $reservation): string
    {
        if ($reservation->paid_at !== null) {
            return 'paid';
        }

        return match ($reservation->status) {
            ReservationStatus::PendingPayment => 'pending',
            ReservationStatus::Paid => 'paid',
            ReservationStatus::Confirmed => 'due_on_site',
            ReservationStatus::Completed => $reservation->payment_method === PaymentMethod::OnSite ? 'paid' : 'none',
            ReservationStatus::Cancelled, ReservationStatus::Expired => 'none',
        };
    }

    private static function dateTime(?CarbonImmutable $instant): ?string
    {
        return $instant === null ? null : QuoteSnapshot::dateTime($instant->toDateTimeImmutable());
    }
}
