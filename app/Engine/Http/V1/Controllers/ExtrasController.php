<?php

namespace App\Engine\Http\V1\Controllers;

use App\Engine\Http\V1\Presenters\ExtraPresenter;
use App\Engine\Settings\EngineSettings;
use Illuminate\Http\JsonResponse;

final class ExtrasController
{
    public function __invoke(EngineSettings $settings): JsonResponse
    {
        return response()->json([
            'data' => array_map(ExtraPresenter::present(...), $settings->catalog()->active()),
        ]);
    }
}
