# Promemoria — email automatiche pianificate per tutte le funzioni

**Stato:** in uso (v0.36.0, 07/10/2026). Prima funzione: «Quote non versate».
**Introdotto in:** v0.36.0
**File coinvolti:**
- Migration: `2026_10_07_000099_create_gl_promemoria_tables`
- Model: `Promemoria` (con l'AuditObserver), `PromemoriaInvio`
- Motore: `App\Promemoria\Funzione` (contratto), `Registro`, `Destinatario`,
  `Invii`; funzione `App\Promemoria\Funzioni\QuoteNonVersate`
- Comando: `promemoria:invia` (`InviaPromemoria`), ogni 15 minuti in
  `routes/console.php`
- Seeder: `PromemoriaSeeder` (in `DatabaseSeeder`); `SezioneSeeder` con
  la sezione `promemoria`
- Controller: `PromemoriaController`; viste `resources/views/promemoria/*`,
  email `emails/promemoria/messaggio`; `PromemoriaEmail`
- Menu: «Promemoria» nel gruppo Governance (`config/navigazione.php`)
- Test: in `tests/Feature/RinnoviQuotaTest.php`
- Tavole PR-1 e PR-2 (`docs/mockup/Tavole-rinnovi-quota.pdf`)

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

**Pagina** (PR-1, `/promemoria`):
- promemoria raggruppati per funzione, con quando, ultimo invio, prossimo
  invio e stato;
- «Nuovo promemoria» per chi modifica la sezione della funzione.

**Modifica** (PR-2):
- nome, attivo/spento, quando (la modalità non ammessa dalla funzione è
  spenta), giorno, mese, ora e parametri;
- oggetto e testo con l'elenco dei segnaposti;
- **destinatari al prossimo invio** con la situazione di oggi;
- **anteprima** su un destinatario vero;
- «Invia una prova a me»: oggetto con «[Prova]» e i valori del primo
  destinatario;
- storico per giorno e periodo, con l'elenco «da avvisare a mano»;
- un promemoria già inviato non si elimina, si spegne.

**Permessi**:
- la pagina (sezione `promemoria`) la vedono Presidente, Tesoriere e
  Segretario; il Revisore la consulta;
- ogni funzione si vede e si modifica secondo la sua sezione: le quote con
  `rinnovi_quota`, quindi Presidente e Tesoriere modificano, il Revisore
  legge;
- il Segretario vede la pagina vuota finché non ci sono funzioni sue.

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
- Funzioni future, per esempio certificati medici in scadenza o scadenze
  dello Scadenzario, con `quando` relativo.

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
