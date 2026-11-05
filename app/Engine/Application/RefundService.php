<?php

namespace App\Engine\Application;

use App\Engine\Domain\Payments\GatewayCapture;
use App\Engine\Domain\Payments\PaymentGateway;
use App\Engine\Domain\Payments\PaymentProviderError;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Domain\Shared\Money;
use App\Engine\Models\PaymentTransaction;
use App\Engine\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Rimborsi delle prenotazioni pagate online e annullate. Stato in reservations.refund:
 *
 * - requested: registrato, da rimborsare (modalità manuale, la predefinita);
 * - refunded: rimborsato, su PayPal (automatico) o segnato a mano dal gestore;
 * - failed: rimborso automatico non riuscito, da completare a mano.
 */
final readonly class RefundService
{
    public const REQUESTED = 'requested';

    public const REFUNDED = 'refunded';

    public const FAILED = 'failed';

    public function __construct(private PaymentGateway $gateway, private Clock $clock) {}

    /**
     * Prima registrazione, al momento della cancellazione. Senza codice fiscale se annulla il gestore.
     */
    public function request(Reservation $reservation, ?string $taxCode, ?string $notes): void
    {
        $reservation->refund = [
            'status' => self::REQUESTED,
            'amount_cents' => $reservation->total_cents,
            'tax_code' => $taxCode,
            'notes' => $notes,
            'requested_at' => $this->now()->format(DATE_ATOM),
            'refunded_at' => null,
            'mode' => null,
            'provider_refund_id' => null,
            'error' => null,
        ];
    }

    /**
     * Rimborso su PayPal dell'incasso della prenotazione. Un errore non si propaga: diventa "failed".
     */
    public function refundAutomatically(Reservation $reservation): void
    {
        $this->ensurePending($reservation);
        $transaction = $this->completedTransaction($reservation);

        if ($transaction === null || $transaction->capture_id === null) {
            $this->fail($reservation, 'Nessun incasso PayPal trovato per la prenotazione.');

            return;
        }

        try {
            $refund = $this->gateway->refundCapture(
                $transaction->capture_id,
                Money::cents((int) ($reservation->refund['amount_cents'] ?? $reservation->total_cents)),
                "Rimborso prenotazione n. {$reservation->number}",
            );
        } catch (PaymentProviderError $e) {
            $this->fail($reservation, $e->getMessage());

            return;
        }

        if (! $refund->succeeded()) {
            $this->fail($reservation, "PayPal ha rifiutato il rimborso ({$refund->status}).");

            return;
        }

        $transaction->forceFill([
            'status' => 'REFUNDED',
            'payload' => ($transaction->payload ?? []) + ['refund' => $refund->payload],
        ])->save();

        $this->complete($reservation, 'automatic', $refund->id);
    }

    /**
     * Il gestore ha rimborsato fuori dal motore (es. dal pannello PayPal).
     */
    public function markRefunded(Reservation $reservation, ?string $note = null): void
    {
        $this->ensurePending($reservation);
        $this->complete($reservation, 'manual', null, $note);
    }

    public static function status(Reservation $reservation): ?string
    {
        $status = $reservation->refund['status'] ?? null;

        return is_string($status) ? $status : null;
    }

    private function complete(Reservation $reservation, string $mode, ?string $providerId, ?string $note = null): void
    {
        $reservation->refund = array_merge($reservation->refund ?? [], [
            'status' => self::REFUNDED,
            'mode' => $mode,
            'refunded_at' => $this->now()->format(DATE_ATOM),
            'provider_refund_id' => $providerId,
            'error' => null,
        ], $note === null ? [] : ['manager_note' => $note]);
        $reservation->save();
    }

    private function fail(Reservation $reservation, string $error): void
    {
        Log::error('Rimborso automatico non riuscito: va completato a mano', ['reservation' => $reservation->number, 'errore' => $error]);

        $reservation->refund = array_merge($reservation->refund ?? [], ['status' => self::FAILED, 'mode' => 'automatic', 'error' => $error]);
        $reservation->save();
    }

    private function ensurePending(Reservation $reservation): void
    {
        if (! in_array(self::status($reservation), [self::REQUESTED, self::FAILED], true)) {
            throw new LogicException("La prenotazione n. {$reservation->number} non ha un rimborso da eseguire.");
        }
    }

    private function completedTransaction(Reservation $reservation): ?PaymentTransaction
    {
        return $reservation->paymentTransactions()
            ->where('status', GatewayCapture::COMPLETED)
            ->whereNotNull('capture_id')
            ->latest('id')
            ->first();
    }

    private function now(): CarbonImmutable
    {
        return CarbonImmutable::instance($this->clock->now());
    }
}
