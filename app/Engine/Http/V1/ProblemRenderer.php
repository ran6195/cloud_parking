<?php

namespace App\Engine\Http\V1;

use App\Engine\Domain\Pricing\Exceptions\IncompatibleExtras;
use App\Engine\Domain\Pricing\Exceptions\InvalidStay;
use App\Engine\Domain\Shared\EngineException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Trasforma le eccezioni delle rotte /api/v1 in problem+json.
 * Le eccezioni non riconosciute restano al gestore standard di Laravel.
 */
final class ProblemRenderer
{
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/v1', 'api/v1/*')) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => Problem::validation(self::errors($e)),
            $e instanceof InvalidStay => Problem::validation(['end' => [$e->getMessage()]]),
            $e instanceof IncompatibleExtras => Problem::validation(['extras' => [$e->getMessage()]], $e->errorCode(), $e->getMessage()),
            $e instanceof EngineException => Problem::response(422, $e->errorCode(), $e->getMessage()),
            $e instanceof NotFoundHttpException => Problem::response(404, 'not_found', 'La risorsa richiesta non esiste.'),
            default => null,
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private static function errors(ValidationException $e): array
    {
        /** @var array<string, list<string>> */
        return $e->errors();
    }
}
