# Associazione (correzione forma giuridica, campi RASD, scadenza affiliazione)

**Stato:** in sviluppo (schema consegnato)
**Introdotto in:** v0.14.0 (2026-09-22)
**File coinvolti:**
- Migration: `2026_09_22_000078` (alter gl_associazione)
- Model aggiornato: `Associazione.php`

## 1. Logica

`gl_associazione` è la tabella singleton di configurazione generale
dell'associazione (pattern `Associazione::istanza()` =
`firstOrCreate(['id' => 1])`, vedi `MANUALE-CORE.md` §4). Questa
revisione nasce da una domanda esplicita di verifica di completezza
("è rimasto fuori qualcosa?") che ha portato a controllare
sistematicamente tutte le tabelle del core mai esaminate direttamente
in questa sessione — `gl_associazione` è risultata l'unica, tra 11
tabelle controllate, con un problema reale.

## 2. Struttura dati

**Modifiche a `gl_associazione` (esistente, alter):**
- **Corretto il default** di `forma_giuridica`: da `'Associazione di
  Promozione Sociale'` (residuo diretto del Dante) a `'Associazione
  Sportiva Dilettantistica'`.
- **Aggiunti** `rasd_numero` (string, nullable) e
  `rasd_data_iscrizione` (date, nullable) — campi per il Registro
  Nazionale delle Attività Sportive Dilettantistiche, l'unico registro
  rilevante per questa ASD (non iscritta al RUNTS).
- **Aggiunto** `rete_affiliazione_scadenza` (date, nullable) — scadenza
  dell'affiliazione a CSEN.
- **Invariati**: `runts_numero`/`runts_data_iscrizione`/`runts_sezione`
  (non applicabili a questa ASD, lasciati senza modifiche) e
  `rete_affiliazione_nome`/`_codice`/`_referente` (già generalizzati nel
  core per qualunque rete/federazione, riusati invariati per CSEN).

## 3. Comportamento / flusso

Nessun cambiamento di comportamento applicativo (nessuna UI ancora
scritta per questo modulo). Quando si configurerà l'associazione reale
(dopo la costituzione, 1/1/2028), andranno popolati `rasd_numero`,
`rasd_data_iscrizione` e `rete_affiliazione_scadenza`, oltre a
correggere manualmente `forma_giuridica` se il seeder l'ha già
impostata con un valore segnaposto (vedi §4).

## 4. Implementazione tecnica

- **`ALTER ... SET DEFAULT` per il default della colonna**: modifica
  diretta via `DB::statement`, non tramite `Blueprint` — il default di
  una colonna stringa esistente non è modificabile in modo pulito con i
  metodi standard di Laravel su alcune versioni/driver, coerente con
  l'approccio già usato per gli enum in altri moduli di questa sessione.
- **Nota per la fase di configurazione dati reali**:
  `AssociazioneSeeder.php` non è stato esaminato in questa sessione, ma
  il commento in `MANUALE-CORE.md` §5 lo descrive come seeder "con dati
  segnaposto" — probabile che imposti esplicitamente `forma_giuridica`
  con un valore letterale, nel qual caso il nuovo default a livello di
  colonna **non avrebbe effetto** (un valore esplicito nell'`INSERT`
  prevale sempre sul default). Da correggere manualmente in quel
  seeder, o al momento dell'inserimento dei dati reali.

## 5. Conformità e sicurezza

- I campi RASD/scadenza affiliazione aggiunti sostengono direttamente
  gli obblighi di compliance già descritti in `MANUALE-ASD.md` §2.1
  (l'affiliazione come scadenza critica, l'iscrizione RASD come unico
  registro rilevante).
- Nessun dato sensibile introdotto da questa modifica.

## 6. Interazioni con altri moduli

- **Compliance RASD/Affiliazione** (§4.2 di `MANUALE-ASD.md`, scheda
  `compliance-rasd.md`, v0.15.0): usa direttamente
  `rasd_numero`/`rasd_data_iscrizione`/`rete_affiliazione_scadenza`
  come dati di stato dell'affiliazione, senza duplicarli in una tabella
  propria.
- **Scadenzario** (`gl_scadenzario`, categoria `rete_affiliazione`,
  vedi scheda `scadenzario.md`): la scadenza di affiliazione ora ha
  finalmente un campo dati di riferimento (`rete_affiliazione_scadenza`)
  da cui generare la relativa riga di scadenza, quando la logica
  applicativa verrà scritta.

## 7. Storia ed evoluzione

- Nato da una domanda diretta dell'utente sul metodo di verifica
  ("come possiamo verificare?"), non da un'ipotesi di adattamento
  pianificata in anticipo.
- Metodo usato: incrocio sistematico tra l'elenco tabelle per modulo di
  `MANUALE-CORE.md` §5 e i file effettivamente esaminati in questa
  sessione, per individuare tabelle "date per buone" solo perché altre
  tabelle dello stesso modulo erano state verificate.
- Su 11 tabelle risultate mai controllate direttamente, solo
  `gl_associazione` ha rivelato un problema reale (default sbagliato,
  campi mancanti) — le altre 10 sono state confermate adeguate senza
  modifiche (vedi `MANUALE-ASD.md` §6.11).
