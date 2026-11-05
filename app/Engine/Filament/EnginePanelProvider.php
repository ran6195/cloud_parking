<?php

namespace App\Engine\Filament;

use App\Engine\Filament\Widgets\EngineStatsWidget;
use App\Engine\Filament\Widgets\OccupancyWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Pannello del gestore del motore (/motore), separato dall'admin del CMS (/admin):
 * utenti propri (engine.admins) con il guard "engine".
 */
class EnginePanelProvider extends PanelProvider
{
    public const ID = 'motore';

    public const GUARD = 'engine';

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id(self::ID)
            ->path('motore')
            ->login()
            ->authGuard(self::GUARD)
            ->brandName('Motore prenotazioni')
            ->colors(['primary' => Color::Sky])
            ->discoverResources(in: __DIR__.'/Resources', for: 'App\Engine\Filament\Resources')
            ->discoverPages(in: __DIR__.'/Pages', for: 'App\Engine\Filament\Pages')
            ->pages([Dashboard::class])
            ->widgets([EngineStatsWidget::class, OccupancyWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
