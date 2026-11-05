<?php

namespace App\Engine\Http\V1\Middleware;

use App\Engine\EngineServiceProvider;
use App\Engine\Http\V1\Problem;
use App\Engine\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header Idempotency-Key (facoltativo) per le richieste che creano qualcosa.
 *
 * - stessa chiave e stesso corpo: si ripete la risposta salvata, con Idempotent-Replayed: true;
 * - stessa chiave e corpo diverso, oppure prima richiesta ancora in corso: 409 idempotency_conflict;
 * - le risposte 5xx non si salvano, così la richiesta si può ritentare.
 *
 * Le chiavi valgono per client e durano config("engine.idempotency_ttl_hours").
 */
final class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->headers->get('Idempotency-Key');
        if ($key === null) {
            return $next($request);
        }

        if (mb_strlen($key) < 8 || mb_strlen($key) > 255) {
            return Problem::validation(['Idempotency-Key' => ['La chiave di idempotenza deve avere da 8 a 255 caratteri.']]);
        }

        $client = AuthenticateClient::client($request);
        $hash = self::hash($request);

        $record = $this->claim($client->id, $key, $hash);
        if ($record !== null) {
            return $this->replayOrConflict($record, $hash);
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 500) {
            IdempotencyKey::query()->where('api_client_id', $client->id)->where('key', $key)->delete();

            return $response;
        }

        IdempotencyKey::query()->where('api_client_id', $client->id)->where('key', $key)->update([
            'response_status' => $response->getStatusCode(),
            'response_body' => (string) $response->getContent(),
            'response_headers' => json_encode(array_filter([
                'Content-Type' => $response->headers->get('Content-Type'),
                'Location' => $response->headers->get('Location'),
            ])),
        ]);

        return $response;
    }

    /**
     * Prenota la chiave: null se è nuova (o scaduta), altrimenti il record esistente.
     */
    private function claim(int $clientId, string $key, string $hash): ?IdempotencyKey
    {
        $connection = DB::connection(EngineServiceProvider::CONNECTION);
        $now = now();

        $connection->delete('delete from idempotency_keys where api_client_id = ? and key = ? and expires_at <= ?', [$clientId, $key, $now]);

        $inserted = $connection->table('idempotency_keys')->insertOrIgnore([
            'api_client_id' => $clientId,
            'key' => $key,
            'request_hash' => $hash,
            'expires_at' => $now->copy()->addHours((int) config('engine.idempotency_ttl_hours')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted === 1
            ? null
            : IdempotencyKey::query()->where('api_client_id', $clientId)->where('key', $key)->first();
    }

    private function replayOrConflict(IdempotencyKey $record, string $hash): Response
    {
        if (! hash_equals($record->request_hash, $hash)) {
            return Problem::response(409, 'idempotency_conflict', 'La stessa Idempotency-Key è già stata usata con una richiesta diversa.');
        }

        if ($record->response_status === null) {
            return Problem::response(409, 'idempotency_conflict', 'Una richiesta con la stessa Idempotency-Key è ancora in elaborazione.');
        }

        return new Response((string) $record->response_body, $record->response_status, [
            ...$record->response_headers ?? [],
            'Idempotent-Replayed' => 'true',
        ]);
    }

    /**
     * Impronta del corpo, indipendente dall'ordine delle chiavi JSON.
     */
    private static function hash(Request $request): string
    {
        $payload = $request->all();
        self::sortRecursive($payload);

        return hash('sha256', $request->method().' '.$request->path().' '.json_encode($payload));
    }

    /**
     * @param  array<mixed>  $data
     */
    private static function sortRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::sortRecursive($value);
            }
        }
    }
}
