<?php

namespace App\Engine\Infrastructure\Payments;

use App\Engine\Domain\Payments\GatewayCapture;
use App\Engine\Domain\Payments\GatewayOrder;
use App\Engine\Domain\Payments\PaymentGateway;
use App\Engine\Domain\Payments\PaymentProviderError;
use App\Engine\Domain\Shared\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal REST API: Orders v2 con intent CAPTURE e verifica della firma dei webhook.
 * Configurazione in config("engine.payments.paypal"): mode (sandbox|live), client_id, secret, webhook_id.
 */
final readonly class PayPalGateway implements PaymentGateway
{
    private const TOKEN_CACHE_KEY = 'engine.paypal.token';

    public function __construct(
        private string $clientId,
        private string $secret,
        private ?string $webhookId,
        private string $baseUrl,
        private int $timeoutSeconds = 15,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array{mode: string, client_id: string|null, secret: string|null, webhook_id: string|null} $config */
        $config = config('engine.payments.paypal');

        return new self(
            (string) $config['client_id'],
            (string) $config['secret'],
            $config['webhook_id'],
            $config['mode'] === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com',
        );
    }

    public function provider(): string
    {
        return 'paypal';
    }

    public function createOrder(string $reference, Money $amount, string $description): GatewayOrder
    {
        $response = $this->send(fn (PendingRequest $http) => $http
            ->withHeaders(['PayPal-Request-Id' => 'order-'.$reference.'-'.$amount->cents])
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $reference,
                    'description' => mb_substr($description, 0, 127),
                    'amount' => ['currency_code' => Money::CURRENCY, 'value' => $amount->toDecimalString()],
                ]],
            ]));

        if (! $response->successful()) {
            throw PaymentProviderError::because('creazione ordine: HTTP '.$response->status());
        }

        return new GatewayOrder((string) $response->json('id'), (string) $response->json('status'), $amount, (array) $response->json());
    }

    public function captureOrder(string $orderId): GatewayCapture
    {
        $response = $this->send(fn (PendingRequest $http) => $http
            ->withHeaders(['PayPal-Request-Id' => 'capture-'.$orderId])
            ->withBody('{}')
            ->post('/v2/checkout/orders/'.rawurlencode($orderId).'/capture'));

        if ($response->status() === 422 && $this->issue($response) === 'ORDER_ALREADY_CAPTURED') {
            $response = $this->send(fn (PendingRequest $http) => $http->get('/v2/checkout/orders/'.rawurlencode($orderId)));
        } elseif ($response->status() === 422 || $response->status() === 404) {
            return new GatewayCapture($orderId, $this->issue($response) ?? GatewayCapture::NOT_APPROVED, null, null, null, (array) $response->json());
        }

        if (! $response->successful()) {
            throw PaymentProviderError::because('incasso ordine: HTTP '.$response->status());
        }

        /** @var array<string, mixed>|null $capture */
        $capture = $response->json('purchase_units.0.payments.captures.0');

        return new GatewayCapture(
            orderId: $orderId,
            status: (string) ($capture['status'] ?? $response->json('status')),
            captureId: isset($capture['id']) ? (string) $capture['id'] : null,
            amount: isset($capture['amount']['value']) ? Money::fromDecimal((string) $capture['amount']['value']) : null,
            currency: isset($capture['amount']['currency_code']) ? (string) $capture['amount']['currency_code'] : null,
            payload: (array) $response->json(),
        );
    }

    public function verifyWebhook(array $headers, string $body): bool
    {
        $event = json_decode($body, true);
        if ($this->webhookId === null || $this->webhookId === '' || ! is_array($event)) {
            return false;
        }

        $response = $this->send(fn (PendingRequest $http) => $http->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $headers['paypal-auth-algo'] ?? '',
            'cert_url' => $headers['paypal-cert-url'] ?? '',
            'transmission_id' => $headers['paypal-transmission-id'] ?? '',
            'transmission_sig' => $headers['paypal-transmission-sig'] ?? '',
            'transmission_time' => $headers['paypal-transmission-time'] ?? '',
            'webhook_id' => $this->webhookId,
            'webhook_event' => $event,
        ]));

        return $response->successful() && $response->json('verification_status') === 'SUCCESS';
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request(Http::baseUrl($this->baseUrl)
                ->timeout($this->timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->withToken($this->token()));
        } catch (ConnectionException $e) {
            throw PaymentProviderError::because('connessione non riuscita');
        }
    }

    private function token(): string
    {
        /** @var string */
        return Cache::remember(self::TOKEN_CACHE_KEY.'.'.md5($this->clientId), 3000, function (): string {
            try {
                $response = Http::baseUrl($this->baseUrl)
                    ->timeout($this->timeoutSeconds)
                    ->withBasicAuth($this->clientId, $this->secret)
                    ->asForm()
                    ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials']);
            } catch (ConnectionException) {
                throw PaymentProviderError::because('connessione non riuscita');
            }

            if (! $response->successful() || ! is_string($response->json('access_token'))) {
                throw PaymentProviderError::because('autenticazione: HTTP '.$response->status());
            }

            return $response->json('access_token');
        });
    }

    private function issue(Response $response): ?string
    {
        $issue = $response->json('details.0.issue') ?? $response->json('name');

        return is_string($issue) ? $issue : null;
    }
}
