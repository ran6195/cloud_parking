<?php

namespace App\Engine\Console;

use App\Engine\EngineServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Migra lo schema del motore. Le migrazioni del motore vivono in
 * database/engine/migrations e hanno una propria tabella "migrations"
 * dentro lo schema "engine": il "php artisan migrate" del CMS non le tocca.
 */
class EngineMigrateCommand extends Command
{
    protected $signature = 'engine:migrate
        {--fresh : Elimina tutte le tabelle dello schema del motore e rimigra}
        {--force : Esegue senza conferma (obbligatorio in produzione)}';

    protected $description = 'Crea lo schema del motore prenotazioni ed esegue le sue migrazioni';

    public function handle(): int
    {
        $schema = $this->schema();
        $connection = DB::connection(EngineServiceProvider::CONNECTION);

        $this->components->info(sprintf(
            'Motore: database "%s", schema "%s"',
            $connection->getDatabaseName(),
            $schema,
        ));

        if ($this->option('fresh') && ! $this->option('force')
            && ! $this->confirm("Eliminare tutte le tabelle dello schema \"{$schema}\"?")) {
            return self::FAILURE;
        }

        $connection->statement(sprintf('create schema if not exists "%s"', $schema));

        return $this->call($this->option('fresh') ? 'migrate:fresh' : 'migrate', [
            '--database' => EngineServiceProvider::CONNECTION,
            '--path' => EngineServiceProvider::MIGRATIONS_PATH,
            '--force' => (bool) $this->option('force'),
        ]);
    }

    private function schema(): string
    {
        $schema = (string) config('database.connections.'.EngineServiceProvider::CONNECTION.'.search_path');

        if (preg_match('/^[a-z_][a-z0-9_]*$/', $schema) !== 1) {
            throw new InvalidArgumentException("Nome di schema non valido: [{$schema}]");
        }

        return $schema;
    }
}
