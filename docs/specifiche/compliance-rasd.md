# Compliance RASD / Affiliazione

**Stato:** in sviluppo (schema consegnato)
**Introdotto in:** v0.15.0 (2026-09-22)
**File coinvolti:**
- Migration: `2026_09_22_000079` (create gl_rasd_verifiche)
- Model nuovo: `RasdVerifica.php`
- Dipende da: `gl_associazione` (campi RASD/affiliazione, aggiunti in
  `associazione.md`, v0.14.0)

## 1. Logica

Modulo leggero, sostitutivo di "Compliance RUNTS" (non applicabile a
un'ASD pura, vedi `MANUALE-ASD.md` §4). Copre due esigenze distinte:
lo stato di affiliazione/iscrizione (dato singolare, che cambia
raramente) e le verifiche periodiche condotte da Sport e Salute
(evento che può ripetersi nel tempo, con esito ed eventuale
regolarizzazione).

Riferimento normativo: dal 2026 Sport e Salute conduce verifiche
sistematiche (incrocio con Anagrafe Tributaria) sulle ASD iscritte al
RASD, con **90 giorni** per regolarizzare eventuali anomalie
riscontrate (vedi `MANUALE-ASD.md` §2.1).

## 2. Struttura dati

**Nessuna tabella per lo stato di affiliazione**: già coperto da
`gl_associazione` (campi `rasd_numero`, `rasd_data_iscrizione`,
`rete_affiliazione_nome`/`_codice`/`_referente`/`_scadenza`), aggiunti
nella revisione di completezza precedente (scheda `associazione.md`).
Essendo un dato singleton (una sola associazione), non ha senso una
tabella a parte.

**Nuova tabella `gl_rasd_verifiche`**: `data_verifica`,
`anomalie_riscontrate` (testo), `scadenza_regolarizzazione` (calcolata
applicativamente come `data_verifica + 90 giorni`), `stato`
(aperta/regolarizzata/chiusa_senza_seguito), `regolarizzata_il`,
`azioni_correttive`, `note`, `gestita_da` (FK → `gl_users`).

## 3. Comportamento / flusso

1. Alla ricezione di una segnalazione di verifica da Sport e Salute, si
   apre un record in `gl_rasd_verifiche` con le anomalie riscontrate.
2. L'applicazione (non ancora scritta) potrà generare automaticamente
   una riga in `gl_scadenzario` (categoria `rete_affiliazione`, già
   esistente) con la scadenza di regolarizzazione, per comparire negli
   alert generali.
3. Una volta risolta, si valorizza `regolarizzata_il` e le
   `azioni_correttive` intraprese; lo stato passa a `regolarizzata`.

## 4. Implementazione tecnica

- **Nessuna FK diretta verso `gl_scadenzario`**: la generazione della
  riga di scadenza resta responsabilità applicativa, non del database
  — stesso principio già seguito per Bilancio (termine di approvazione)
  e Tesseramento (scadenze certificati), per non introdurre
  dipendenze rigide tra un evento specifico e una tabella generica di
  promemoria.
- Pattern della tabella (evento ripetibile con stato ed esito) ricalca
  deliberatamente `gl_gdpr_violazioni`, per coerenza di stile con un
  caso concettualmente simile già presente nel core (incidente →
  gestione → chiusura).

## 5. Conformità e sicurezza

- Riferimento normativo: verifiche sistematiche Sport e Salute dal
  2026 (incrocio con Anagrafe Tributaria), termine di regolarizzazione
  90 giorni.
- Nessun dato sensibile introdotto da questo modulo.

## 6. Interazioni con altri moduli

- **Associazione** (`gl_associazione`): fonte dei dati di affiliazione/
  iscrizione RASD, non duplicati qui.
- **Scadenzario** (`gl_scadenzario`, categoria `rete_affiliazione`):
  destinazione futura (non ancora implementata) della scadenza di
  regolarizzazione, generata lato applicazione.

## 7. Storia ed evoluzione

- Modulo rimasto "in sospeso" per gran parte della sessione (previsto
  fin dall'inizio in `bootstrap-avvio-asd.md` come possibile "non
  applicabile" se l'ASD non fosse anche APS) — risultato invece
  necessario, ma molto più leggero del previsto grazie al lavoro già
  fatto altrove.
- La maggior parte del contenuto inizialmente previsto per questo
  modulo (stato affiliazione, iscrizione RASD) si è rivelata già
  coperta da un adattamento fatto per un motivo diverso
  (`gl_associazione`, trovato durante la verifica di completezza
  finale, scheda `associazione.md`) — a dimostrazione che le sessioni
  di verifica trasversale possono anticipare lavoro previsto per dopo.
- L'unica aggiunta reale (`gl_rasd_verifiche`) nasce da un dettaglio
  normativo specifico (le verifiche Sport e Salute dal 2026) individuato
  fin dalla prima ricerca di questa sessione, ma rimasto solo annotato
  in `MANUALE-ASD.md` §2.1 senza uno schema dedicato fino ad ora.
- Con questa scheda si chiude la progettazione di tutti i moduli nuovi
  pianificati per questo derivato (Tesseramento, Volontari, Scontrini,
  Compliance RASD) e la revisione di tutti i moduli ereditati dal core.
