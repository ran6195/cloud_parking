<?php

namespace App\Engine\Http\V1;

use App\Engine\EngineServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::connection(EngineServiceProvider::CONNECTION)->select('select 1');
            $database = 'ok';
        } catch (Throwable $e) {
            report($e);
            $database = 'unavailable';
        }

        return response()->json([
            'status' => $database === 'ok' ? 'ok' : 'degraded',
            'api_version' => 'v1',
            'database' => $database,
        ], $database === 'ok' ? 200 : 503);
    }
}
