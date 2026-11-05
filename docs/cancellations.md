# Cancellazioni e rimborsi

Codice: `app/Engine/Domain/Booking/CancellationPolicy.php` (regole), `app/Engine/Application/CancellationService.php` e `RefundService.php`.
Test: `tests/Engine/Unit/Booking/CancellationPolicyTest.php` e `tests/Engine/Feature/Cancellations`.

## 1. Regole

- Il cliente annulla con il **token** dell'email di conferma: `GET /api/v1/cancellations/{token}` mostra l'anteprima, `POST` annulla.
- Il token è il segreto e vale da qualsiasi interfaccia. Per limitare i tentativi a caso c'è un limite stretto di richieste (10 al minuto per client e IP).
- La cancellazione è gratuita **fino a 24 ore prima della consegna**, estremo incluso (impostazione `cancellation.free_hours_before_start`). Con consegna il 20/12 alle 08:00 si annulla fino al 19/12 alle 08:00: 24h01 prima sì, 23h59 no. La regola vale per ogni metodo di pagamento.
- Si possono annullare le prenotazioni `pending_payment`, `confirmed` e `paid`.

| Caso | Errore 409 |
|---|---|
| Già annullata | `already_cancelled` |
| Oltre la scadenza | `cancellation_window_closed`, ad es. "La cancellazione gratuita era possibile fino al 19/12/2026 alle 08:00." |
| Scaduta o conclusa | `not_cancellable_status` |

Dopo la cancellazione il posto torna libero. Il cliente riceve la conferma e il gestore un avviso (se `contact.email` è impostato).

## 2. Rimborsi

Una prenotazione **pagata online** richiede i dati per il rimborso: `refund.tax_code`, cioè il codice fiscale (anche omocodico) o la partita IVA di chi ha pagato, ed eventuali note.

Impostazione `refunds.mode`:

| Modalità | Cosa succede |
|---|---|
| `manual` (predefinita) | La richiesta viene registrata (`requested`). Il gestore riceve un'email con oggetto "RIMBORSO DA FARE", rimborsa da PayPal e poi la segna come fatta |
| `automatic` | Il motore rimborsa subito su PayPal l'intero importo (`refunded`). Se PayPal rifiuta o non risponde, la cancellazione resta valida e il rimborso passa a `failed`, da completare a mano |

Lo stato è in `reservations.refund`, insieme a importo, codice fiscale, note, date, id del rimborso PayPal ed eventuale errore. A rimborso fatto, `payment.status` della prenotazione diventa `refunded`.

## 3. Comandi per il gestore

```
php artisan engine:refunds                          # elenca i rimborsi da fare (requested e failed)
php artisan engine:refunds 22171 --automatic        # rimborsa su PayPal
php artisan engine:refunds 22171 --mark-refunded --note="Rimborsato dal pannello PayPal"
```

Con il pannello Motore (fase 9) le stesse azioni saranno disponibili anche da interfaccia.
