# Promemoria — email automatiche pianificate per tutte le funzioni

**Stato:** in uso (v0.36.0, 07/10/2026). Funzioni: «Quote non versate»;
dalla v0.38.0 «Scadenze dell'associazione» e «Tessere in scadenza», con la
modalità «N giorni prima o dopo».
**Introdotto in:** v0.36.0; modalità relativa nella v0.38.0
**File coinvolti:**
- Migration: `2026_10_07_000099_create_gl_promemoria_tables`
- Model: `Promemoria` (con l'AuditObserver), `PromemoriaInvio`
- Motore: `App\Promemoria\Funzione` (contratto), `Registro`, `Destinatario`,
  `Invii`, `Interni` (destinatari interni, v0.38.0); funzioni
  `App\Promemoria\Funzioni\QuoteNonVersate`, `ScadenzeAssociazione`,
  `TessereInScadenza`
- Comando: `promemoria:invia` (`InviaPromemoria`), ogni 15 minuti in
  `routes/console.php`
- Seeder: `PromemoriaSeeder` (in `DatabaseSeeder`); `SezioneSeeder` con
  la sezione `promemoria`
- Controller: `PromemoriaController`; viste `resources/views/promemoria/*`,
  email `emails/promemoria/messaggio`; `PromemoriaEmail`
- Menu: «Promemoria» nel gruppo Governance (`config/navigazione.php`)
- Test: in `tests/Feature/RinnoviQuotaTest.php` e `ScadenzarioTest.php`
- Tavole PR-1 e PR-2 (`docs/mockup/Tavole-rinnovi-quota.pdf`), PR-3
  (`docs/mockup/Tavole-tessere-scadenze.pdf`)

## 1. Logica

Richiesta di Paolo (06/10/2026): i solleciti delle quote sono automatici,
con una pagina per pianificare l'invio e scrivere il testo dell'email, e il
sistema deve valere **anche per altre funzioni**. Per questo un promemoria
non sa nulla delle quote: è una data, un testo e una **funzione**, che
decide chi lo riceve.

Il Gestionale APS aveva un solo comando di sollecito, che ha alcuni
problemi:
- testo fisso nel codice;
- per un errore di parametri partiva il 4 e il 5 gennaio invece che ad
  aprile e maggio;
- nessun registro degli invii, quindi doppioni possibili;
- mai al genitore dei minori;
- nomi ed email stampati nei log del cron.

## 2. Struttura dati

- `gl_promemoria`:
  - `funzione` (chiave nel codice), `nome`, `attivo`;
  - `quando`: `data_fissa` ogni anno con `giorno` e `mese`, oppure
    `relativa` con `giorni` prima (negativo) o dopo una data del
    destinatario;
  - `ora`, `parametri` (JSON, per esempio `anno_sociale`: corrente o
    successivo), `oggetto` e `testo` con i segnaposti `{nome}`, `creato_da`.
- `gl_promemoria_invii`: un invio per destinatario e periodo, unico su
  (`promemoria_id`, `periodo`, `chiave_destinatario`).
  - Colonne: `socio_id`, `email`, `esito`, `inviato_da` (per le prove),
    `inviato_il`.
  - `esito`: `inviato`, `fallito` (si ritenta), `senza_email` (da avvisare
    a mano), `prova`.

Funzioni: `App\Promemoria\Registro::FUNZIONI`. Ognuna implementa
`Funzione` con questi metodi:
- `chiave`, `nome`, `descrizione`;
- `sezione`: chi la modifica, modifica i suoi promemoria;
- `quandoAmmessi`, `parametri`, `segnaposti`;
- `periodo`: un destinatario riceve lo stesso promemoria una volta per
  periodo;
- `destinatari`;
- `predefiniti`: i promemoria pronti, creati dal seeder una volta sola.

## 3. Comportamento / flusso

**Invio** (`promemoria:invia`, ogni 15 minuti):
- per ogni promemoria attivo arrivato alla data e all'ora previste
  calcola i destinatari della funzione e toglie chi l'ha già ricevuto nel
  periodo;
- manda le email nello stile delle altre, con risposte all'indirizzo della
  Compagnia, e registra ogni invio, anche nel registro email;
- se il server era spento recupera nei 7 giorni dopo;
- il 29 febbraio, negli anni non bisestili, diventa il 28;
- l'output del comando non contiene dati personali.

**Modalità «N giorni prima o dopo»** (relativa, v0.38.0): ogni destinatario
porta la sua data (la scadenza della polizza, il giorno in cui scadono delle
tessere) e il suo periodo (`Destinatario::$data`, `$periodo`):
- l'email parte quella data ± i giorni, all'ora scelta, al primo giro del
  comando; se il server era spento si recupera nei 7 giorni dopo;
- un promemoria «prima» non parte più dopo la data (un avviso di scadenza a
  cose fatte confonderebbe);
- una volta sola per destinatario e periodo: per le scadenze il periodo è
  `s<id>:<data>`, così dopo «Rinnovata» il promemoria riparte per l'anno
  dopo; per le tessere `t:<giorno di scadenza>`;
- il comando controlla questi promemoria a ogni giro (le date sono per
  destinatario); «prossimo invio» è la data più vicina tra i destinatari non
  ancora avvisati, con «che cosa» (es. «01/07/2027 · Polizza RC»).

**Pagina** (PR-1, PR-3, `/promemoria`):
- promemoria raggruppati per funzione, con quando, destinatari (soci, o gli
  utenti scelti), ultimo invio, prossimo invio (con «che cosa» per i
  relativi) e stato;
- «Nuovo promemoria» per chi modifica la sezione della funzione.

**Modifica** (PR-2):
- nome, attivo/spento, quando (la modalità non ammessa dalla funzione è
  spenta), giorno e mese oppure giorni e «prima/dopo», ora e parametri;
- oggetto e testo con l'elenco dei segnaposti;
- **destinatari al prossimo invio** con la situazione di oggi;
- **anteprima** su un destinatario vero; se oggi non ce n'è nessuno, su uno
  di un'altra data; se la funzione non ne trova per nessuna data, con i
  **dati d'esempio** della funzione (`Funzione::esempio`: nomi, date e
  importi inventati, il saluto di chi guarda; v0.38.1), mai i segnaposti;
- «Invia una prova a me»: stessi valori dell'anteprima, oggetto con
  «[Prova]» (o «[Prova, dati d'esempio]»);
- promemoria per gli utenti (parametro «Destinatari»): se nessun utente
  attivo ha il ruolo scelto, avviso «Nessun utente attivo con il ruolo
  scelto.» (v0.38.1);
- storico per giorno e periodo, con l'elenco «da avvisare a mano»;
- un promemoria già inviato non si elimina, si spegne.

**Permessi**:
- la pagina (sezione `promemoria`) la vedono Presidente, Tesoriere e
  Segretario; il Revisore la consulta;
- ogni funzione si vede e si modifica secondo la sua sezione: le quote con
  `rinnovi_quota`, quindi Presidente e Tesoriere modificano, il Revisore
  legge;
- dalla v0.38.0 il Segretario vede le sue funzioni (scadenze e tessere), non
  le quote.

## 4. Implementazione tecnica

- Testo dell'email in un blocco HTML (`nl2br(e(...))`): niente
  interpretazione Markdown né HTML di quanto scritto dall'utente.
- Segnaposti sconosciuti lasciati come sono, così l'errore si vede
  nell'anteprima.
- Fino al primo invio riuscito un destinatario resta da avvisare
  (`updateOrCreate` sull'invio); prove escluse dallo storico degli invii
  veri.
- Audit: creazione LOW, modifica ed eliminazione MEDIUM (testi e date delle
  email ai soci).

### Funzione «Quote non versate»

- **Destinatari**: soci attivi, non pseudonimizzati, iscritti entro l'anno,
  senza una quota valida dell'anno sociale. L'anno sociale è l'anno
  dell'invio, più uno con «quello dopo».
- **Email**: a chi indica `destinatarioPrivacy()`, cioè il genitore per i
  minorenni.
- **Periodo**: l'anno sociale.
- **Segnaposti**: saluto (chi riceve), nome, cognome, anno, importo
  (secondo la categoria), scadenza (31/01), scadenza_decadenza (30/04),
  IBAN (segnaposto finché la scheda Associazione è vuota), causale, firma
  (Tesoriere).
- **Promemoria pronti**:
  - invito il 15 dicembre (anno dopo);
  - sollecito il 1° febbraio;
  - ultimo avviso il 1° aprile;

  tutti alle 9:00 (`rinnovi-quota.md`).

### Destinatari interni (v0.38.0)

`App\Promemoria\Interni`: parametro «Destinatari» con «Presidente e
Tesoriere», «Solo il Tesoriere», «Solo il Presidente», «Presidente, Tesoriere
e Segretario»; utenti attivi con quel ruolo, all'email con cui entrano. Chiave
del destinatario `utente:<id>`.

### Funzione «Scadenze dell'associazione» (v0.38.0)

- **Sezione**: `scadenzario`. Solo «N giorni prima o dopo».
- **Destinatari**: per ogni scadenza aperta dello Scadenzario (ricorrenti,
  una tantum non fatte) gli utenti scelti; data = la scadenza.
- **Segnaposti**: saluto, scadenza, data, giorni («tra 30 giorni», «scade
  oggi», «scaduta da 3 giorni»), categoria, ultimo_pagamento, link.
- **Promemoria pronti**: «Scadenza in arrivo» 30 giorni prima e «Ultimi
  giorni» 7 giorni prima, alle 9:00, a Presidente e Tesoriere.

### Funzione «Tessere in scadenza» (v0.38.0)

- **Sezione**: `tesseramento`. Solo «N giorni prima o dopo».
- **Destinatari**: l'ultima tessera di ogni socio attivo (se è già
  rinnovata, l'ultima è la nuova); le tessere che scadono lo stesso giorno
  in una sola email per utente (tessere caricate tutte insieme dal CSV).
  Una tessera emessa dopo l'invio con la stessa scadenza non fa ripartire il
  promemoria (resta nella pagina Tessere, «Da rinnovare»).
- **Segnaposti**: saluto, data, numero («3 tessere»), elenco (una riga per
  tessera: socio, numero, situazione della quota per l'anno del rinnovo),
  link.
- **Promemoria pronto**: «Tessere da rinnovare» 15 giorni prima, alle 9:00,
  al Tesoriere.

## 5. Conformità e sicurezza

- Ai minorenni non arriva nulla di diretto: tutto va al genitore.
- Registro degli invii: mai doppioni, traccia di chi non è stato raggiunto.
- Nessun dato personale nei log del cron.

## 6. Interazioni con altri moduli

- **Rinnovi quota**: riquadro dei promemoria nell'elenco, pulsante
  «Promemoria».
- **Registro email** (`gl_email_log`): tipi `promemoria_<funzione>` e
  `promemoria_prova`.
- **Cron**: lo stesso `schedule:run` ogni minuto (`deploy.md` §6).
- **Scadenzario** (`scadenzario.md`) e **Tessere** (`tessere.md`): funzioni
  della v0.38.0. Funzioni future, per esempio i certificati medici in
  scadenza, con la stessa modalità.

## 7. Storia ed evoluzione

- 06/10/2026: richiesta di Paolo; tavole PR-1 e PR-2 approvate.
- v0.36.0 (07/10/2026): realizzazione, dentro i tre giri di revisione dei
  Rinnovi quota (`rinnovi-quota.md` §7). In questa parte l'anteprima dei
  destinatari guardava l'anno di oggi invece di quello del prossimo invio
  (per esempio il sollecito di febbraio visto a ottobre): ora usa la data
  del prossimo invio.
- **v0.37.1** (07/10/2026, collaudo K9): elenco nell'ordine dell'anno
  sociale (prima i promemoria sull'anno dopo: l'invito del 15 dicembre), nome
  come link e freccia per aprire il dettaglio (destinatari, anteprima, prova).
- **v0.38.0** (11/10/2026, tavola PR-3): modalità «N giorni prima o dopo»
  nel motore (`Invii::momento`, `fineFinestra`, `dovuto`, `periodoDi`,
  `prossimoDettaglio`), destinatari interni, funzioni «Scadenze
  dell'associazione» e «Tessere in scadenza»; pagina con la colonna
  «Destinatari» e «che cosa» nel prossimo invio; nello storico il periodo in
  parole («scadenza del 31/07/2027», «tessere al 01/03/2027»). Il seeder crea
  i promemoria pronti anche relativi. Revisione nei tre giri della v0.38.0
  (`scadenzario.md` §7).
- **v0.38.1** (12/10/2026, collaudo M11):
  - «Invia una prova a me» su «Tessere da rinnovare» arrivava con
    `[saluto]`, `[data]`, `[elenco]`…: senza destinatari la prova metteva il
    nome del segnaposto tra parentesi quadre. Ora ogni funzione ha
    `esempio()` (dati inventati, data che farebbe partire il promemoria
    oggi); l'anteprima della pagina fa lo stesso («Anteprima con dati
    d'esempio») invece di dire di usare la prova;
  - `promemoria:invia` non scriveva nulla quando non partiva nessuna email:
    ora scrive «Nessun promemoria da inviare adesso…»; con `--prova` dice
    per ogni promemoria quanti da avvisare adesso e, per quelli «N giorni
    prima o dopo», quanti destinatari in tutto e il prossimo invio, oppure
    perché non ce ne sono (nessun utente attivo col ruolo scelto, o la
    funzione non trova nulla). Nessun dato personale nell'output;
  - nella pagina, avviso se nessun utente attivo ha il ruolo scelto.
  Causa della mancata email delle tessere sul server: da verificare con
  Paolo (comando di diagnosi del 12/10/2026).

