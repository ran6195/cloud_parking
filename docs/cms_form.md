# Form di prenotazione del CMS

Il modulo della landing (`App\Livewire\BookingForm`) è un **client dell'API del motore**, come la PWA o un partner. Non usa classi di `App\Engine`, e un test di architettura lo verifica. Chiama `/api/v1` via HTTP con `App\Services\Engine\EngineClient` e una chiave segreta `sk_`.

## Configurazione

```
php artisan engine:client "Form del CMS" cms --origin=https://sostablq.it   # stampa la chiave sk_
```

| Variabile | Valore |
|---|---|
| `ENGINE_API_KEY` | chiave `sk_` del client `cms` |
| `ENGINE_API_URL` | facoltativa; senza, si usa `APP_URL/api/v1` (stessa applicazione) |
| `ENGINE_API_TIMEOUT` | secondi di attesa, predefinito 10 |
| `ENGINE_FORM_PAYPAL_BUTTONS` | `true` per i pulsanti PayPal veri (servono `PAYPAL_CLIENT_ID` e il motore con `ENGINE_PAYMENTS_DRIVER=paypal`); altrimenti il form mostra un pagamento simulato |

**In locale** `php artisan serve` serve una richiesta alla volta, quindi la chiamata del CMS verso sé stesso resterebbe bloccata. Serve più di un processo:

```
PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload
```

Senza `--no-reload` Laravel ignora la variabile. Con Herd o Valet, e su Laravel Cloud, il problema non c'è.

## Passi del modulo

1. **Date e servizi**: consegna, ritiro, persone, metodo di pagamento, servizi (da `GET /extras`) e codice sconto → `POST /quotes`. Il modulo mostra righe e totale, oppure "non ci sono più posti" con il telefono.
2. **Dati**: nome, cognome, email, telefono, targa, voli, note e consensi → `POST /reservations`, con una `Idempotency-Key` per ogni tentativo.
3. **Conferma**:
   - con il pagamento in parcheggio, numero e riepilogo;
   - con il pagamento online, il posto resta tenuto fino al termine e c'è il pulsante di pagamento (`orders` + `capture`).

Risposte del motore gestite:

| Risposta | Cosa vede il cliente |
|---|---|
| 422 con `errors` | il messaggio sotto il campo giusto (`customer.email` → Email, `start` → Lasci l'auto il…) |
| `quote_mismatch` | il nuovo preventivo, con l'avviso che il prezzo è cambiato |
| `quote_expired` | il preventivo rinnovato in automatico, con l'avviso se il prezzo è cambiato |
| `dates_unavailable` | il ritorno al primo passo con il messaggio |
| motore non raggiungibile o non configurato | "Prenota con una telefonata", con il numero delle impostazioni generali |

## Tabella prezzi della landing

Il blocco "pricing" legge `GET /tariffs/summary`, con la risposta in cache per 10 minuti: 1, 3, 7 e 14 giorni, giorno oltre il 30° e supplemento del posto coperto. Se il motore non risponde, al posto della tabella invita a usare il modulo.

Sono stati rimossi dal CMS `PriceCalculator`, i prezzi e `booking_url` delle impostazioni "Parcheggio" (migrazione `database/settings/2026_11_05_120000_remove_pricing_from_parking_settings.php`).
