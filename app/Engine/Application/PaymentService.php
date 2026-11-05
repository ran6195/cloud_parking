<?php

namespace App\Engine\Application;

use App\Engine\Application\Exceptions\PaymentNotVerified;
use App\Engine\Application\Exceptions\ReservationNotPayable;
use App\Engine\Domain\Availability\AvailabilityChecker;
use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Payments\GatewayCapture;
use App\Engine\Domain\Payments\PaymentGateway;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Domain\Pricing\StayPeriod;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Domain\Shared\Money;
use App\Engine\EngineServiceProvider;
use App\Engine\Models\PaymentTransaction;
use App\Engine\Models\Reservation;
use App\Engine\Models\ReservationLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pagamento online delle prenotazioni (vedi docs/payments.md).
 *
 * - L'ordine lo crea il server con il totale della prenotazione: il client non decide mai l'importo.
 * - L'incasso è verificato lato server: stato COMPLETED, euro, importo identico al totale.
 * - Pagato in ritardo (oltre payment_due_by): si accetta solo se il posto è ancora libero,
 *   controllato con lo stesso lock della creazione delle prenotazioni.
 */
final readonly class PaymentService
{
    /** Un ordine PayPal non approvato scade dopo circa 3 ore: oltre non lo si riusa. */
    private const ORDER_REUSE_HOURS = 3;

    public function __construct(
        private PaymentGateway $gateway,
        private AvailabilityChecker $availability,
        private ReservationNotifier $notifier,
        private Clock $clock,
    ) {}

    public function createOrder(Reservation $reservation): PaymentTransaction
    {
        return DB::connection(EngineServiceProvider::CONNECTION)->transaction(function () use ($reservation): PaymentTransaction {
            $reservation = $this->lock($reservation);
            $this->ensureOrderable($reservation);

            $existing = $reservation->paymentTransactions()
                ->where('provider', $this->gateway->provider())
                ->whereIn('status', ['CREATED', 'APPROVED'])
                ->where('amount_cents', $reservation->total_cents)
                ->where('created_at', '>', $this->now()->subHours(self::ORDER_REUSE_HOURS)->format(DATE_ATOM))
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $order = $this->gateway->createOrder(
                $reservation->id,
                Money::cents($reservation->total_cents),
                "Prenotazione n. {$reservation->number}",
            );

            return $reservation->paymentTransactions()->create([
                'provider' => $this->gateway->provider(),
                'order_id' => $order->id,
                'status' => 'CREATED',
                'amount_cents' => $reservation->total_cents,
                'currency' => Money::CURRENCY,
                'payload' => ['order' => $order->payload],
            ]);
        });
    }

    /**
     * Incassa l'ordine approvato dal cliente. Ripetuta su un ordine già incassato, restituisce la prenotazione pagata.
     */
    public function capture(Reservation $reservation, string $orderId): Reservation
    {
        $transaction = $this->transactionFor($reservation, $orderId)
            ?? throw new ReservationNotPayable('L\'ordine non appartiene a questa prenotazione.');

        if ($transaction->status === GatewayCapture::COMPLETED && $reservation->paid_at !== null) {
            return $reservation;
        }

        $result = DB::connection(EngineServiceProvider::CONNECTION)->transaction(function () use ($reservation, $transaction): array {
            $reservation = $this->lock($reservation);

            if ($reservation->paid_at !== null) {
                return ['reservation' => $reservation, 'paid' => false, 'error' => null];
            }

            $this->ensureCapturable($reservation);
            $capture = $this->gateway->captureOrder($transaction->order_id);

            return $this->settle($reservation, $transaction, $capture);
        });

        return $this->finish($result);
    }

    /**
     * Notifica di PayPal (già verificata): rete di sicurezza se il client si chiude prima della cattura.
     *
     * @param  array<string, mixed>  $event
     */
    public function handleWebhook(array $event): void
    {
        $type = $event['event_type'] ?? null;
        /** @var array<string, mixed> $resource */
        $resource = is_array($event['resource'] ?? null) ? $event['resource'] : [];

        $orderId = match ($type) {
            'CHECKOUT.ORDER.APPROVED' => $resource['id'] ?? null,
            'PAYMENT.CAPTURE.COMPLETED' => data_get($resource, 'supplementary_data.related_ids.order_id'),
            default => null,
        };

        $transaction = is_string($orderId)
            ? PaymentTransaction::query()->where('provider', $this->gateway->provider())->where('order_id', $orderId)->first()
            : null;
        $reservation = $transaction?->reservation;

        if ($transaction === null || $reservation === null || $reservation->paid_at !== null) {
            return;
        }

        if ($type === 'CHECKOUT.ORDER.APPROVED') {
            try {
                $this->capture($reservation, $transaction->order_id);
            } catch (ReservationNotPayable|PaymentNotVerified $e) {
                Log::warning('Webhook PayPal: ordine approvato ma non incassato', ['order_id' => $orderId, 'motivo' => $e->getMessage()]);
            }

            return;
        }

        // PAYMENT.CAPTURE.COMPLETED: i soldi sono già incassati, si registra comunque.
        $capture = new GatewayCapture(
            orderId: $transaction->order_id,
            status: (string) ($resource['status'] ?? ''),
            captureId: isset($resource['id']) ? (string) $resource['id'] : null,
            amount: is_scalar(data_get($resource, 'amount.value')) ? Money::fromDecimal((string) data_get($resource, 'amount.value')) : null,
            currency: is_string(data_get($resource, 'amount.currency_code')) ? data_get($resource, 'amount.currency_code') : null,
            payload: $resource,
        );

        $result = DB::connection(EngineServiceProvider::CONNECTION)->transaction(function () use ($reservation, $transaction, $capture): array {
            $reservation = $this->lock($reservation);

            if ($reservation->paid_at !== null) {
                return ['reservation' => $reservation, 'paid' => false, 'error' => null];
            }

            try {
                $this->ensureCapturable($reservation);
            } catch (ReservationNotPayable $e) {
                $this->record($transaction, $capture);
                Log::error('Pagamento PayPal incassato su una prenotazione non più pagabile: serve un rimborso', [
                    'reservation' => $reservation->number, 'order_id' => $transaction->order_id, 'motivo' => $e->getMessage(),
                ]);

                return ['reservation' => $reservation, 'paid' => false, 'error' => null];
            }

            return $this->settle($reservation, $transaction, $capture);
        });

        try {
            $this->finish($result);
        } catch (PaymentNotVerified) {
            // già registrato in settle()
        }
    }

    /**
     * Registra l'esito e, se il pagamento è verificato, segna la prenotazione come pagata.
     *
     * @return array{reservation: Reservation, paid: bool, error: PaymentNotVerified|null}
     */
    private function settle(Reservation $reservation, PaymentTransaction $transaction, GatewayCapture $capture): array
    {
        $this->record($transaction, $capture);

        if (! $capture->confirms(Money::cents($reservation->total_cents))) {
            if ($capture->status === GatewayCapture::COMPLETED) {
                Log::error('Pagamento PayPal incassato con un importo diverso dal totale', [
                    'reservation' => $reservation->number, 'order_id' => $transaction->order_id,
                    'atteso' => $reservation->total_cents, 'incassato' => $capture->amount?->cents, 'valuta' => $capture->currency,
                ]);
            }

            return ['reservation' => $reservation, 'paid' => false, 'error' => new PaymentNotVerified(match ($capture->status) {
                GatewayCapture::COMPLETED => 'L\'importo dell\'ordine PayPal non corrisponde a quello della prenotazione.',
                GatewayCapture::NOT_APPROVED => 'L\'ordine PayPal non è stato approvato dal cliente.',
                'PENDING' => 'Il pagamento PayPal è in attesa di conferma: la prenotazione sarà confermata appena arriva.',
                default => "Il pagamento PayPal non è andato a buon fine (stato {$capture->status}).",
            })];
        }

        $reservation->paid_at = $this->now();
        $reservation->transitionTo(ReservationStatus::Paid);

        return ['reservation' => $reservation, 'paid' => true, 'error' => null];
    }

    /**
     * @param  array{reservation: Reservation, paid: bool, error: PaymentNotVerified|null}  $result
     */
    private function finish(array $result): Reservation
    {
        if ($result['error'] !== null) {
            throw $result['error'];
        }

        if ($result['paid']) {
            $this->notifier->confirmed($result['reservation']);
        }

        return $result['reservation']->refresh();
    }

    private function record(PaymentTransaction $transaction, GatewayCapture $capture): void
    {
        $payload = $transaction->payload ?? [];
        $payload['capture'] = $capture->payload;

        $transaction->forceFill([
            'status' => $capture->status,
            'capture_id' => $capture->captureId ?? $transaction->capture_id,
            'payload' => $payload,
        ])->save();
    }

    private function ensureOrderable(Reservation $reservation): void
    {
        $this->ensureOnlinePending($reservation);

        if ($reservation->payment_due_by !== null && $reservation->payment_due_by->lessThanOrEqualTo($this->now())) {
            throw new ReservationNotPayable('Il tempo per pagare online è scaduto: la prenotazione non è più valida.');
        }
    }

    /**
     * Oltre il termine si incassa solo se il posto è ancora libero (la prenotazione scaduta non lo occupa più).
     */
    private function ensureCapturable(Reservation $reservation): void
    {
        $late = $reservation->status === ReservationStatus::Expired
            || ($reservation->payment_due_by !== null && $reservation->payment_due_by->lessThanOrEqualTo($this->now()));

        if (! $late) {
            $this->ensureOnlinePending($reservation);

            return;
        }

        if ($reservation->status !== ReservationStatus::Expired) {
            $this->ensureOnlinePending($reservation);
        }

        DB::connection(EngineServiceProvider::CONNECTION)->select('select pg_advisory_xact_lock(?)', [ReservationService::LOCK_KEY]);

        $extras = array_values(array_filter(array_map(
            fn (ReservationLine $line) => ExtraId::tryFrom($line->code),
            $reservation->lines->all(),
        )));
        $stay = new StayPeriod($reservation->starts_at->toDateTimeImmutable(), $reservation->ends_at->toDateTimeImmutable());

        if (! $this->availability->check($stay, $extras)->isAvailable()) {
            throw new ReservationNotPayable('Il tempo per pagare è scaduto e le date non sono più disponibili.');
        }
    }

    private function ensureOnlinePending(Reservation $reservation): void
    {
        $reason = match (true) {
            $reservation->payment_method !== PaymentMethod::Online => 'La prenotazione è da pagare in parcheggio.',
            $reservation->paid_at !== null, $reservation->status === ReservationStatus::Paid => 'La prenotazione è già pagata.',
            $reservation->status === ReservationStatus::Expired => 'Il tempo per pagare online è scaduto: la prenotazione non è più valida.',
            $reservation->status !== ReservationStatus::PendingPayment => 'La prenotazione non è in attesa di pagamento.',
            default => null,
        };

        if ($reason !== null) {
            throw new ReservationNotPayable($reason);
        }
    }

    private function transactionFor(Reservation $reservation, string $orderId): ?PaymentTransaction
    {
        return $reservation->paymentTransactions()
            ->where('provider', $this->gateway->provider())
            ->where('order_id', $orderId)
            ->first();
    }

    private function lock(Reservation $reservation): Reservation
    {
        return Reservation::query()->whereKey($reservation->id)->lockForUpdate()->with('lines')->firstOrFail();
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->clock->now());
    }
}
