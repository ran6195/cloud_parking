<?php

namespace App\Engine\Http\V1\Middleware;

use App\Engine\Http\V1\Problem;
use App\Engine\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Riconosce il client dalla chiave "Authorization: Bearer pk_…|sk_…".
 * Una chiave pk_ è accettata solo dalle origini registrate per quel client.
 */
final class AuthenticateClient
{
    private const ATTRIBUTE = 'engine.api_client';

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->bearerToken();
        $client = $key === '' ? null : ApiClient::findByKey($key);

        if ($client === null) {
            return Problem::response(401, 'unauthenticated', 'Chiave API mancante o non valida.');
        }

        if (str_starts_with($key, 'pk_') && ! $client->allowsOrigin($request->headers->get('Origin'))) {
            return Problem::response(401, 'unauthenticated', 'Origine non consentita per questa chiave pubblicabile.');
        }

        if ($client->last_used_at === null || $client->last_used_at->lt(now()->subMinutes(5))) {
            $client->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        $request->attributes->set(self::ATTRIBUTE, $client);

        return $next($request);
    }

    public static function client(Request $request): ApiClient
    {
        $client = $request->attributes->get(self::ATTRIBUTE);

        return $client instanceof ApiClient
            ? $client
            : throw new LogicException('Rotta del motore senza il middleware AuthenticateClient.');
    }
}
