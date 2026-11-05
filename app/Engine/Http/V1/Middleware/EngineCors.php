<?php

namespace App\Engine\Http\V1\Middleware;

use App\Engine\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS per /api/v1: risponde solo alle origini registrate da almeno un client
 * attivo. È un middleware globale perché le richieste di preflight (OPTIONS)
 * non passano dai middleware delle rotte.
 */
final class EngineCors
{
    private const EXPOSED_HEADERS = 'Location, RateLimit-Limit, RateLimit-Remaining, RateLimit-Reset, Retry-After, Idempotent-Replayed';

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if ($origin === null || ! $request->is('api/v1', 'api/v1/*')) {
            return $next($request);
        }

        $allowed = in_array(ApiClient::normalizeOrigin($origin), ApiClient::allAllowedOrigins(), true);

        if ($request->isMethod('OPTIONS') && $request->headers->has('Access-Control-Request-Method')) {
            $response = new Response(status: 204);
            if ($allowed) {
                $response->headers->add([
                    'Access-Control-Allow-Origin' => $origin,
                    'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, Idempotency-Key',
                    'Access-Control-Max-Age' => '600',
                ]);
            }
            $response->headers->set('Vary', 'Origin');

            return $response;
        }

        $response = $next($request);
        if ($allowed) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Expose-Headers', self::EXPOSED_HEADERS);
        }
        $response->headers->set('Vary', 'Origin', false);

        return $response;
    }
}
