<?php

namespace App\Engine\Application;

use App\Engine\Application\Exceptions\CancellationNotAllowed;
use App\Engine\Domain\Booking\CancellationDecision;
use App\Engine\Domain\Booking\CancellationPolicy;
use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Domain\Shared\Clock;
use App\Engine\EngineServiceProvider;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cancellazione da parte del cliente, con il token ricevuto per email (vedi docs/cancellations.md).
 */
final readonly class CancellationService
{
    public function __construct(
        private RefundService $refunds,
        private ReservationNotifier $notifier,
        private EngineSettings $settings,
        private Clock $clock,
    ) {}

    public function find(string $token): Reservation
    {
        return Reservation::query()->where('cancellation_token', $token)->firstOrFail();
    }

    public function decide(Reservation $reservation): CancellationDecision
    {
        return (new CancellationPolicy($this->settings->cancellationHours()))->decide(
            $reservation->status,
            $reservation->starts_at->toDateTimeImmutable(),
            self::paidOnline($reservation),
            $this->clock->now(),
        );
    }

    /**
     * @param  array{tax_code: string, notes?: string|null}|null  $refund
     * @return array{reservation: Reservation, email_sent: bool}
     */
    public function cancel(string $token, ?string $reason, ?array $refund): array
    {
        $reservation = DB::connection(EngineServiceProvider::CONNECTION)->transaction(function () use ($token, $reason, $refund): Reservation {
            $reservation = Reservation::query()->where('cancellation_token', $token)->lockForUpdate()->firstOrFail();
            $decision = $this->decide($reservation);

            if (! $decision->cancellable && $decision->reason !== null) {
                throw CancellationNotAllowed::because($decision->reason, $decision->deadline);
            }

            if ($decision->refundRequired && ($refund['tax_code'] ?? '') === '') {
                throw ValidationException::withMessages([
                    'refund.tax_code' => 'Per il rimborso serve il codice fiscale di chi ha pagato.',
                ]);
            }

            if ($decision->refundRequired && $refund !== null) {
                $this->refunds->request($reservation, $refund['tax_code'], $refund['notes'] ?? null);
            }

            $reservation->cancelled_at = CarbonImmutable::instance($this->clock->now());
            $reservation->cancellation_reason = $reason;
            $reservation->transitionTo(ReservationStatus::Cancelled);

            return $reservation;
        });

        if (RefundService::status($reservation) === RefundService::REQUESTED && $this->settings->automaticRefunds()) {
            $this->refunds->refundAutomatically($reservation);
        }

        return ['reservation' => $reservation->refresh(), 'email_sent' => $this->notifier->cancelled($reservation)];
    }

    private static function paidOnline(Reservation $reservation): bool
    {
        return $reservation->payment_method === PaymentMethod::Online && $reservation->paid_at !== null && $reservation->total_cents > 0;
    }
}
