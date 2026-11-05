# Creazione delle prenotazioni

Codice: `app/Engine/Application/ReservationService.php`, esposto da `POST /api/v1/reservations`.
Test: `tests/Engine/Feature/Booking`. Comprende un test di concorrenza con due processi reali.

## 1. Flusso

1. L'interfaccia chiede un preventivo (`POST /quotes`) e riceve un `quote_id`, valido 30 minuti.
2. Invia `POST /reservations` con `quote_id` e i dati di cliente, veicolo, viaggio, consensi ed eventuale fattura.
3. Il motore apre una transazione con un **lock esclusivo** (`pg_advisory_xact_lock`). Le creazioni di prenotazione avvengono quindi una alla volta, e due richieste per l'ultimo posto non passano entrambe. Il lock vale per la sola transazione, quindi funziona anche con il connection pooler di Laravel Cloud.
4. Dentro il lock:
   - controlla che il preventivo sia del client, non usato e non scaduto;
   - **ricalcola il prezzo**: se righe o totale sono cambiati (listino modificato, codice sconto esaurito…), rifiuta e restituisce un nuovo preventivo;
   - **ricontrolla la disponibilità**;
   - crea o aggiorna il cliente (uno per email), la prenotazione e le righe, poi segna il preventivo come usato.
5. Dopo il commit, per le prenotazioni confermate, mette in coda la ricevuta e le email.

## 2. Stati

| Pagamento | Stato iniziale | Poi |
|---|---|---|
| `on_site` (in parcheggio) | `confirmed`, con email e ricevuta subito | `completed` o `cancelled` |
| `online` (PayPal, fase 7) | `pending_payment`, senza email; `payment_due_by` = adesso + 30 minuti | `paid` dopo il pagamento, `expired` se non pagata in tempo, `cancelled` |

- Fino a `payment_due_by` una prenotazione in attesa **tiene occupato il posto**.
- `php artisan engine:expire-pending`, pianificato ogni minuto, porta a `expired` quelle non pagate in tempo.
- Le transizioni consentite sono in `ReservationStatus::allowedTransitions()`, e `Reservation::transitionTo()` rifiuta le altre.

## 3. Errori

| Codice | HTTP | Quando |
|---|---|---|
| `not_found` | 404 | preventivo inesistente o di un altro client |
| `quote_used` | 409 | preventivo già convertito in prenotazione |
| `quote_expired` | 409 | preventivo scaduto |
| `quote_mismatch` | 409 | prezzo o sconto cambiati; il campo `quote` contiene il nuovo preventivo da mostrare |
| `dates_unavailable` | 409 | periodo o servizio non più disponibile; `unavailable_dates` elenca i giorni |
| `idempotency_conflict` | 409 | stessa `Idempotency-Key` con un corpo diverso, oppure prima richiesta ancora in corso |
| `validation_failed` | 422 | dati non validi, con i messaggi per campo in `errors` |

## 4. Idempotency-Key

L'header è facoltativo, ma consigliato: basta un UUID per ogni tentativo.

- La stessa chiave con lo stesso corpo restituisce la risposta originale, identica byte per byte, con `Idempotent-Replayed: true`.
- Vale anche per gli errori 4xx. Le risposte 5xx non si salvano, così la richiesta si può ritentare.
- Le chiavi sono per client e durano 24 ore.

## 5. Email e ricevuta

- **Cliente**: conferma con riepilogo, telefono di contatto e link di cancellazione. Il link segue `ENGINE_CANCELLATION_URL`, con il segnaposto `{token}`; senza la variabile il link non compare.
- **Gestore**: avviso di nuova prenotazione, solo se l'impostazione `contact.email` è valorizzata.
- **Ricevuta**: per ora è un HTML provvisorio, salvato in `engine/receipts/<anno>/<numero>.html` sul disco `ENGINE_RECEIPTS_DISK`.
- Un errore nell'invio non annulla la prenotazione: `notifications.email_sent` resta `false` e l'errore viene registrato. `email_sent: true` significa che le email sono state **messe in coda**.

Su Laravel Cloud servono un **worker della coda** e lo **scheduler**.
