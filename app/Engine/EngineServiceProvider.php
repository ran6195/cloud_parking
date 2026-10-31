<?php

namespace App\Engine;

use App\Engine\Console\EngineMigrateCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Punto di ingresso del motore prenotazioni. È l'unica classe di App\Engine
 * che il CMS conosce (registrata in bootstrap/providers.php).
 */
class EngineServiceProvider extends ServiceProvider
{
    public const CONNECTION = 'engine';

    public const MIGRATIONS_PATH = 'database/engine/migrations';

    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->name('engine.v1.')
            ->group(__DIR__.'/Http/V1/routes.php');

        if ($this->app->runningInConsole()) {
            $this->commands([EngineMigrateCommand::class]);
        }
    }
}
