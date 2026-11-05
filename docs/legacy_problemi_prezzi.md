# Problemi del motore di costo legacy (da risolvere)

Emersi il 2026-10-31 dall'analisi del codice (`nuovo_prenota/backend/app/Http/Controllers/CalcoliController.php`)
e del dump del DB. Tutti vanno risolti nel nuovo motore; ✅ = già risolto in fase 2, ❓ = serve una decisione.

| # | Problema nel legacy | Stato nel nuovo motore |
|---|---|---|
| 1 | I `price_lists` sono tutti cancellati: i prezzi reali stanno nella tabella `exceptions`, usata come listino mensile (riga del giorno N = prezzo per N giorni). Il "fallback" è in realtà la fonte principale. | ✅ listini veri e completi; l'import da `exceptions` arriva in fase 3 |
| 2 | Mesi corti: `Carbon::create(anno, mese, durata)` va in overflow, quindi 29 giorni da febbraio = prezzo di 1 giorno di marzo (stesso problema in aprile, giugno, settembre e novembre per 31 giorni). | ✅ impossibile con listini 1–30 obbligatori; all'import i giorni mancanti proseguono l'ultimo incremento (febbraio 2026: 28 gg 155 € → 29 gg 160 €, 30 gg 165 €) |
| 3 | Durata in minuti reali: a cavallo del cambio dell'ora legale, 08:00 → 08:00 può valere 2 giorni. | ✅ durata sull'orologio |
| 4 | 10 € al giorno oltre il 30° scritti nel codice. | ✅ prezzo del giorno aggiuntivo nel listino |
| 5 | Prezzi `float`. | ✅ centesimi interi |
| 6 | Il totale lo calcola il client (PWA): il backend somma le righe inviate e sovrascrive solo alcuni extra. | ✅ in fase 5/6 il server ricalcola tutto e si prenota da un `quote_id` |
| 7 | Un totale scontato pari a 0 ricade sul totale pieno; uno sconto fisso può dare totali negativi. | ✅ totale tra 0 e il subtotale |
| 8 | `quantita_massima` degli sconti mai applicata (il codice attivo: 228 utilizzi su 100) e 0 usato come "illimitato"; `quantita_utilizzata` sempre vuota. | ✅ `max_uses` applicato · ❓ cosa fare all'import con i codici già oltre il limite |
| 9 | 385 prenotazioni scontate senza `discount_id` e alcune con uno sconto non riconducibile alla formula. | ❓ da capire (modifiche manuali dal pannello?) |
| 10 | Prezzi di gennaio 2027 assenti (`costo` NULL): il legacy ripiega silenziosamente sul 2026. | ✅ il fallback resta, ma viene registrato nel preventivo · ❓ avvisare il gestore dei listini mancanti |
| 11 | Stati incoerenti: il codice scrive `conclusa`, che non è nell'ENUM originale; 145 prenotazioni con stato NULL; `confermata` solo da giugno 2026. | ❓ significato di `confermata`; mappatura proposta nel piano (sezione 7) |
| 12 | 55 prenotazioni con quantità di giorni diversa dalla regola, probabilmente date modificate senza ricalcolo. | ✅ dal pannello non si possono cambiare date o servizi senza passare dal calcolo (per ora: annulla e ricrea); il cambio con ricalcolo è ancora da fare |
| 13 | Posto coperto e "Tieni le chiavi" esclusivi solo nella UI. | ✅ vincolo anche lato server |
| 14 | Errore di battitura nei prezzi: giugno 2025, 21 giorni = 2.190 € (20 gg 180 €, 22 gg 195 €). | ❓ importato così com'è e segnalato dall'import come anomalia; da correggere a mano (190 €?) |
