<?php

namespace App\Services\Engine;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Client HTTP dell'API del motore (/api/v1) per il CMS. Il CMS non usa classi del
 * motore: lo tratta come un servizio esterno, con la propria chiave segreta.
 */
class EngineClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $key,
        private readonly int $timeout = 10,
    ) {}

    public static function fromConfig(): self
    {
        $url = config('services.engine.url');

        return new self(
            rtrim(is_string($url) && $url !== '' ? $url : rtrim((string) config('app.url'), '/').'/api/v1', '/'),
            config('services.engine.key'),
            (int) config('services.engine.timeout', 10),
        );
    }

    public function configured(): bool
    {
        return is_string($this->key) && $this->key !== '';
    }

    /**
     * Servizi attivi (in cache per 5 minuti).
     *
     * @return list<array<string, mixed>>
     */
    public function extras(): array
    {
        /** @var list<array<string, mixed>> */
        return Cache::remember('cms.engine.extras', 300, fn () => $this->get('/extras')['data'] ?? []);
    }

    /**
     * Prezzi "a partire da" (in cache per 10 minuti).
     *
     * @return array<string, mixed>
     */
    public function tariffSummary(): array
    {
        return Cache::remember('cms.engine.tariffs.'.now('Europe/Rome')->format('Y-m-d'), 600, fn () => $this->get('/tariffs/summary'));
    }

    /**
     * @param  array<string, mixed>  $request  schema QuoteRequest
     * @return array<string, mixed>
     */
    public function quote(array $request): array
    {
        return $this->post('/quotes', $request);
    }

    /**
     * @param  array<string, mixed>  $request  schema ReservationRequest
     * @return array<string, mixed>
     */
    public function createReservation(array $request, string $idempotencyKey): array
    {
        return $this->post('/reservations', $request, ['Idempotency-Key' => $idempotencyKey]);
    }

    /**
     * @return array<string, mixed>
     */
    public function createPaypalOrder(string $reservationId, string $idempotencyKey): array
    {
        return $this->post("/reservations/{$reservationId}/payments/paypal/orders", [], ['Idempotency-Key' => $idempotencyKey]);
    }

    /**
     * @return array<string, mixed>
     */
    public function capturePaypalOrder(string $reservationId, string $orderId): array
    {
        return $this->post("/reservations/{$reservationId}/payments/paypal/capture", ['order_id' => $orderId], ['Idempotency-Key' => 'capture-'.$orderId]);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get($this->baseUrl.$path));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function post(string $path, array $body, array $headers = []): array
    {
        return $this->send(fn (PendingRequest $http) => $http->withHeaders($headers)->post($this->baseUrl.$path, (object) $body));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     * @return array<string, mixed>
     */
    private function send(callable $request): array
    {
        if (! $this->configured()) {
            throw new EngineUnavailable('Motore prenotazioni non configurato: manca ENGINE_API_KEY.');
        }

        try {
            $response = $request(Http::acceptJson()->asJson()->timeout($this->timeout)->withToken((string) $this->key));
        } catch (ConnectionException $e) {
            report($e);

            throw new EngineUnavailable('Motore prenotazioni non raggiungibile.', previous: $e);
        }

        if ($response->successful()) {
            /** @var array<string, mixed> */
            return (array) $response->json();
        }

        if ($response->clientError() && is_array($response->json()) && isset($response->json()['code'])) {
            /** @var array<string, mixed> $problem */
            $problem = $response->json();

            throw new EngineApiError($response->status(), $problem);
        }

        report(new EngineUnavailable("Motore prenotazioni: HTTP {$response->status()} su {$response->effectiveUri()}"));

        throw new EngineUnavailable('Motore prenotazioni non disponibile (HTTP '.$response->status().').');
    }
}
