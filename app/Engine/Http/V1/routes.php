<?php

/*
 * Rotte pubbliche del motore: prefisso /api/v1, nome "engine.v1.*".
 * Il contratto di riferimento è openapi/v1.yaml.
 */

use App\Engine\Http\V1\Controllers\AvailabilityController;
use App\Engine\Http\V1\Controllers\DiscountCheckController;
use App\Engine\Http\V1\Controllers\ExtrasController;
use App\Engine\Http\V1\Controllers\QuoteController;
use App\Engine\Http\V1\Controllers\TariffSummaryController;
use App\Engine\Http\V1\HealthController;
use App\Engine\Http\V1\Middleware\AuthenticateClient;
use App\Engine\Http\V1\Middleware\RateLimitClient;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('health');

Route::middleware([AuthenticateClient::class, RateLimitClient::class.':default'])->group(function (): void {
    Route::get('extras', ExtrasController::class)->name('extras');
    Route::get('tariffs/summary', TariffSummaryController::class)->name('tariffs.summary');
    Route::post('availability', AvailabilityController::class)->name('availability');
    Route::post('quotes', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('quotes/{quoteId}', [QuoteController::class, 'show'])->whereUuid('quoteId')->name('quotes.show');
});

Route::middleware([AuthenticateClient::class, RateLimitClient::class.':discounts'])->group(function (): void {
    Route::post('discounts/check', DiscountCheckController::class)->name('discounts.check');
});
