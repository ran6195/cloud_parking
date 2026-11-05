# Pannello del gestore (/motore)

Codice: `app/Engine/Filament`. Test: `tests/Engine/Feature/Panel`.

È un pannello Filament separato dall'admin del CMS (`/admin`):
- ha utenti propri, nella tabella `engine.admins` e con il guard `engine`;
- un utente del CMS non entra in `/motore`, e un utente del motore non entra in `/admin`.

```
php artisan engine:admin gestore@example.com --name="Gestore"   # chiede la password (almeno 12 caratteri)
```

Lo stesso comando, con un'email esistente, cambia la password.

## Sezioni

| Sezione | Cosa si fa |
|---|---|
| **Dashboard** | auto in parcheggio oggi rispetto ai posti, consegne e ritiri del giorno, pagamenti in attesa, rimborsi da fare; occupazione dei prossimi 14 giorni (parcheggio, posto coperto, Tieni le chiavi, giorni bloccati) |
| **Prenotazioni** | elenco con filtri (stato, periodo di sosta, rimborsi da fare) e ricerca (numero, targa, cliente). Nel dettaglio: righe, pagamento, cancellazione e rimborso. Azioni: annulla (anche oltre la finestra del cliente, con o senza email), rimborsa su PayPal, segna come rimborsata a mano, segna come conclusa, reinvia la conferma. Si modificano veicolo, voli, passeggeri e note |
| **Clienti** | ricerca e correzione dei contatti (uno per email) |
| **Listini** | creazione e modifica con i 30 prezzi totali e il giorno aggiuntivo, in euro. **Duplica** copia un listino su un nuovo periodo con una variazione in %. I periodi sovrapposti vengono rifiutati |
| **Codici sconto** | percentuale o importo fisso, utilizzi massimi, scadenza; mostra gli utilizzi effettivi |
| **Date bloccate** | un giorno o un periodo intero; di default mostra solo da oggi in poi |
| **Simulatore preventivo** | lo stesso calcolo dell'API, con listino usato, eventuale fallback sull'anno precedente e disponibilità. Non salva nulla |
| **Impostazioni** | posti e blocco automatico, commissione in parcheggio, tempo per pagare online, ore di cancellazione gratuita, rimborsi manuali o automatici, contatti e nome nelle email, servizi aggiuntivi (attivo, nome, prezzo o percentuale, capacità, messaggio) |
| **Client API** | crea un client (la chiave segreta compare una volta sola), modifica le origini e il limite, revoca |

## Non ancora disponibile

- **Cambiare date o servizi** di una prenotazione: richiede di ricalcolare prezzo e disponibilità. Per ora si annulla e se ne crea una nuova.
- **Prenotazione da sportello o telefono**: per ora si usa il form di un'interfaccia.
