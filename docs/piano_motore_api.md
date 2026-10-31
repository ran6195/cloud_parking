# Piano: motore prenotazioni e tariffe API-first

Stato: **bozza da approvare** (aggiornata il 2026-10-31). Riferimento regole di calcolo: `docs/prompt_rebuild_motore_prenotazioni.md`.

## 1. Principi

- **Modulo isolato dentro `cloud_parking`**: stesso repo e stessa app Laravel Cloud (Postgres 18 serverless + Object Storage). Il motore però ha namespace, schema DB, migrazioni, rotte e pannello propri. **Nessuna dipendenza in nessuna delle due direzioni** con il CMS, e il vincolo è verificato da test di architettura.
- **Contratto prima del codice**: `openapi/v1.yaml` è la fonte di verità. I test verificano che ogni risposta sia conforme alla spec.
- **Il server è l'unica fonte del prezzo**: le interfacce chiedono un preventivo, ricevono un `quote_id` firmato e con scadenza, e prenotano solo a partire da quello.
- **Più interfacce, una sola API**: ogni interfaccia (form CMS, PWA, partner) è un *client* con le proprie credenziali e il proprio `canale`.
- **Il CMS fa da banco di prova**: un form concreto che consuma l'API **via HTTP**, come qualsiasi altro client, senza chiamare classi del motore.
- **Estraibile**: se un giorno servirà un'app separata, basta spostare `app/Engine`, `database/engine` e `openapi/` in un nuovo repo.

## 2. Architettura del modulo

```
app/Engine/                       # namespace App\Engine — non importa nulla da App\* del CMS
  Domain/                         # logica pura, testabile senza DB né framework
    Shared/        Money (centesimi int), DateRange, Clock
    Pricing/       StayPeriod, DurationCalculator, TariffSource (interfaccia),
                   PriceListTariffSource, LegacyExceptionsTariffSource,
                   CoveredSurcharge, ExtrasCatalog, DiscountPolicy,
                   QuoteCalculator, Quote, QuoteLine, PricingTrace
    Availability/  AvailabilityChecker (query aggregate), CapacityResult
    Booking/       ReservationStatus (enum + transizioni), CreateReservation,
                   CancelReservation, ConfirmPayment, BookingLock
    Payments/      PaymentGateway (interfaccia), PayPalGateway
    Receipts/      ReceiptGenerator (interfaccia), StubReceiptGenerator
  Models/          modelli Eloquent sulla connessione `engine`
  Settings/        EngineSettings (tabella propria, tipizzata, in cache)
  Http/V1/         controller sottili, FormRequest, Resource JSON, middleware client/idempotenza
  Filament/        pannello separato "Motore" (/motore), distinto da /admin
  EngineServiceProvider.php   # registra rotte /api/v1, connessione, migrazioni, pannello
database/engine/migrations/   # migrazioni del motore, separate da quelle del CMS
openapi/v1.yaml
tests/Engine/{Unit,Feature,Arch}
```

- **Database**:
  - Su Cloud si usa lo stesso Postgres 18, ma in uno **schema dedicato `engine`** (connessione `engine` con `search_path=engine`). Le tabelle del CMS restano nello schema `public`.
  - In locale c'è un container `postgres:18-alpine` con i database `parking` (sviluppo) e `parking_test` (test, usato da `phpunit.xml`).
  - Il CMS in locale può restare su SQLite finché non decidi di allinearlo. Il motore usa sempre Postgres, anche nei test.
- **Lock anti-overbooking**: `pg_advisory_xact_lock` (lock a livello di transazione) oppure `SELECT … FOR UPDATE`. Vanno evitati i lock a livello di sessione, che con il connection pooler di Cloud non sono affidabili.
- **Code**: email, webhook in uscita e ricevute vanno in coda (`ShouldQueue`). Su Cloud servono un worker e lo scheduler.
- **Storage**: le ricevute stanno su Object Storage, con il disco `receipts` configurato da env. In locale si usa il disco `local`.
- **Test di architettura (Pest `arch()`)**:
  - `App\Engine` non usa `App\Models`, `App\Filament`, `App\Settings`, `App\Livewire`, `App\Services`, `App\Support`;
  - il CMS non usa `App\Engine`, tranne `App\Engine\…` nel solo provider registrato in `bootstrap/providers.php`.

## 3. Autenticazione e client

Tabella `engine.api_clients`: nome, `canale`, chiave pubblicabile, chiave segreta (hash), origini CORS consentite, abilità, rate limit.

| Tipo chiave | Dove si usa | Abilità |
|---|---|---|
| `pk_…` pubblicabile | browser e PWA (visibile) | `extras`, `availability`, `quotes`, `reservations:create` + vincolo sull'origine |
| `sk_…` segreta | server-to-server (form del CMS, partner) | tutto quello sopra + lettura delle prenotazioni del proprio canale |

- La chiave passa nell'header `Authorization: Bearer …`.
- Il rate limit si applica per chiave e per IP, con limiti più stretti su `discounts` e `reservations`.
- Il form del CMS usa una `sk_` in `ENGINE_API_KEY` e l'URL in `ENGINE_API_URL`.

## 4. Endpoint v1 (bozza)

| Metodo | Path | Scopo |
|---|---|---|
| GET | `/api/v1/extras` | servizi attivi, con prezzo, tipo e messaggio |
| GET | `/api/v1/tariffs/summary` | prezzi "a partire da" per landing e marketing (1, 3, 7, 14 giorni) |
| POST | `/api/v1/availability` | `{start, end, extras[]}` → disponibilità base e per ciascun servizio a capacità |
| POST | `/api/v1/quotes` | `{start, end, extras[], discount_code?}` → righe, totale, totale scontato, `quote_id`, `expires_at` |
| GET | `/api/v1/quotes/{id}` | rilettura del preventivo |
| POST | `/api/v1/discounts/check` | verifica del codice (anche implicita in `quotes`) |
| POST | `/api/v1/reservations` | `{quote_id, payment_method, customer, vehicle, flight, notes, consents}` + `Idempotency-Key` |
| GET | `/api/v1/reservations/{id}` | stato e dettaglio (solo `sk_` del canale, oppure token del cliente) |
| POST | `/api/v1/reservations/{id}/payments/paypal` | `{order_id}` → verifica lato server presso PayPal, idempotente |
| GET | `/api/v1/cancellations/{token}` | anteprima: annullabile? fino a quando? |
| POST | `/api/v1/cancellations/{token}` | `{motivo, dati_rimborso}` |
| POST | `/api/v1/webhooks/paypal` | rete di sicurezza `PAYMENT.CAPTURE.COMPLETED` |

- **Date e ore**: formato ISO 8601 con offset (`2026-11-03T08:00:00+01:00`). Questo elimina l'ambiguità sull'ora legale.
- **Errori**: RFC 9457 `application/problem+json` con un `code` stabile: `dates_unavailable`, `quote_expired`, `quote_mismatch`, `discount_invalid`, `extras_incompatible`, `payment_not_verified`, `cancellation_window_closed`.
- **Webhook in uscita** per i client che li registrano: `reservation.created`, `reservation.paid`, `reservation.cancelled`, firmati HMAC con ritentativi.
- **Compatibilità con la PWA legacy** (`/api/calcolo/...` ecc.): adattatore opzionale sopra la v1, solo in fase 11.

## 5. Schema dati (schema `engine`, sintesi)

- `price_lists` (periodo, soft delete) e `prices` (`price_list_id`, `days` 1..31, `amount_cents`)
- `legacy_exceptions` (solo per la transizione)
- `settings`: extra, prezzi, capacità, blocco automatico, ore di cancellazione, maggiorazione coperto
- `blocked_dates`
- `discounts`: codice in maiuscolo, tipo, valore, utilizzi residui, scadenza
- `quotes`: uuid, input, righe `jsonb`, totali, trace, `expires_at`, `api_client_id`
- `customers` (deduplica per email) e `reservations` (stato, periodo, veicolo, volo, totali, canale, `api_client_id`, `cancellation_token`, `quote_id`, consensi) e `reservation_lines`
- `payment_transactions`: provider, `order_id` unique, payload `jsonb`, importo
- `idempotency_keys`: chiave, client, hash della richiesta, risposta salvata
- `api_clients` e `webhook_endpoints`
- `admins`: utenti del pannello Motore, separati dagli utenti del CMS

## 6. Fasi

Ogni fase si chiude con test Pest verdi, Pint e Larastan puliti, e l'aggiornamento di `CHANGELOG.md`.

| # | Fase | Consegna | Verifica |
|---|---|---|---|
| 0 | Setup | `docker-compose.yml` con Postgres 18 (dev + test), connessione `engine`, `EngineServiceProvider`, Pest, Larastan, test di architettura, `install:api` | `php artisan test` verde, test arch verdi |
| 1 | **Spec OpenAPI v1** | `openapi/v1.yaml` completo di esempi | **revisione tua prima di procedere** |
| 2 | Motore prezzi puro | durata, listini, fallback, coperto, extra, sconti, `Money` | test unitari (inclusi ora legale e 29/02) |
| 3 | Schema e modelli | migrazioni `database/engine`, factory, seed listino di esempio, comando `exceptions → price_lists` | test sui modelli |
| 4 | Disponibilità | query aggregate, date bloccate, capacità coperto e chiavi | test di equivalenza con l'algoritmo giorno per giorno |
| 5 | API lettura | client e chiavi, CORS, rate limit, `extras`, `tariffs`, `availability`, `quotes`, `discounts` | feature test + validazione contro la spec |
| 6 | Prenotazione | `reservations` con lock, idempotenza, email in coda, ricevuta stub, stati | test di concorrenza, 422, email che fallisce |
| 7 | Pagamenti | PayPal Orders v2 con verifica lato server e webhook | test con gateway fake |
| 8 | Cancellazione | anteprima e conferma via token, finestra configurabile | test 24h01 / 23h59 |
| 9 | Pannello Motore (Filament, `/motore`) | prenotazioni, clienti, listini (editor 31 righe), sconti, date bloccate, impostazioni, simulatore preventivo, widget occupazione, gestione client API | test di accesso alle pagine |
| 10 | **Form di prova nel CMS** | `App\Services\EngineClient` (HTTP con `sk_`), BookingForm completo (date → preventivo → dati → prenotazione → pagamento) | test con `Http::fake()` + prova end-to-end in locale e su Cloud |
| 11 | Legacy | import dal DB legacy con `--dry-run` e report, golden test, eventuale adattatore PWA | golden test al centesimo |
| 12 | Laravel Cloud | env `ENGINE_*`, creazione dello schema `engine`, worker, scheduler, bucket ricevute, segreti PayPal | smoke test in produzione |

Note sulla fase 10:
- Nel CMS spariscono `App\Services\PriceCalculator`, i campi `price_*` di `ParkingSettings` e il `booking_url`.
- Il blocco pricing legge `/api/v1/tariffs/summary`, con la risposta in cache.
- **Chiamate dell'app verso sé stessa in locale**: `php artisan serve` è a processo singolo, quindi una richiesta del form verso `/api/v1` resterebbe bloccata in attesa. Serve `PHP_CLI_SERVER_WORKERS=4`, oppure Herd o Valet. Su Cloud non c'è questo problema.

## 7. Decisioni (proposte, da confermare)

1. **Totale dal client**: il server ricalcola sempre. Il client invia solo il `quote_id`. Se il preventivo è scaduto o il prezzo è cambiato, la risposta è 409 `quote_expired` o `quote_mismatch` con il nuovo preventivo.
2. **Sconti**: il totale resta tra 0 e T, e lo 0 è ammesso.
3. **`quantita_massima`**: utilizzi residui. `null` significa illimitato, `0` esaurito. Il decremento è atomico al passaggio a `da_pagare` o `pagata`.
4. **Stati**: `iniziata → da_pagare | pagata → completata`, più `annullata` e `scaduta`. Da verificare sui dati legacy.
5. **Coperto e "Tieni le chiavi"**: esclusivi anche lato server (`extras_incompatible`).
6. **Intervalli di disponibilità**: le date bloccate includono il giorno di rientro, le capacità no. Da confermare.
7. **Fallback `exceptions`**: solo durante la transizione. Si migra e poi si rimuove, quindi la costante di 10 € non diventa un'impostazione.
8. **Customer**: deduplica per email normalizzata.
9. **Prenotazioni `iniziata` non pagate**: diventano `scaduta` dopo N minuti (job schedulato) e non occupano posti.
10. **Durata e ora legale**: replicare il legacy. Da verificare se il legacy calcolava in UTC o in `Europe/Rome`.
11. **CMS locale su SQLite**: resta così, oppure si passa anche il CMS al Postgres locale per allinearlo a Cloud (con export dei dati attuali).

## 8. Cosa serve dal gestore

- Conferma o modifica delle decisioni della sezione 7.
- Accesso in lettura al DB legacy (`~/Code/nuovo_prenota` usa MySQL; basta un dump), per stati, timezone e casi del golden test.
- Credenziali PayPal sandbox.
- Brand da usare nelle email (CCParking o Sosta BLQ).
