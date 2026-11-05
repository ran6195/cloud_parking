# Regole di disponibilità

Codice: `app/Engine/Domain/Availability` (puro). Il punto di ingresso è `AvailabilityChecker::check()`.
I dati arrivano da `app/Engine/Infrastructure/Availability/DatabaseAvailabilityData`, che usa una query aggregata per intervallo.
Test: `tests/Engine/Unit/Availability` e `tests/Engine/Feature/Availability`. Questi ultimi includono l'equivalenza con l'algoritmo giorno per giorno del legacy.

Le date sono **locali `Europe/Rome`**. Una sosta dal 20/11 alle 08:00 al 23/11 alle 18:00 ha consegna il 20 e ritiro il 23.

## 1. Chi occupa un posto

Una prenotazione occupa un posto **dal giorno di consegna incluso al giorno di ritiro escluso**, cioè `start_date <= giorno < end_date`. Questo vale solo se:

- non è cancellata (soft delete);
- è in stato `confirmed`, `paid` o `completed`;
- oppure è in stato `pending_payment` e il termine di pagamento (`payment_due_by`) non è ancora passato. Così due clienti non possono prenotare l'ultimo posto mentre il primo sta pagando. Il termine è obbligatorio per vincolo del database.

Le prenotazioni `cancelled` ed `expired` non occupano posti.

Differenza dal legacy: il legacy contava le prenotazioni con la ricevuta generata e in stato diverso da `iniziata`. Il motore usa solo lo stato, perché la ricevuta potrà essere generata in coda.

## 2. Parcheggio

Si controllano i giorni **dalla consegna al ritiro, entrambi inclusi**.

1. Se un giorno è nelle **date bloccate**, la risposta è `blocked_date`, con l'elenco dei giorni bloccati.
2. Con il **blocco automatico** attivo, un giorno con posti occupati ≥ posti totali dà `capacity_full`. Senza blocco automatico, i posti totali non si controllano.

## 3. Servizi a capacità limitata (posto coperto, Tieni le chiavi)

Si controllano i giorni **dalla consegna al ritiro escluso**.

- Se il servizio è disattivato o ha capacità 0, la risposta è `service_disabled`.
- Se un giorno ha posti occupati (prenotazioni con quel servizio) ≥ capacità, la risposta è `capacity_full`.
- Per una sosta nello stesso giorno non c'è nessun giorno da controllare, quindi il servizio risulta sempre disponibile, come nel legacy.

Nel risultato compaiono solo i servizi a capacità limitata richiesti.

> Il giorno di ritiro conta per le date bloccate e per i posti totali, ma non per i servizi a capacità: è il comportamento del legacy. Per ora resta così, e lo si verificherà in fase di test (decisione del 2026-11-05).

## 4. Esempio

Con 2 posti totali (blocco automatico attivo) e 1 posto coperto, ci sono già queste prenotazioni:

- A: 20/11 → 25/11, con posto coperto (occupa dal 20 al 24)
- B: 22/11 → 23/11 (occupa il 22)

Una richiesta dal 21/11 alle 08:00 al 22/11 alle 20:00, con posto coperto, dà questo risultato:

| | Giorni controllati | Esito |
|---|---|---|
| Parcheggio | 21, 22 | il 22 i posti sono 2 su 2 → `capacity_full` (22/11) |
| Posto coperto | 21 | il 21 il posto è occupato da A → `capacity_full` (21/11) |
