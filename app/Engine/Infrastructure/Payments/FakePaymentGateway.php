<?php

namespace App\Engine\Infrastructure\Payments;

use App\Engine\Domain\Payments\GatewayCapture;
use App\Engine\Domain\Payments\GatewayOrder;
use App\Engine\Domain\Payments\PaymentGateway;
use App\Engine\Domain\Payments\PaymentProviderError;
use App\Engine\Domain\Shared\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * PayPal simulato, per sviluppo e test (ENGINE_PAYMENTS_DRIVER=fake). Mai in produzione.
 *
 * Gli ordini stanno in cache, quindi sopravvivono tra una richiesta e l'altra.
 * Di default sono già approvati, così il flusso si prova senza la finestra di PayPal.
 * I test possono chiedere l'approvazione esplicita, simulare un guasto o un importo diverso.
 * Le notifiche sono "firmate" con un HMAC del corpo (header paypal-transmission-sig).
 */
final class FakePaymentGateway implements PaymentGateway
{
    private const CACHE_KEY = 'engine.fake_paypal';

    public function provider(): string
    {
        return 'paypal';
    }

    public function createOrder(string $reference, Money $amount, string $description): GatewayOrder
    {
        $this->failIfRequested();

        $id = 'FAKE'.strtoupper(Str::random(13));
        $this->put($id, [
            'status' => $this->state('require_approval') ? 'CREATED' : 'APPROVED',
            'reference' => $reference,
            'amount_cents' => $amount->cents,
            'capture_id' => null,
        ]);

        return new GatewayOrder($id, 'CREATED', $amount, ['id' => $id, 'reference_id' => $reference]);
    }

    public function captureOrder(string $orderId): GatewayCapture
    {
        $this->failIfRequested();
        $order = $this->order($orderId);

        if ($order === null || $order['status'] === 'CREATED') {
            return new GatewayCapture($orderId, GatewayCapture::NOT_APPROVED, null, null, null, ['id' => $orderId]);
        }

        if ($order['capture_id'] === null) {
            $order['status'] = 'COMPLETED';
            $order['capture_id'] = 'FAKECAP'.strtoupper(Str::random(10));
            $order['amount_cents'] = $order['captured_cents'] ?? $order['amount_cents'];
            $this->put($orderId, $order);
        }

        return new GatewayCapture(
            $orderId,
            GatewayCapture::COMPLETED,
            $order['capture_id'],
            Money::cents($order['amount_cents']),
            Money::CURRENCY,
            ['id' => $orderId, 'status' => 'COMPLETED'],
        );
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        return hash_equals(self::signature($body), $headers['paypal-transmission-sig'] ?? '');
    }

    public static function signature(string $body): string
    {
        return hash_hmac('sha256', $body, 'fake-paypal-webhook');
    }

    // Controlli per i test ---------------------------------------------------

    public function requireApproval(bool $required = true): void
    {
        $this->setState('require_approval', $required);
    }

    public function approve(string $orderId): void
    {
        $order = $this->order($orderId) ?? [];
        $this->put($orderId, ['status' => 'APPROVED'] + $order);
    }

    public function failNextCall(): void
    {
        $this->setState('fail_next', true);
    }

    /** Fa incassare un importo diverso da quello dell'ordine. */
    public function captureAmount(string $orderId, int $cents): void
    {
        $this->put($orderId, ['captured_cents' => $cents] + ($this->order($orderId) ?? []));
    }

    // -------------------------------------------------------------------------

    private function failIfRequested(): void
    {
        if ($this->state('fail_next')) {
            $this->setState('fail_next', false);

            throw PaymentProviderError::because('errore simulato');
        }
    }

    /**
     * @return array{status: string, reference: string, amount_cents: int, capture_id: string|null, captured_cents?: int}|null
     */
    private function order(string $id): ?array
    {
        /** @var array{status: string, reference: string, amount_cents: int, capture_id: string|null, captured_cents?: int}|null */
        return $this->data()['orders'][$id] ?? null;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    private function put(string $id, array $order): void
    {
        $data = $this->data();
        $data['orders'][$id] = $order;
        Cache::forever(self::CACHE_KEY, $data);
    }

    private function state(string $flag): bool
    {
        return (bool) ($this->data()['state'][$flag] ?? false);
    }

    private function setState(string $flag, bool $value): void
    {
        $data = $this->data();
        $data['state'][$flag] = $value;
        Cache::forever(self::CACHE_KEY, $data);
    }

    /**
     * @return array{orders: array<string, array<string, mixed>>, state: array<string, bool>}
     */
    private function data(): array
    {
        /** @var array{orders: array<string, array<string, mixed>>, state: array<string, bool>} */
        return Cache::get(self::CACHE_KEY, ['orders' => [], 'state' => []]);
    }
}
