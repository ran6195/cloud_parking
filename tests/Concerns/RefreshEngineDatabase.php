<?php

namespace Tests\Concerns;

use App\Engine\EngineServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Prepara lo schema del motore sul Postgres di test: migrazione completa una
 * volta per processo, poi ogni test gira dentro una transazione annullata alla fine.
 *
 * Rifiuta di partire se il database non termina con "_test", per non azzerare
 * mai per errore il database di sviluppo.
 */
trait RefreshEngineDatabase
{
    private static bool $engineMigrated = false;

    protected function setUpRefreshEngineDatabase(): void
    {
        $connection = DB::connection(EngineServiceProvider::CONNECTION);
        $database = $connection->getDatabaseName();

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "I test del motore richiedono un database che termina con \"_test\", trovato [{$database}]."
            );
        }

        if (! self::$engineMigrated) {
            Artisan::call('engine:migrate', ['--fresh' => true, '--force' => true]);
            self::$engineMigrated = true;
        }

        $connection->beginTransaction();

        $this->beforeApplicationDestroyed(function () use ($connection): void {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $connection->disconnect();
        });
    }
}
