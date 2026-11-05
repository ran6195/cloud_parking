<?php

namespace App\Engine\Http\V1\Middleware;

use App\Engine\Http\V1\Problem;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limite di richieste al minuto per client e IP, con gli header
 * RateLimit-Limit, RateLimit-Remaining e RateLimit-Reset.
 *
 * Il parametro sceglie il limite: "default" (quello del client, o config
 * engine.rate_limits.default) oppure un limite più stretto (es. "discounts").
 */
final class RateLimitClient
{
    private const WINDOW_SECONDS = 60;

    public function handle(Request $request, Closure $next, string $bucket = 'default'): Response
    {
        $client = AuthenticateClient::client($request);
        $limit = $bucket === 'default'
            ? ($client->rate_limit_per_minute ?? (int) config('engine.rate_limits.default'))
            : (int) config("engine.rate_limits.{$bucket}");
        $key = "engine:{$bucket}:{$client->id}:".$request->ip();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = RateLimiter::availableIn($key);

            return Problem::response(429, 'rate_limited', "Troppe richieste: riprova fra {$retryAfter} secondi.", headers: [
                'Retry-After' => $retryAfter,
                'RateLimit-Limit' => $limit,
                'RateLimit-Remaining' => 0,
                'RateLimit-Reset' => $retryAfter,
            ]);
        }

        RateLimiter::hit($key, self::WINDOW_SECONDS);

        $response = $next($request);
        $response->headers->add([
            'RateLimit-Limit' => (string) $limit,
            'RateLimit-Remaining' => (string) RateLimiter::remaining($key, $limit),
            'RateLimit-Reset' => (string) RateLimiter::availableIn($key),
        ]);

        return $response;
    }
}
