# Regole di calcolo del prezzo

Codice: `app/Engine/Domain/Pricing` (puro, senza database). Unico punto di ingresso: `QuoteCalculator::quote()`.
Test: `tests/Engine/Unit/Pricing`, compreso il golden test sui casi reali del legacy.

Tutti gli importi sono in **centesimi interi** (`Money`). Gli arrotondamenti sono al centesimo, con la metà per eccesso.

## 1. Durata della sosta

La durata si misura **sull'orologio** nel fuso `Europe/Rome`: 08:00 → 08:00 del giorno dopo vale 24 ore anche a cavallo del cambio dell'ora legale. Il legacy usava i minuti reali; nei dati storici non c'è nessun caso in cui la differenza conti.

| Sosta | Giorni |
|---|---|
| meno di 24 ore | 1 |
| 24h00 | 1 |
| 24h15 | 1 (tolleranza: 15 minuti esatti non fanno scattare il giorno) |
| 24h16 | 2 |
| 71h00 (3 giorni meno un'ora) | 3 |
| 30 giorni + 16 minuti | 31 = 30 compresi nel listino + 1 giorno in più |

Limiti: il rientro deve essere successivo alla partenza e la sosta non può superare i 365 giorni.

## 2. Parcheggio (listino)

Un listino contiene il **prezzo totale** per 1, 2, … 30 giorni, più il prezzo di **ogni giorno oltre il 30°**. Tutte le 30 righe sono obbligatorie.

- Il listino si sceglie in base alla **data di inizio sosta**.
- `parcheggio = totale[min(giorni, 30)] + giorni_oltre_30 × prezzo_giorno_aggiuntivo`
- Se per quella data non esiste un listino, si usa quello della stessa data dell'anno precedente, risalendo fino a 5 anni. Il 29/02 diventa il 28/02. Il preventivo registra il fallback in `PricingTrace`.

Esempio con il listino di novembre 2026 del legacy (1 giorno 17 €, 3 giorni 27 €, 7 giorni 47 €, 30 giorni 165 €, giorno aggiuntivo 10 €):

| Sosta | Calcolo | Parcheggio |
|---|---|---|
| 3 giorni | totale[3] | 27,00 € |
| 32 giorni | totale[30] + 2 × 10 € | 185,00 € |

## 3. Servizi aggiuntivi

| Servizio | Tipo | Prezzo | Quantità |
|---|---|---|---|
| `covered_parking` posto coperto | percentuale | 40% del parcheggio, giorni oltre il 30° compresi | giorni fatturati |
| `exterior_wash` / `interior_wash` lavaggi | fisso | da impostazioni (15 €) | 1 |
| `key_drop` tieni le chiavi | fisso | da impostazioni (7 €) | 1 |
| `ev_charging` / `tyre_repair` | informativo | 0 €, con messaggio | 1 |

- Posto coperto e Tieni le chiavi **non si possono scegliere insieme** (`extras_incompatible`).
- Un servizio disattivato dal gestore viene rifiutato (`extra_unavailable`).
- Con il posto coperto, la riga del parcheggio si chiama "Parcheggio" invece di "Parcheggio scoperto".

## 4. Commissione di pagamento

Il pagamento in parcheggio (`on_site`) aggiunge una riga fissa di 1 €. Il pagamento online non ha commissioni.

## 5. Sconti

Lo sconto si applica al **subtotale di tutte le righe**, commissione compresa, come nel legacy.

- **Percentuale**: `totale = subtotale × (100 − p) / 100`, arrotondato al centesimo.
- **Importo fisso**: `sconto = min(importo, subtotale)`.
- Il totale resta sempre tra 0 e il subtotale.
- Il codice si confronta senza distinguere maiuscole e spazi.
- Un codice inesistente, scaduto (dopo l'ultimo giorno di validità) o esaurito **non blocca** il preventivo: viene calcolato senza sconto, con il motivo.

## 6. Esempio completo

3 giorni a novembre 2026 con posto coperto, pagamento in parcheggio e codice al 10%:

| Riga | Importo |
|---|---|
| Parcheggio, 3 giorni | 27,00 € |
| Posto coperto (+40%), 3 giorni | 10,80 € |
| Pagamento in parcheggio | 1,00 € |
| **Subtotale** | **38,80 €** |
| Sconto 10% | −3,88 € |
| **Totale** | **34,92 €** |

## 7. Differenze volute rispetto al legacy

| Legacy | Motore |
|---|---|
| Listini mensili nella tabella `exceptions`; nei mesi corti, 29–31 giorni andavano in overflow sul mese successivo (es. 29 giorni da febbraio = prezzo di 1 giorno a marzo) | Listini sempre completi da 1 a 30 giorni: l'overflow non è più possibile |
| Durata in minuti reali (ora legale) | Durata sull'orologio |
| 10 € fissi nel codice per ogni giorno oltre il 30° | Prezzo del giorno aggiuntivo nel listino |
| Totale scontato a 0 → ricadeva sul totale pieno; sconto fisso poteva dare totali negativi | Totale tra 0 e il subtotale, lo 0 è ammesso |
| `quantita_massima` mai applicata | `max_uses` applicato (`null` = illimitato) |
| Importi `float` | Centesimi interi |
