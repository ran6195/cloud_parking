# Pagamento online (PayPal)

Codice: `app/Engine/Application/PaymentService.php`. Gateway: `app/Engine/Infrastructure/Payments/` (`PayPalGateway` reale, `FakePaymentGateway` simulato).
Test: `tests/Engine/Feature/Payments`. Il gateway reale è provato con risposte PayPal simulate, quindi senza chiamate in rete.

## 1. Flusso (PayPal Orders v2, intent CAPTURE)

1. La prenotazione online nasce `pending_payment`, con 30 minuti per pagare (`payment.due_by`).
2. Il client chiama `POST /reservations/{id}/payments/paypal/orders`. Il **server** crea l'ordine con il totale della prenotazione e restituisce `order_id`. Ripetendo la chiamata si ottiene lo stesso ordine per 3 ore.
3. Il client mostra il pulsante PayPal con quell'`order_id`, e il cliente approva.
4. Il client chiama `POST /reservations/{id}/payments/paypal/capture` con `order_id`. Il server incassa e verifica: stato `COMPLETED`, valuta EUR, **importo identico** al totale.
5. Se la verifica passa, la prenotazione diventa `paid` e partono email e ricevuta. Ripetere la cattura restituisce la prenotazione pagata, senza email doppie.

## 2. Rete di sicurezza: webhook

`POST /api/v1/webhooks/paypal` è autenticato dalla firma di PayPal (API `verify-webhook-signature` con `PAYPAL_WEBHOOK_ID`), non dalle chiavi dei client.

| Evento | Effetto |
|---|---|
| `CHECKOUT.ORDER.APPROVED` | Il cliente ha approvato ma il client si è chiuso: il server incassa da sé |
| `PAYMENT.CAPTURE.COMPLETED` | Incasso confermato: la prenotazione diventa `paid`, se non lo è già |
| altri, ordini sconosciuti | Ignorati, con risposta 204 perché PayPal non ripeta l'invio |

Su PayPal va registrato il webhook `https://<host>/api/v1/webhooks/paypal` con questi due eventi.

## 3. Casi particolari

| Caso | Esito |
|---|---|
| Da pagare in parcheggio, già pagata, annullata | 409 `reservation_not_payable` |
| Tempo scaduto alla creazione dell'ordine | 409 `reservation_not_payable` |
| Cattura in ritardo (dopo `due_by`, anche già `expired`) | Si incassa **solo se il posto è ancora libero**, controllato con il lock delle prenotazioni; altrimenti 409 e nessun incasso |
| Ordine non approvato | 422 `payment_not_verified`; si riprova dopo l'approvazione |
| Importo o valuta diversi | 422 `payment_not_verified`: la prenotazione resta da pagare, l'incasso viene registrato e segnalato nel log come errore |
| Incasso arrivato via webhook su una prenotazione non più pagabile | Incasso registrato, prenotazione invariata, errore nel log: **serve un rimborso manuale** |
| PayPal non risponde | 502 `payment_provider_error`; si può ritentare |
| Totale 0 € (sconto del 100%) | La prenotazione nasce già `paid`, senza PayPal |

Ogni ordine è una riga di `payment_transactions`, con lo stato PayPal, l'id dell'incasso e le risposte del provider.

## 4. Configurazione

| Variabile | Valore |
|---|---|
| `ENGINE_PAYMENTS_DRIVER` | `fake` (predefinito: PayPal simulato) oppure `paypal`. **`fake` è bloccato in produzione.** |
| `PAYPAL_MODE` | `sandbox` oppure `live` |
| `PAYPAL_CLIENT_ID`, `PAYPAL_SECRET` | Credenziali dell'app REST su developer.paypal.com |
| `PAYPAL_WEBHOOK_ID` | Id del webhook registrato; senza, tutte le notifiche vengono rifiutate |

Con il driver `fake` gli ordini sono già approvati, così il flusso si prova in locale senza la finestra di PayPal.

## 5. Limiti noti

- Due prenotazioni in attesa possono usare insieme l'ultimo utilizzo di un codice sconto: gli utilizzi contano solo le prenotazioni confermate o pagate.
- I rimborsi non sono ancora gestiti: per ora il caso viene solo registrato. Arriveranno con le cancellazioni (fase 8).
