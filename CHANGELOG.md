# Changelog

Registro dei file creati e modificati per il motore prenotazioni (`App\Engine`).
Piano di riferimento: `docs/piano_motore_api.md`.

## Fase 3 — Schema e modelli (2026-11-05)

### Creati
- `database/engine/migrations/`: `price_lists` e `prices` (vincolo di esclusione Postgres contro i periodi sovrapposti, righe 1–30), `settings`, `blocked_dates`, `discounts` (codice normalizzato e unico tra i non cancellati), `api_clients`, `webhook_endpoints`, `admins`, `customers` (unico per email normalizzata), `quotes`, `reservations` (`number` identity che proseguirà la numerazione legacy, date locali per la disponibilità, vincoli su stato e totali), `reservation_lines`, `payment_transactions`, `idempotency_keys`.
- `app/Engine/Models/`: `EngineModel` (connessione `engine`, date salvate con l'offset), `PriceList`, `Price`, `Setting`, `BlockedDate`, `Discount`, `Customer`, `ApiClient`, `WebhookEndpoint`, `Admin`, `Quote`, `Reservation`, `ReservationLine`, `PaymentTransaction`, `IdempotencyKey`.
- `app/Engine/Domain/Booking/ReservationStatus.php`: stati della spec OpenAPI.
- `app/Engine/Infrastructure/Pricing/`: `DatabaseTariffSource` e `DatabaseDiscountFinder` (utilizzi contati sulle prenotazioni valide).
- `app/Engine/Legacy/`: `LegacyExceptionsConverter`, `LegacyExceptionsConversion`, `LegacyMonthlyPriceList`.
- `app/Engine/Console/ImportLegacyExceptionsCommand.php`: `php artisan engine:import-legacy-exceptions [--dry-run] [--extra-day-cents=1000] [--connection=legacy]`, idempotente, con report.
- `database/engine/factories/`: factory di listini, sconti, clienti, client API, admin, preventivi, prenotazioni, righe, transazioni, date bloccate.
- `database/engine/seeders/EngineSeeder.php`: listino di esempio e codice `BENVENUTO10`.
- `tests/Engine/Feature/Models/`: test di listini, sconti, prenotazioni, clienti, preventivi e seeder.
- `tests/Engine/Feature/Legacy/ImportLegacyExceptionsCommandTest.php`, `tests/Engine/Unit/Legacy/LegacyExceptionsConverterTest.php`.

### Modificati
- `app/Engine/EngineServiceProvider.php`: registra il comando di import.
- `config/database.php`: connessione `legacy` (MariaDB, variabili `LEGACY_DB_*`).
- `.env.example`, `.env` (non versionato): variabili `LEGACY_DB_*`.
- `composer.json`: autoload `Database\Engine\` → `database/engine/`.
- `docs/piano_motore_api.md`, `docs/legacy_problemi_prezzi.md`: decisioni e stato aggiornati.

### Rimossi
- `database/engine/migrations/.gitkeep`.

## Fase 2 — Motore prezzi puro (2026-10-31)

### Creati
- `app/Engine/Domain/Shared/`: `Money` (centesimi interi), `Clock`, `SystemClock`, `FrozenClock`, `EngineException` (codice errore stabile).
- `app/Engine/Domain/Pricing/`: `StayPeriod`, `StayDuration`, `DurationPolicy`, `PriceTable`, `TariffSource`, `TariffMatch`, `InMemoryTariffSource`, `PreviousYearsTariffSource`, `ExtraId`, `ExtraPricing`, `ExtraDefinition`, `ExtrasCatalog`, `PaymentMethod`, `PaymentFees`, `QuoteRequest`, `QuoteLine`, `QuoteLineType`, `Quote`, `PricingTrace`, `QuoteCalculator`.
- `app/Engine/Domain/Pricing/Discount/`: `DiscountCode`, `DiscountType`, `DiscountRejection`, `Discount`, `DiscountFinder`, `InMemoryDiscountFinder`, `AppliedDiscount`, `DiscountPolicy`.
- `app/Engine/Domain/Pricing/Exceptions/`: `InvalidStay`, `PriceListNotFound`, `ExtraUnavailable`, `IncompatibleExtras`.
- `tests/Engine/Support/Pricing.php`: listino, catalogo e calcolatore di prova.
- `tests/Engine/Unit/Pricing/`: test di `Money`, durata (compresa l'ora legale), listini e fallback, catalogo, sconti, preventivo e golden test.
- `tests/Engine/Fixtures/legacy_golden.json`: 166 prenotazioni web reali (solo date, servizi e importi) e i listini mensili usati.
- `docs/pricing.md`: regole di calcolo con esempi, verificati da un test.
- `docs/legacy_problemi_prezzi.md`: problemi del motore di costo legacy da risolvere.

### Modificati
- `.gitignore`: esclude `*.sql`, `*.sql.gz` e `*.dump` (i dump contengono dati personali).
- `docker-compose.yml`: servizio `legacy` (MariaDB 10.11, profilo `legacy`) per analizzare i dump.
- `docs/piano_motore_api.md`: decisioni aggiornate con i dati del dump.

### Rimossi
- `app/Engine/Domain/.gitkeep`.

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
