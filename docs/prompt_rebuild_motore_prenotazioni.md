# Prompt: ricostruzione motore tariffe e gestione prenotazioni CCParking (Laravel 13 + Filament su Laravel Cloud)

## Ruolo e obiettivo

Sei un senior Laravel developer. Devi ricostruire, in un **nuovo progetto Laravel 13** con **pannello admin Filament** (ultima versione stabile compatibile con Laravel 13: verificala prima di installare) da deployare su **Laravel Cloud**, due sottosistemi di un'applicazione esistente di prenotazione parcheggi aeroportuali (CCParking, Bologna):

1. **Motore di calcolo del costo** della prenotazione (tariffa base, posto coperto, servizi extra, sconti, totale).
2. **Gestione della prenotazione**: verifica disponibilità, creazione, stati, pagamento, modifica, cancellazione, più l'amministrazione di tutto questo in Filament.

Il sistema legacy (Laravel 8) resta la fonte di verità sul comportamento: le regole sotto ne sono l'estrazione. **Replica fedelmente il comportamento di calcolo**, salvo i punti marcati come "DECISIONE APERTA", dove devi fermarti e chiedere prima di scegliere.

Fuori scope (non implementare, ma lascia punti di estensione): parsing email IMAP dai partner (Parkos, MyParking, ParkingMyCar), statistiche/report, richieste recensioni Google, tracking del wizard, generazione grafica del PDF ricevuta (prevedi solo un'interfaccia `ReceiptGenerator` con un'implementazione stub).

## Vincoli tecnici

- PHP 8.3+, Laravel 13, Filament, Pest per i test, Larastan livello ≥ 6, Laravel Pint.
- Database: MySQL su Laravel Cloud. Usa colonne `decimal(10,2)` per gli importi e calcola **in centesimi interi** (o con `brick/money`) dentro il motore: niente float per i soldi.
- **Laravel Cloud**: il filesystem locale è effimero → ricevute/PDF su disco S3-compatibile (Laravel Cloud Object Storage) tramite `Storage::disk(config('filesystems.receipts'))`. Email e lavori lenti in coda (`ShouldQueue`), con worker configurato su Cloud. Configurazione solo da variabili d'ambiente, nessun segreto nel repo.
- Architettura: logica di dominio in classi dedicate (`app/Domain/Pricing`, `app/Domain/Booking`), **non nei controller né nelle risorse Filament**. Controller API sottili, Form Request per la validazione, DTO/value object immutabili (`StayPeriod`, `Money`, `Quote`, `QuoteLine`), enum PHP per stati e servizi.
- Timezone applicativa `Europe/Rome`.

## API pubblica da esporre (consumata da una PWA esistente: mantieni i contratti JSON)

Prefisso `/api`. Le date arrivano come `YYYY-MM-DD`, gli orari come `HH:mm` (default `08:00` per l'inizio e `20:00` per la fine se assenti).

| Metodo | Path | Input | Output |
|---|---|---|---|
| POST | `calcolo/costo_base` | `data_inizio, ora_inizio, data_fine, ora_fine` | `[{nome:'feriale', servizio:'Giorni Parcheggio Scoperto', costo, quantita}]` |
| POST | `calcolo/costo_coperto` | come sopra | `[{id:'coperto', descrizione:'Posto Coperto', costo_totale, quantita}]` |
| POST | `calcolo/costo_servizio` | `servizio` | `{nome, descrizione, costo, tipo_costo:'singolo'}` |
| POST | `disponibilita/base` | `data_inizio, data_fine` | `{disponibile: bool}` |
| POST | `disponibilita/coperto` | date + orari | `{disponibile: bool}` |
| POST | `disponibilita/tieni_le_chiavi` | date + orari | `{disponibile: bool}` |
| GET | `extras` | — | lista extra attivi (vedi sotto) |
| POST | `sconti/verifica` | `codice` | `{valido, codice:{...}|null, motivo?}` |
| POST | `prenotazione` | `data_inizio, ora_inizio, data_fine, ora_fine, dati_customer{nome_completo, email, telefono, ...}, prenotazione{targa, marca, modello, volo, ritorno, passeggeri, note, in_aeroporto?, ricarica?, riparazione_gomme?}, tabella_costi[], sconto{}, ref?` | `{id, email_inviata}` oppure 422 `{error:'date_bloccata', message}` |
| POST | `transazione` | `prenotazione_id, dati_ordine` (payload PayPal) | `{ok, dati_ordine, email_inviata}` |

Aggiungi inoltre un endpoint nuovo **`POST /api/preventivo`**, che restituisce il preventivo completo calcolato dal server (righe + totale + totale scontato) a partire da date, orari, extra selezionati e codice sconto. Diventerà la fonte unica del prezzo (vedi Decisione aperta 1).

## Regole del motore di calcolo (comportamento legacy da replicare)

### 1. Durata della sosta
- Inizio `d1` = data_inizio + ora_inizio, fine `d2` = data_fine + ora_fine; `minuti = d2 - d1`.
- Se `minuti < 1440` → `durata = 1` giorno.
- Altrimenti `durata = floor(minuti / 1440)`, e **+1 se il resto supera 15 minuti** (tolleranza: 15 minuti esatti non fanno scattare il giorno extra).
- Se `durata > 30`: `giorni_in_piu = durata - 30`, `durata = 30`.
- La quantità esposta al cliente è `durata + giorni_in_piu`.

### 2. Tariffa base (parcheggio scoperto)
Fonte primaria: **listini** (`price_lists`: `start_date`, `end_date`, soft delete) con righe `prices` (`price_list_id`, `numero_giorni` 1..31, `price`).
- `price` per `numero_giorni = N` è il **prezzo totale forfettario per N giorni** (non un prezzo al giorno).
- La riga `numero_giorni = 31` è il **prezzo per ogni giorno oltre il 30°**.
- Il listino si sceglie in base alla **data di inizio sosta** (`start_date <= d1 <= end_date`).
- `tariffa = price[durata] + giorni_in_piu × price[31]`.
- **Fallback sugli anni**: se per la data non esiste un listino, oppure il listino non ha la riga per `durata` (o la riga 31 quando servono giorni in più), riprova con la stessa data spostata indietro di 1, 2, … fino a **5 anni** (spostamento "no overflow": il 29/02 diventa 28/02).
- **Fallback legacy** se nessun listino è utilizzabile: tabella `exceptions` (`data`, `costo`), dove la riga con data `(anno, mese_di_inizio, giorno = durata)` contiene il prezzo forfettario per quella durata in quel mese; anche qui si risale fino a 5 anni indietro. Poi si aggiungono `giorni_in_piu × 10 €` (costante hard-coded). Se non si trova niente, tariffa 0. → Implementa questo fallback dietro un'interfaccia `TariffSource` separata, così si può dismettere migrando i dati `exceptions` in listini (scrivi anche un comando artisan di migrazione dei dati `exceptions` → `price_lists`).

### 3. Posto coperto
- `costo_coperto = round(tariffa_base × maggiorazione_coperto / 100, 2)`, con `maggiorazione_coperto` dalle impostazioni (default 40). È un **supplemento** che si aggiunge alla tariffa base, non la sostituisce. La quantità è uguale a quella della base.
- Coperto e "Tieni le chiavi" sono **mutuamente esclusivi**.
- Quando è presente il coperto, nell'elenco servizi della ricevuta va tolta la parola "Scoperto".

### 4. Servizi extra (prezzo fisso, quantità 1)
Le impostazioni chiave/valore (`impostazioni`: `chiave` PK string, `valore` string) controllano attivazione e prezzo:

| id | attivo se | costo | tipo |
|---|---|---|---|
| `coperto` | `posti_coperti > 0` | calcolato (punto 3) | prezzo_calcolato |
| `lavaggio` | `lavaggio_attivo = '1'` | `lavaggio_costo` | servizio |
| `lavaggio_interni` | `lavaggio_interni_attivo = '1'` | `lavaggio_interni_costo` | servizio |
| `tieni_le_chiavi` | `tieni_le_chiavi_attivo = '1'` | `tieni_le_chiavi_costo` | servizio |
| `ricarica` | `ricarica_attivo = '1'` | 0, mostra `ricarica_messaggio` | informativo |
| `riparazione_gomme` | `riparazione_gomme_attivo = '1'` | 0, mostra `riparazione_gomme_messaggio` | informativo |
| `in_aeroporto` (pagamento in parcheggio) | sempre | `in_aeroporto_costo` (default 1.00) | servizio |

Altre chiavi: `posti_totali` (200), `blocco_automatico` ('0'/'1'), `posti_coperti`, `tieni_le_chiavi_quota`, `avviso_veicolo_attivo`. Gestiscile con una classe `Settings` tipizzata e cache-ata (invalidazione al salvataggio), editabile da una pagina Filament.

### 5. Sconti (`discounts`, soft delete)
Campi: `codice` (confronto case-insensitive, normalizzato in maiuscolo), `tipo_sconto` (`perc` | importo fisso), `percentuale_sconto`, `totale_sconto`, `quantita_massima` (nullable), `valido_fino` (nullable).
- Verifica: codice inesistente → `{valido:false, motivo:'non esiste'}`; `quantita_massima < 0` → `motivo:'terminato'`; `valido_fino` passato → `motivo:'scaduto'`; altrimenti valido.
- Applicazione sul totale lordo `T` (somma di tutte le righe, extra compresi): `perc` → `T × (100 − p) / 100`; fisso → `T − importo`.
- La prenotazione salva `totale` (lordo), `totale_scontato` e `discount_id`.

## Regole di disponibilità

- **Date bloccate** (`date_bloccate.data`): se un giorno qualsiasi nell'intervallo **[data_inizio, data_fine] estremi inclusi** è bloccato → non disponibile.
- **Blocco automatico** (se `blocco_automatico = '1'`): per ogni giorno dell'intervallo, occupazione = prenotazioni con `data_partenza <= giorno` − prenotazioni con `data_arrivo <= giorno`, contando solo prenotazioni **non cancellate, con ricevuta generata e stato ≠ 'iniziata'**. Se occupazione ≥ `posti_totali` → non disponibile.
- **Coperto / Tieni le chiavi**: capacità `posti_coperti` / `tieni_le_chiavi_quota` (0 = servizio non disponibile). Per ogni giorno nell'intervallo **[inizio, fine)** (giorno di fine escluso) conta le prenotazioni valide (stessi filtri di sopra) che hanno quel servizio fra i dettagli e coprono il giorno (`data_partenza <= g < data_arrivo`); se il conteggio ≥ capacità → non disponibile.
- Implementa il calcolo con **una query aggregata per intervallo**, non una query per giorno, e copri con test le equivalenze rispetto all'algoritmo giorno per giorno.
- La creazione della prenotazione deve **ricontrollare la disponibilità lato server**, in una transazione con lock adeguato (evita overbooking concorrente), e rispondere 422 `date_bloccata` con il messaggio: *"Le date selezionate non sono più disponibili. Contatta CCParking per verificare la disponibilità residua: 051 41 133 31."*

## Ciclo di vita della prenotazione

Modello: `Customer` (soft delete) 1–N `Reservation` (soft delete) 1–N `ReservationLine` (ex `reservation_details`: `servizio`, `descrizione`, `quantita`, `costo_unitario`, `costo_totale`), più `Reservation` 1–1 `PaymentTransaction` (ex `paypal_transactions`: payload JSON) e `Reservation` N–1 `Discount`.

Campi `Reservation`: `data_partenza`, `ora_partenza`, `data_arrivo`, `ora_arrivo` (nota: "partenza" = inizio sosta, "arrivo" = rientro del cliente), `targa`, `marca`, `modello`, `volo`, `ritorno`, `passeggeri`, `note`, `stato`, `pagata`, `totale`, `totale_scontato`, `ricevuta` (path del file), `id_fornitore` (referral `?ref=`), `cancellation_token` (64 hex, generato alla creazione), `codice_fiscale_rimborso`. `Customer`: `nome`, `cognome`, `ragione_sociale`, `email`, `telefono`, `indirizzo`, `citta`, più i dati fiscali (accetta P.IVA europee).

Flusso:
1. **Creazione** (`POST /api/prenotazione`):
   - `nome_completo` → `nome` = prima parola, `cognome` = resto.
   - Crea sempre un nuovo Customer.
   - Crea la Reservation in stato `iniziata`, con una riga per ogni voce con quantità > 0. Le voci informative (`ricarica`, `riparazione_gomme`) si salvano come righe a costo 0 se richieste.
   - Le righe extra a prezzo fisso **usano sempre il prezzo dalle impostazioni**, mai quello inviato dal client.
   - Accoda alle note la lista dei servizi extra nel formato `- Descrizione`.
   - Genera la ricevuta.
   - Se `in_aeroporto` → stato `da_pagare` e invio delle email di conferma (cliente + indirizzi interni configurabili da env).
2. **Pagamento PayPal** (`POST /api/transazione`): salva il payload, imposta `pagata = true` e lo stato "pagata/conclusa", invia le email. **Verifica l'ordine lato server** con le API PayPal (importo = `totale_scontato`, stato COMPLETED) prima di confermare. Rendi l'operazione idempotente.
3. **Modifica (admin)**: date, orari, veicolo, volo, note, importo; ricalcola la quantità giorni della riga base e rigenera la ricevuta.
4. **Cancellazione pubblica** via token: consentita solo se non già cancellata e mancano **più di 24 ore** all'inizio sosta (`data_partenza + ora_partenza − 24h`); altrimenti stato `scaduta`. La cancellazione = soft delete + email al cliente e a info@, con motivazione e dati fiscali per il rimborso.
5. Un errore nell'invio email **non deve mai** far fallire la creazione o il pagamento: logga e restituisci `email_inviata: false`.

Modella gli stati con un enum PHP e una macchina a stati esplicita (transizioni consentite testate).

## Pannello Filament

- **Prenotazioni**:
  - lista con filtri (stato, intervallo date, pagata, con coperto/tieni le chiavi, referral) e ricerca per targa, cognome, email, id;
  - vista dettaglio con righe, transazione, cliente;
  - azioni: modifica (con ricalcolo preventivo tramite il motore e conferma del nuovo importo), segna come pagata, reinvia email, scarica ricevuta, cancella/ripristina.
- **Clienti** (con blocco dell'eliminazione se hanno prenotazioni).
- **Listini prezzi**: CRUD del listino con editor tabellare delle 31 righe (1–30 giorni + "oltre 30"), duplicazione di un listino su un nuovo periodo, validazione di non sovrapposizione dei periodi.
- **Sconti**: CRUD.
- **Date bloccate**: vista calendario o lista con blocco/sblocco rapido.
- **Impostazioni**: pagina unica per extra, prezzi, capacità, blocco automatico.
- **Simulatore preventivo**: pagina che, date le date, gli orari, gli extra e lo sconto, mostra il dettaglio del calcolo (listino usato, eventuale fallback, durata, giorni in più, righe). Usa lo stesso servizio dell'API.
- Dashboard: widget con occupazione dei prossimi 14 giorni rispetto a `posti_totali`, coperti e tieni le chiavi.

## Test obbligatori (Pest)

Test unitari puri del motore (senza DB, con `TariffSource` fake) e feature test delle API. Includi almeno:
- durata: 23h59 → 1; 24h00 → 1; 24h15 → 1; 24h16 → 2; 48h → 2; 30g + 16min → 31 → durata 30 + 1 giorno in più;
- listino: prezzo per N giorni, giorni oltre il 30° con la riga 31, riga mancante → fallback all'anno precedente, nessun listino → fallback `exceptions` + 10 €/giorno, 29/02 nel fallback;
- coperto: 40% di una base che include i giorni in più, arrotondamento a 2 decimali;
- sconti: percentuale, fisso, scaduto, terminato, inesistente, codice in minuscolo;
- disponibilità: data bloccata sul giorno di arrivo (bloccante), capacità coperto piena il giorno di arrivo (non bloccante), prenotazioni `iniziata` / senza ricevuta / cancellate ignorate;
- creazione: prezzi degli extra sovrascritti dal server, voci informative salvate a costo 0, 422 su date bloccate, email che falliscono → prenotazione creata comunque;
- cancellazione: a 24h01 sì, a 23h59 no;
- **golden test**: prepara una fixture con 20–30 casi reali (date, orari, extra, sconto → totale atteso) calcolati con il sistema legacy, e verifica che il nuovo motore dia gli stessi risultati al centesimo. Chiedimi i dati se non li hai.

## Migrazione dati

Scrivi comandi artisan idempotenti per importare dal DB legacy MySQL (connessione `legacy`): clienti, prenotazioni (comprese le soft-deleted), righe, transazioni, sconti, listini, prezzi, `exceptions`, date bloccate, impostazioni. Mappa gli stati legacy sul nuovo enum, mostra a fine import un report di record scartati o anomali, e prevedi la modalità `--dry-run`.

## DECISIONI APERTE: chiedi prima di implementare

Nel legacy ho trovato questi comportamenti sospetti. Per ciascuno proponi la soluzione e attendi conferma:

1. **Totale calcolato dal client**: oggi il backend somma `costo_totale` delle righe inviate dalla PWA e lo usa come prezzo (sovrascrive solo lavaggio, lavaggio interni e tieni le chiavi). Proposta: il server ricalcola sempre tutto con il motore e ignora gli importi del client (eventualmente rifiutando una discrepanza oltre 0,01 €).
2. **Sconto al 100% o che azzera il totale**: per come è scritto il codice, un totale scontato pari a 0 ricade sul totale pieno. Inoltre uno sconto fisso può rendere il totale negativo. Proposta: limitare il totale tra 0 e T, e permettere lo 0.
3. **`quantita_massima` degli sconti** non viene mai decrementata all'uso, e il controllo considera valido qualsiasi valore ≥ 0. Va definita la semantica (utilizzi residui? 0 = esaurito?) e il momento del decremento (alla creazione o al pagamento).
4. **Stati**: il codice scrive `conclusa`, ma l'ENUM della colonna nel DB contiene `bozza, iniziata, da_pagare, confermata, pagata, annullata, completata` (non `conclusa`). Verifica sui dati di produzione cosa è stato salvato davvero, poi definisci l'enum definitivo.
5. **Coperto ↔ Tieni le chiavi** sono esclusivi solo nella UI: la proposta è di imporre il vincolo anche lato server.
6. **Intervalli di disponibilità incoerenti**: le date bloccate includono il giorno di rientro, i coperti no. Va confermato se è voluto.
7. **Costante 10 €/giorno** nel fallback `exceptions`: renderla un'impostazione, oppure eliminare del tutto il fallback dopo la migrazione dei dati.
8. **Un nuovo Customer per ogni prenotazione** (nessuna deduplica per email): mantenere o deduplicare?

## Modo di lavorare

1. Prima di scrivere codice, presenta un piano: struttura delle cartelle, schema DB, interfacce del motore, elenco dei test, risposte proposte alle Decisioni aperte.
2. Procedi a piccoli passi verificabili: motore e test unitari → schema e modelli → API con feature test → Filament → migrazione dati → configurazione Laravel Cloud (`cloud.yaml` o impostazioni equivalenti, worker coda, scheduler, disco S3, variabili d'ambiente documentate in `.env.example`).
3. Ogni passo deve finire con test verdi, Pint e Larastan puliti.
4. Tieni un `CHANGELOG.md` dei file creati/modificati e un `docs/pricing.md` che spiega le regole di calcolo con esempi numerici.
5. Non fare commit senza chiedere.
