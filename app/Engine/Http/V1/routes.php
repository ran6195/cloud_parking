<?php

/*
 * Rotte pubbliche del motore: prefisso /api/v1, nome "engine.v1.*".
 * Il contratto di riferimento è openapi/v1.yaml.
 */

use App\Engine\Http\V1\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('health');
