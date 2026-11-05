<?php

namespace App\Engine;

use App\Engine\Console\CreateAdminCommand;
use App\Engine\Console\CreateApiClientCommand;
use App\Engine\Console\EngineMigrateCommand;
use App\Engine\Console\ExpirePendingReservationsCommand;
use App\Engine\Console\ImportLegacyExceptionsCommand;
use App\Engine\Console\RefundsCommand;
use App\Engine\Domain\Availability\AvailabilityChecker;
use App\Engine\Domain\Availability\AvailabilityData;
use App\Engine\Domain\Payments\PaymentGateway;
use App\Engine\Domain\Pricing\Discount\DiscountFinder;
use App\Engine\Domain\Pricing\Discount\DiscountPolicy;
use App\Engine\Domain\Pricing\PreviousYearsTariffSource;
use App\Engine\Domain\Pricing\QuoteCalculator;
use App\Engine\Domain\Pricing\TariffSource;
use App\Engine\Domain\Receipts\ReceiptGenerator;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Domain\Shared\SystemClock;
use App\Engine\Filament\EnginePanelProvider;
use App\Engine\Http\V1\Middleware\EngineCors;
use App\Engine\Http\V1\ProblemRenderer;
use App\Engine\Infrastructure\Availability\DatabaseAvailabilityData;
use App\Engine\Infrastructure\Payments\FakePaymentGateway;
use App\Engine\Infrastructure\Payments\PayPalGateway;
use App\Engine\Infrastructure\Pricing\DatabaseDiscountFinder;
use App\Engine\Infrastructure\Pricing\DatabaseTariffSource;
use App\Engine\Infrastructure\Receipts\StubReceiptGenerator;
use App\Engine\Models\Admin;
use App\Engine\Settings\EngineSettings;
use DateTimeZone;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LogicException;

/**
 * Punto di ingresso del motore prenotazioni. È l'unica classe di App\Engine
 * che il CMS conosce (registrata in bootstrap/providers.php).
 */
class EngineServiceProvider extends ServiceProvider
{
    public const CONNECTION = 'engine';

    public const MIGRATIONS_PATH = 'database/engine/migrations';

    public const TIMEZONE = 'Europe/Rome';

    public function register(): void
    {
        // Utenti del pannello Motore: guard e provider propri, separati da quelli del CMS.
        config([
            'auth.guards.'.EnginePanelProvider::GUARD => ['driver' => 'session', 'provider' => 'engine_admins'],
            'auth.providers.engine_admins' => ['driver' => 'eloquent', 'model' => Admin::class],
        ]);
        $this->app->register(EnginePanelProvider::class);

        $this->app->bind(Clock::class, fn () => new SystemClock(new DateTimeZone(self::TIMEZONE)));
        $this->app->scoped(EngineSettings::class);
        $this->app->bind(DiscountFinder::class, DatabaseDiscountFinder::class);
        $this->app->bind(AvailabilityData::class, DatabaseAvailabilityData::class);
        $this->app->bind(ReceiptGenerator::class, StubReceiptGenerator::class);

        $this->app->singleton(PaymentGateway::class, function (Application $app): PaymentGateway {
            return match (config('engine.payments.driver')) {
                'paypal' => PayPalGateway::fromConfig(),
                'fake' => $app->environment('production')
                    ? throw new LogicException('Il PayPal simulato non si può usare in produzione: imposta ENGINE_PAYMENTS_DRIVER=paypal.')
                    : new FakePaymentGateway,
                default => throw new LogicException('ENGINE_PAYMENTS_DRIVER non valido: usa paypal oppure fake.'),
            };
        });

        $this->app->bind(TariffSource::class, fn (Application $app) => new PreviousYearsTariffSource(
            new DatabaseTariffSource,
            $app->make(EngineSettings::class)->fallbackYears(),
        ));

        $this->app->bind(QuoteCalculator::class, function (Application $app): QuoteCalculator {
            $settings = $app->make(EngineSettings::class);

            return new QuoteCalculator(
                tariffs: $app->make(TariffSource::class),
                extras: $settings->catalog(),
                fees: $settings->paymentFees(),
                discounts: new DiscountPolicy($app->make(DiscountFinder::class)),
                clock: $app->make(Clock::class),
            );
        });

        $this->app->bind(AvailabilityChecker::class, function (Application $app): AvailabilityChecker {
            $settings = $app->make(EngineSettings::class);

            return new AvailabilityChecker($app->make(AvailabilityData::class), $settings->capacityRules(), $settings->catalog());
        });
    }

    public function boot(): void
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->name('engine.v1.')
            ->group(__DIR__.'/Http/V1/routes.php');

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'engine');

        // Le richieste di preflight (OPTIONS) non passano dai middleware delle rotte.
        $this->app->make(Kernel::class)->prependMiddleware(EngineCors::class);

        $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
            if ($handler instanceof Handler) {
                $handler->renderable(new ProblemRenderer);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                EngineMigrateCommand::class,
                ImportLegacyExceptionsCommand::class,
                CreateApiClientCommand::class,
                ExpirePendingReservationsCommand::class,
                RefundsCommand::class,
                CreateAdminCommand::class,
            ]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('engine:expire-pending')->everyMinute()->withoutOverlapping();
        });

    }
}
