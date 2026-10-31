# Changelog

Registro dei file creati e modificati per il motore prenotazioni (`App\Engine`).
Piano di riferimento: `docs/piano_motore_api.md`.

## Fase 1 — Spec OpenAPI v1 (2026-10-31, bozza da revisionare)

### Creati
- `openapi/v1.yaml`: contratto OpenAPI 3.1 del motore (13 operazioni, 3 webhook in uscita, esempi validati).
- `redocly.yaml`, `.redocly.lint-ignore.yaml`: configurazione del lint (`composer openapi:lint`).
- `tests/Concerns/ValidatesOpenApi.php`: `assertMatchesOpenApi()` valida le risposte reali contro la spec.
- `tests/Engine/Feature/OpenApiContractTest.php`: ogni rotta `engine.v1.*` deve comparire nella spec.

### Modificati
- `tests/Engine/Feature/HealthTest.php`: la risposta di `/health` viene validata contro la spec.
- `composer.json`, `composer.lock`: `osteel/openapi-httpfoundation-testing` ^0.15 (dev) e script `openapi:lint`.
- `docs/piano_motore_api.md`: la tabella endpoint rimanda alla spec; pagamenti PayPal in due passi.

## Fase 0 — Setup (2026-10-31)

### Creati
- `docker-compose.yml`: Postgres 18 (`postgres:18-alpine`) allineato a Laravel Cloud Serverless Postgres 18.
- `docker/postgres/init/01-create-test-database.sh`: crea `parking_test` accanto a `parking`.
- `app/Engine/EngineServiceProvider.php`: registra le rotte `/api/v1` (nome `engine.v1.*`) e i comandi del motore.
- `app/Engine/Console/EngineMigrateCommand.php`: `php artisan engine:migrate [--fresh] [--force]`. Crea lo schema `engine` e migra `database/engine/migrations`, con una propria tabella `engine.migrations`.
- `app/Engine/Http/V1/routes.php`, `app/Engine/Http/V1/HealthController.php`: `GET /api/v1/health`.
- `app/Engine/Domain/.gitkeep`, `database/engine/migrations/.gitkeep`.
- `tests/Pest.php`: configurazione di Pest (i test PHPUnit esistenti restano invariati).
- `tests/Concerns/RefreshEngineDatabase.php`: migrazione una volta per processo e transazione per test. Rifiuta database che non terminano con `_test`.
- `tests/Engine/Arch/IsolationTest.php`: motore e CMS non si importano a vicenda; dominio senza HTTP, DB o facade.
- `tests/Engine/Feature/HealthTest.php`.
- `phpstan.neon`: Larastan livello 8 su `app/Engine`.
- `CHANGELOG.md`.

### Modificati
- `config/database.php`: connessione `engine` (pgsql, `search_path` da `ENGINE_DB_SCHEMA`, fallback sulle `DB_*`).
- `bootstrap/providers.php`: registra `App\Engine\EngineServiceProvider`.
- `phpunit.xml`: suite `Engine` e variabili `ENGINE_DB_*` verso `parking_test`.
- `.env.example`, `.env` (non versionato): variabili `ENGINE_DB_*`.
- `composer.json`, `composer.lock`: aggiunti `pestphp/pest` ^4.7, `pestphp/pest-plugin-laravel` ^4.1, `larastan/larastan` ^3.13 (dev) e lo script `composer analyse`. Con `-W` sono state aggiornate anche dipendenze minori (es. `laravel/framework` 13.30.1 → 13.39.0).
- `docs/piano_motore_api.md`: aggiornato per il modulo dentro `cloud_parking`.
