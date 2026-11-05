# Changelog

Registro dei file creati e modificati per il motore prenotazioni (`App\Engine`).
Piano di riferimento: `docs/piano_motore_api.md`.

## Fase 9 — Pannello del gestore (2026-11-05)

### Creati
- `app/Engine/Filament/EnginePanelProvider.php`: pannello `/motore` con guard `engine` e utenti `engine.admins`.
- `app/Engine/Filament/Resources/`: `Reservations` (elenco, dettaglio con azioni del gestore, modifica dei dati non legati al prezzo), `Customers`, `PriceLists` (editor dei 30 prezzi, duplicazione con variazione %, controllo delle sovrapposizioni), `Discounts`, `BlockedDates` (giorno o periodo), `ApiClients` (creazione con le chiavi, revoca).
- `app/Engine/Filament/Pages/`: `EngineSettingsPage` (impostazioni del gestore), `QuoteSimulator`.
- `app/Engine/Filament/Widgets/`: `EngineStatsWidget`, `OccupancyWidget` (14 giorni).
- `app/Engine/Filament/Support/`: `Euro`, `Labels`.
- Viste `app/Engine/Resources/views/filament/` (`occupancy`, `quote-result`).
- `app/Engine/Console/CreateAdminCommand.php`: `engine:admin`.
- `docs/panel.md`.
- `tests/Engine/Feature/Panel/EnginePanelTest.php`.

### Modificati
- `app/Engine/EngineServiceProvider.php`: guard e provider di autenticazione `engine`, registrazione del pannello, comando `engine:admin`.
- `app/Engine/Models/Admin.php`: accesso al solo pannello `motore`.
- `app/Engine/Application/CancellationService.php`: `cancelByManager()` senza finestra di tempo; `RefundService::request()` accetta un codice fiscale assente.
- Vista `mail/reservation-cancelled-notice`: codice fiscale "non indicato" se manca.
- `docs/piano_motore_api.md`, `docs/legacy_problemi_prezzi.md`.

## Fase 8 — Cancellazioni e rimborsi (2026-11-05)

### Creati
- `app/Engine/Domain/Booking/`: `CancellationPolicy` (finestra di 24 ore, estremo incluso), `CancellationDecision`, `CancellationReason`.
- `app/Engine/Domain/Payments/GatewayRefund.php`; `PaymentGateway::refundCapture()` nel gateway PayPal (`/v2/payments/captures/{id}/refund`) e in quello simulato.
- `app/Engine/Application/CancellationService.php`, `RefundService.php` (rimborso manuale, il predefinito, oppure automatico), `Exceptions/CancellationNotAllowed.php`.
- `app/Engine/Http/V1/Controllers/CancellationController.php`, `Requests/CancelReservationRequest.php` (codice fiscale o partita IVA).
- `app/Engine/Mail/ReservationCancelled.php` (cliente), `ReservationCancelledNotice.php` (gestore, con "RIMBORSO DA FARE"); viste `mail/reservation-cancelled*`.
- `app/Engine/Console/RefundsCommand.php`: `engine:refunds [numero] [--automatic|--mark-refunded]`.
- `docs/cancellations.md`.
- `tests/Engine/Unit/Booking/CancellationPolicyTest.php`, `tests/Engine/Feature/Cancellations/CancellationsEndpointTest.php`; test del rimborso in `PayPalGatewayTest`.

### Modificati
- `openapi/v1.yaml`: codice `not_cancellable_status` per il POST di cancellazione; `refund` (stato e importo) in `CancellationResult`.
- `app/Engine/Settings/EngineSettings.php`: `refunds.mode` (`manual` | `automatic`).
- `app/Engine/Http/V1/Presenters/ReservationPresenter.php`: `payment.status = refunded`.
- `app/Engine/Application/ReservationNotifier.php`: email di cancellazione.
- `app/Engine/Http/V1/routes.php`, `Problem.php`, `ProblemRenderer.php`, `EngineServiceProvider.php`, `config/engine.php`, `.env.example`.

## Fase 7 — Pagamenti PayPal (2026-11-05)

### Creati
- `app/Engine/Domain/Payments/`: `PaymentGateway` (interfaccia), `GatewayOrder`, `GatewayCapture`, `PaymentProviderError`.
- `app/Engine/Infrastructure/Payments/PayPalGateway.php`: Orders v2 (token OAuth in cache, `PayPal-Request-Id`, ordine già incassato) e verifica della firma dei webhook.
- `app/Engine/Infrastructure/Payments/FakePaymentGateway.php`: PayPal simulato per sviluppo e test, vietato in produzione.
- `app/Engine/Application/PaymentService.php`: creazione e riuso dell'ordine, incasso verificato, pagamento in ritardo solo con il posto libero, webhook `CHECKOUT.ORDER.APPROVED` e `PAYMENT.CAPTURE.COMPLETED`.
- `app/Engine/Application/Exceptions/`: `ReservationNotPayable` (409), `PaymentNotVerified` (422).
- `app/Engine/Http/V1/`: `Controllers/PaymentController`, `Controllers/PayPalWebhookController`, `Requests/CapturePaymentRequest`.
- `docs/payments.md`.
- `tests/Engine/Feature/Payments/PaypalPaymentsTest.php`, `tests/Engine/Feature/Payments/PayPalGatewayTest.php`.

### Modificati
- `app/Engine/Domain/Booking/ReservationStatus.php`: `expired → paid` per i pagamenti arrivati in ritardo.
- `app/Engine/Application/ReservationService.php`: una prenotazione online da 0 € nasce già pagata.
- `app/Engine/Http/V1/routes.php`: `reservations/{id}/payments/paypal/orders`, `…/capture`, `webhooks/paypal`.
- `app/Engine/Http/V1/Problem.php`, `ProblemRenderer.php`: `reservation_not_payable`, `payment_not_verified`, `payment_provider_error` (502).
- `app/Engine/EngineServiceProvider.php`: scelta del gateway (`ENGINE_PAYMENTS_DRIVER`).
- `config/engine.php`, `.env.example`: configurazione dei pagamenti e limite per i webhook.
- `tests/Engine/Support/EngineApi.php`: `createQuote()` e `reservationBody()` condivisi; `ReservationsEndpointTest` li usa.

## Fase 6 — Prenotazione (2026-11-05)

### Creati
- `app/Engine/Application/ReservationService.php`: prenotazione da preventivo dentro `pg_advisory_xact_lock`, con ricalcolo di prezzo e disponibilità; `ReservationInput`, `ReservationNotifier` (email e ricevuta in coda), `PriceChanged`.
- `app/Engine/Application/Exceptions/`: `QuoteNotUsable` (`quote_expired`, `quote_used`), `QuoteMismatch`, `DatesUnavailable`.
- `app/Engine/Http/V1/`: `Middleware/EnsureIdempotency`, `Requests/CreateReservationRequest`, `Presenters/ReservationPresenter`, `Controllers/ReservationController` (`POST /reservations`, `GET /reservations/{id}` solo con `sk_` del canale).
- `app/Engine/Mail/`: `ReservationConfirmed` (cliente), `ReservationReceived` (gestore); viste in `app/Engine/Resources/views/mail`.
- `app/Engine/Domain/Receipts/ReceiptGenerator.php`, `app/Engine/Infrastructure/Receipts/StubReceiptGenerator.php`, `app/Engine/Jobs/GenerateReceipt.php`, vista `receipts/stub`.
- `app/Engine/Console/ExpirePendingReservationsCommand.php`: `engine:expire-pending`, pianificato ogni minuto.
- `database/engine/migrations/2026_11_05_000007_add_discount_snapshot_and_idempotency_headers.php`.
- `docs/booking.md`.
- `tests/Engine/Feature/Booking/ReservationsEndpointTest.php`, `tests/Engine/Feature/Booking/ConcurrentReservationsTest.php` (due processi reali, l'ultimo posto va a uno solo; senza lock il test fallisce), `tests/Engine/Unit/Booking/ReservationStatusTest.php`.

### Modificati
- `app/Engine/Domain/Booking/ReservationStatus.php`: transizioni consentite.
- `app/Engine/Models/Reservation.php`: `transitionTo()`, `owner()`, snapshot dello sconto; la relazione con il codice si chiama `discountCode()`; il cliente si trova anche se cancellato.
- `app/Engine/Models/IdempotencyKey.php`: corpo come testo, header salvati.
- `app/Engine/Settings/EngineSettings.php`: finestra di pagamento online (30 minuti), marchio, telefono ed email del gestore.
- `app/Engine/Http/V1/Middleware/AuthenticateClient.php`: distingue chiave segreta e pubblicabile.
- `app/Engine/Http/V1/Problem.php`, `ProblemRenderer.php`: errori 409 con `unavailable_dates` e `quote`.
- `app/Engine/EngineServiceProvider.php`, `app/Engine/Http/V1/routes.php`, `config/engine.php`, `.env.example`.
- `tests/Engine/Support/EngineApi.php`: l'orologio fermo vale anche per `now()`.

## Fase 5 — API di lettura (2026-11-05)

### Creati
- `config/engine.php`: validità dei preventivi (30 minuti), tolleranza sulla consegna (15 minuti), limiti di richieste, base dei problem+json.
- `app/Engine/Settings/EngineSettings.php`: impostazioni del gestore tipizzate e in cache, con i valori del legacy come predefiniti; costruisce catalogo dei servizi, capacità e commissioni.
- `app/Engine/Application/`: `QuoteService` (calcolo, disponibilità, salvataggio con scadenza) e `QuoteSnapshot`.
- `app/Engine/Http/V1/`:
  - `Problem`, `ProblemRenderer` (errori RFC 9457 con `code` stabile);
  - `Middleware/AuthenticateClient` (chiavi `pk_` con controllo dell'origine, `sk_`), `Middleware/RateLimitClient` (header `RateLimit-*`, 429), `Middleware/EngineCors` (origini registrate dai client);
  - `Rules/DateTimeWithOffset`, `Requests/` (`StayRequest`, `CheckAvailabilityRequest`, `CreateQuoteRequest`, `CheckDiscountRequest`);
  - `Presenters/` (`MoneyJson`, `ExtraPresenter`, `QuotePresenter`);
  - `Controllers/` (`ExtrasController`, `TariffSummaryController`, `AvailabilityController`, `DiscountCheckController`, `QuoteController`).
- `app/Engine/Console/CreateApiClientCommand.php`: `php artisan engine:client "Nome" canale --origin=https://…`.
- `database/engine/migrations/2026_11_05_000006_add_snapshot_columns_to_quotes.php`: durata, disponibilità, `bookable` e termine di cancellazione salvati nel preventivo.
- `config/cors.php`: pubblicato con `paths` vuoto, perché il CORS di `/api/v1` lo gestisce il motore.
- `tests/Engine/Support/EngineApi.php`; `tests/Engine/Feature/Api/` (accesso, catalogo, disponibilità, sconti, preventivi, tutte le risposte validate contro `openapi/v1.yaml`); `tests/Engine/Feature/Settings/EngineSettingsTest.php`.

### Modificati
- `app/Engine/EngineServiceProvider.php`: collegamenti nel container (orologio, impostazioni, listini, sconti, disponibilità, calcolatore), CORS globale, rendering dei problem+json, comando `engine:client`.
- `app/Engine/Http/V1/routes.php`: `extras`, `tariffs/summary`, `availability`, `quotes`, `quotes/{quoteId}`, `discounts/check`.
- `app/Engine/Models/ApiClient.php`: `issue()`, `findByKey()`, origini consentite in cache.
- `app/Engine/Models/Setting.php`: svuota la cache delle impostazioni a ogni modifica.
- `app/Engine/Models/Quote.php`, `database/engine/factories/QuoteFactory.php`: nuove colonne.
- `routes/web.php` (CMS): la rotta delle pagine non intercetta più i percorsi `api/…`.
- `.env.example`: variabili `ENGINE_*` del motore.
- `docs/piano_motore_api.md`: stato e decisioni.

## Fase 4 — Disponibilità (2026-11-05)

### Creati
- `app/Engine/Domain/Availability/`: `AvailabilityChecker`, `AvailabilityData` (interfaccia), `InMemoryAvailabilityData`, `DailyOccupancy`, `CapacityRules`, `Availability`, `AvailabilityItem`, `AvailabilityReason`.
- `app/Engine/Infrastructure/Availability/DatabaseAvailabilityData.php`: date bloccate e posti occupati per giorno con una query aggregata (`generate_series`); circa 5 ms su 22.000 prenotazioni.
- `database/engine/migrations/2026_11_05_000005_require_payment_due_by_for_pending_reservations.php`: termine di pagamento obbligatorio per `pending_payment`.
- `tests/Engine/Unit/Availability/AvailabilityCheckerTest.php`, `tests/Engine/Feature/Availability/DatabaseAvailabilityDataTest.php` (compresa l'equivalenza con l'algoritmo giorno per giorno).
- `docs/availability.md`: regole con esempio.

### Modificati
- `app/Engine/Domain/Booking/ReservationStatus.php`: `holdsCapacity()`; le prenotazioni in attesa di pagamento occupano il posto fino al termine.
- `app/Engine/Domain/Pricing/ExtrasCatalog.php`: `find()` restituisce anche i servizi disattivati.
- `database/engine/factories/ReservationFactory.php`: `status()` imposta il termine di pagamento, `withExtra()` aggiunge la riga di un servizio.
- `phpunit.xml`: `memory_limit` a 512M (i test di architettura portano la suite completa oltre 128M).
- `docs/piano_motore_api.md`: decisioni 6 e 9.

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
