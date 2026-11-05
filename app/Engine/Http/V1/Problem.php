<?php

namespace App\Engine\Http\V1;

use Illuminate\Http\JsonResponse;

/**
 * Risposta di errore RFC 9457 (application/problem+json) con un "code" stabile.
 */
final class Problem
{
    private const TITLES = [
        'unauthenticated' => 'Non autenticato',
        'forbidden' => 'Accesso negato',
        'not_found' => 'Non trovato',
        'validation_failed' => 'Dati non validi',
        'rate_limited' => 'Troppe richieste',
        'price_list_not_found' => 'Listino non disponibile',
        'extras_incompatible' => 'Servizi non combinabili',
        'extra_unavailable' => 'Servizio non disponibile',
        'idempotency_conflict' => 'Richiesta in conflitto',
        'dates_unavailable' => 'Date non più disponibili',
        'quote_expired' => 'Preventivo scaduto',
        'quote_used' => 'Preventivo già usato',
        'quote_mismatch' => 'Il preventivo è cambiato',
        'reservation_not_payable' => 'Prenotazione non pagabile',
        'payment_not_verified' => 'Pagamento non verificato',
        'payment_provider_error' => 'Errore del servizio di pagamento',
    ];

    /**
     * @param  array<string, mixed>  $extra  campi aggiuntivi (es. "errors")
     * @param  array<string, string|int>  $headers
     */
    public static function response(int $status, string $code, ?string $detail = null, array $extra = [], array $headers = []): JsonResponse
    {
        $body = array_filter([
            'type' => config('engine.problem_type_base').$code,
            'title' => self::TITLES[$code] ?? $code,
            'status' => $status,
            'code' => $code,
            'detail' => $detail,
        ], fn ($value) => $value !== null) + $extra;

        return new JsonResponse($body, $status, ['Content-Type' => 'application/problem+json'] + $headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, list<string>>  $errors  messaggi per campo, in notazione puntata
     */
    public static function validation(array $errors, string $code = 'validation_failed', ?string $detail = null): JsonResponse
    {
        return self::response(422, $code, $detail ?? 'La richiesta contiene dati non validi.', ['errors' => $errors]);
    }
}
