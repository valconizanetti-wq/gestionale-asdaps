# Soci (verifica per il contesto ASD — regole per le viste)

**Stato:** completo per il primo deploy (v0.23.0)
**Introdotto in:** v0.18.0 (2026-09-23) — verifica; v0.20.0 — viste
**File coinvolti:**
- Nessuna migration (schema confermato): `2026_09_22_000004`
  (`gl_soci`), `2026_09_22_000005` (`gl_soci_documenti`)
- Model aggiornato: `Socio.php` (hook `creating`, `isMinorenne()`,
  `tipoIncoerenteConEta()`, scope `minorenniPerEta`)
- Invariato: `SocioDocumento.php`
- v0.20.0: `SociController.php`, `Http/Requests/SocioRequest.php`, viste
  `soci/index`, `show`, `create`, `edit`, `_form`, rotte `soci.*`

## 1. Logica

Anagrafica dei soci, base di quasi tutti gli altri moduli (Tesseramento,
Volontari, Governance CD, Rinnovi, Comunicazioni, Domande). Era già
stata classificata "riuso diretto" nella checklist moduli; questa è la
prima verifica diretta del codice, fatta prima di scrivere le viste.

Lo schema corrisponde a quanto le altre schede danno per scontato: enum
`tipo` a 3 valori allineato a `gl_domande` (`domande-iscrizione.md`),
campi tutore e consenso privacy (`gdpr.md`, `comunicazioni.md`),
`quota_scadenza` (`tesseramento-sportivo.md` §4), relazioni aggiunte da
Tesseramento e Governance CD. Sono però emerse quattro questioni che non
toccano lo schema ma decidono cosa devono fare le viste (§3).

## 2. Struttura dati

**Nessuna modifica di schema.** Punti rilevanti per le viste:
- **Tessera**: `numero_tessera` (stringa), `numero_tessera_int`
  (unique, per ordinamento) e `blocco_tessera_id` → `gl_tessere_blocchi`
  (blocchi di tessere prestampate, eredità del Dante). Distinta dal
  numero di tessera CSEN, che vive in `gl_tesseramenti` e cambia ogni
  stagione.
- **Uscita del socio**: `stato` (`attivo`/`sospeso`/`cancellato`) con
  `data_cancellazione`/`motivo_cancellazione`; `deleted_at` (soft
  delete); `pseudonimizzato` (booleano).
- **Unicità**: `codice_fiscale` e `numero_tessera_int` sono unique anche
  per i record in soft delete.
- **Obbligatori senza default**: `cognome`, `nome`,
  `data_prima_iscrizione`.
- **Documenti**: `gl_soci_documenti.categoria` è testo libero (20
  caratteri); la privacy firmata ha già un campo dedicato
  (`privacy_doc_path` su `gl_soci`).

## 3. Comportamento / flusso

**Uscita di un socio** — tre meccanismi, tre usi distinti (deciso):
1. **Uscita normale** (dimissioni, mancato rinnovo, esclusione): sempre
   `stato = cancellato`, con data e motivo. Il socio resta visibile nello
   storico e i collegamenti contabili restano integri.
2. **Soft delete**: solo per correggere un inserimento sbagliato, azione
   riservata al Presidente.
3. **Pseudonimizzazione**: su un socio già `cancellato`, su richiesta
   GDPR o allo scadere dei termini di conservazione.

Conseguenza per il form: per via dell'unicità (§2), reinserire un socio
cancellato per errore fallirebbe sul codice fiscale. Il form deve
intercettare il caso e proporre il **ripristino** del record in soft
delete invece di mostrare un errore generico.

**Minore età** — calcolata da `data_nascita`, non da `tipo` (deciso):
- `tipo` resta solo la **categoria di quota** (`ordinario`/`giovane`/
  `minorenne`).
- Tutte le regole legate all'età usano `Socio::isMinorenne()`: email del
  tutore nelle comunicazioni (`comunicazioni.md` §3), KPI "soci
  minorenni", avviso di tutore mancante.
- Quando categoria ed età non coincidono (es. registrato `minorenne` ma
  ormai maggiorenne), le viste mostrano il badge "tipo da aggiornare"
  (`Socio::tipoIncoerenteConEta()`). L'aggiornamento resta un'azione
  manuale: il cambio di categoria può incidere sulla quota.

**Tessera associativa interna** — **punto aperto**, non ancora deciso se
la Compagnia emetterà una propria tessera oltre a quella CSEN. Finché la
decisione non è presa, le viste non devono darla per scontata: la colonna
"N. tessera" dell'elenco e il relativo campo del form si trattano come
opzionali. Se la risposta sarà no, `numero_tessera`/`blocco_tessera_id`
e `gl_tessere_blocchi` restano inutilizzati, come i campi RUNTS in altri
moduli.

**Categorie documenti** — `gl_soci_documenti.categoria` va trattata come
elenco chiuso lato applicazione, da definire con il form della scheda
socio (indicativamente: documento d'identità, modulo del tutore, altro).
Esclusi la privacy firmata (campo dedicato) e i certificati medici
(tabella propria, `gl_certificati_medici`).

## 4. Implementazione tecnica

- **Bug del default di `anno_iscrizione`**: la migration usa
  `->default(date('Y'))`, che PHP calcola una volta sola quando la
  migration gira. Nel database il default resta quindi fisso a quell'anno
  (2026): un socio inserito nel 2028 senza anno esplicito verrebbe
  registrato come iscritto nel 2026. Risolto senza migration con un hook
  `creating` in `Socio.php`, che valorizza `anno_iscrizione` dall'anno di
  `data_prima_iscrizione` (o dall'anno corrente) se non impostato. Il
  difetto è presente anche nel core: riportato in `MANUALE-ASD.md` §7.
- **`isMinorenne(?DateTimeInterface $alla = null)`**: confronto
  `data_nascita + 18 anni` con la data indicata (default oggi), invece di
  `diffInYears()`, che con Carbon 3 restituisce un float e renderebbe il
  confronto meno leggibile. Il parametro opzionale serve per valutazioni
  a una data precisa (es. alla data di un'assemblea).
- **Scope `minorenniPerEta`**: equivalente a `isMinorenne()` lato query,
  per KPI e filtri senza caricare tutti i soci in memoria.
- Un socio senza `data_nascita` è trattato come non minorenne e non
  genera il badge di incoerenza: il form dovrà renderla fortemente
  consigliata.

**Viste e controller (v0.20.0)** — scelte applicative:
- **Elenco**: filtri per stato (default: attivi), tipo, quota e ricerca
  su nome, cognome e codice fiscale; KPI cliccabili che applicano il
  filtro corrispondente; esportazione CSV con gli stessi filtri (UTF-8
  con BOM e separatore `;`, per Excel in italiano).
- **Libro soci in PDF** (v0.23.0, layout rifatto in v0.24.0 sul modello
  del Gestionale APS: A4 verticale, barra di riepilogo con totale, quota
  valida, da sollecitare, minorenni e documenti completi, tabella con
  intestazione colorata e righe alternate, colonna Cessato solo se
  presenti cessati; layout comune `pdf.layout`, vedi `ui.md`). Versione
  v0.23.0: A4 orizzontale, con gli stessi filtri
  dell'elenco; con stato "Tutti" è il registro completo, cessati compresi
  con data e motivo. Numero progressivo, nascita, codice fiscale,
  residenza, tipo, iscrizione, cessazione; piè di pagina con data di
  generazione e avviso "contiene dati personali". Pulsante solo su
  desktop (la stampa si fa da computer). Richiede
  `barryvdh/laravel-dompdf`, la stessa libreria del Gestionale APS.
  Era nella tavola approvata ma era rimasto fuori dalla v0.20.0.
- **Esportazioni nell'audit log** (v0.23.0): PDF e CSV registrano un
  evento `SOCI_ESPORTATI` (MEDIUM) con formato, numero di soci e filtri;
  il testo cercato non si registra, perché potrebbe essere un nome e il
  dettaglio entra nell'hash.
- **Stato della quota**: ricavato da `quota_scadenza` (valida se ≥ oggi),
  non da `gl_rinnovi`, finché il modulo Rinnovi non è costruito.
- **Tesseramento CSEN in elenco**: "Tesserato" se esiste un tesseramento
  con `stato = attivo`, altrimenti "Da tesserare".
- **Tessera interna**: la colonna compare solo se almeno un socio ha un
  numero di tessera (punto aperto, vedi sopra). Il campo è facoltativo; il
  controllo dei duplicati, ripreso dal Gestionale APS, confronta il
  numero normalizzato ("1050" = "01050") anche tra i soci eliminati.
- **Codice fiscale obbligatorio nel form** (serve per il tesseramento
  CSEN; confermato da Paolo), anche se nullable nello schema; normalizzato in maiuscolo senza
  spazi e verificato (`CodiceFiscaleValido`: formato, carattere di
  controllo, data e luogo). L'unicità si controlla nel controller per
  poter proporre il ripristino di una scheda eliminata.
- **Dati ricavati dal codice fiscale** (v0.30.3): data, luogo di nascita e
  sesso si compilano mentre si scrive il codice (anteprima
  `iscrizione.codice-fiscale`, che non legge il database) e sono in sola
  lettura; il server li ricalcola sempre in `SocioRequest`.
- **Obbligatori** (v0.30.3, Paolo 30/09/2026): residenza completa
  (indirizzo, CAP di 5 cifre, comune, sigla di provincia di 2 lettere);
  email e telefono per i maggiorenni. Per un minorenne (età ricavata dal
  codice) email e telefono propri sono facoltativi e sono obbligatori
  tutti i dati del genitore o tutore: nome, codice fiscale (valido e
  diverso da quello del socio), relazione, telefono, email.
- **Telefono unico** (v0.30.3): un solo campo `telefono`; la colonna
  `cellulare` è stata tolta (migration `2026_09_30_000088`, che sposta
  nel telefono l'eventuale cellulare).
- **Cessazione**: modale con motivo (elenco chiuso) e data. Bloccata se il
  socio ha una carica attiva nel CD, come nel Gestionale APS. A
  differenza del Gestionale APS **non** pseudonimizza: imposta solo
  `stato = cancellato` (regola di §3) ed è annullabile con "Riattiva".
  I motivi ammessi (`SociController::MOTIVI_CESSAZIONE`: dimissioni
  volontarie, mancato rinnovo della quota, esclusione deliberata dal CD,
  decesso) sono **confermati da Paolo**; da ricontrollare quando lo
  statuto sarà redatto.
- **Eliminazione e ripristino**: solo Presidente, con modale di conferma
  che rimanda alla cessazione per le uscite normali.
- **Telefono**: su schermo stretto le righe diventano schede, compare il
  pulsante per chiamare (telefono del socio, o del tutore se minorenne)
  e i filtri diversi dalla ricerca si aprono con "Filtri".
- **Audit log** (v0.21.0): inserimenti, modifiche (inclusa cessazione e
  riattivazione), eliminazioni e ripristini sono registrati in automatico
  dall'`AuditObserver` dichiarato su `Socio` (vedi `audit-log.md` §8).
  Alla futura pseudonimizzazione andranno oscurati anche i valori nelle
  righe di audit del socio.
- **Documenti e privacy** (v0.22.0): vedi sotto.

**Documenti per l'iscrizione e consenso privacy (v0.22.0)** — deciso con
Paolo:
- **Requisiti dell'iscrizione**, mostrati nella scheda del socio con lo
  stato di ciascuno (presente / mancante / non richiesto): documento
  d'identità, scheda di iscrizione, consenso privacy, modulo del tutore
  (solo minorenni per età), certificato medico.
- **Categorie di `gl_soci_documenti`** (elenco chiuso in
  `config/soci.php`): documento d'identità, scheda di iscrizione, modulo
  del tutore, altro (con descrizione obbligatoria). Caricare una scheda di
  iscrizione imposta anche `scheda_registrazione_presente`.
- **Consenso privacy**, due modalità: *in presenza* (un clic registra data,
  ora e versione dell'informativa; nessun IP, sarebbe quello
  dell'operatore) e *cartaceo* (data della firma e scansione del modulo in
  `privacy_doc_path`). Un nuovo modulo non cancella il precedente dal
  disco: resta come prova del consenso passato.
- **Modalità online = canale principale** (indicazione di Paolo): il
  consenso verrà tipicamente acquisito online, nella domanda di
  iscrizione (`gl_domande` traccia già consenso privacy e altri consensi
  per ogni domanda). All'approvazione, la trasformazione della domanda in
  socio dovrà copiare in `gl_soci` i campi privacy con `privacy_metodo =
  online`, data e ora dell'accettazione, IP del richiedente e versione
  dell'informativa. *In presenza* e *cartaceo* restano come canali
  secondari (iscrizioni senza modulo online, correzioni). Da realizzare
  con il modulo Domande.
- **Soci senza domanda online** (primi soci, inserimenti a mano) — deciso
  da Paolo: **link personale via email**. Dalla scheda del socio si invia
  un link (al tutore se minorenne); la pagina pubblica mostra l'informativa
  nella versione corrente e registra l'accettazione come `online`, con data,
  ora e IP di chi accetta. Da progettare insieme al modulo Domande, con cui
  condivide pagina dell'informativa e meccanismo del link. Richiede:
  posta configurata (SMTP), una tabella per i link (token salvato solo come
  hash, scadenza, data di invio e di utilizzo, monouso: `gl_soci` non ha
  campi adatti), una rotta pubblica senza login con limite di tentativi, e
  il testo dell'informativa della Compagnia. **Realizzato nella v0.33.0**
  (`privacy-link.md`): tabella `gl_privacy_link`, monouso e senza scadenza
  (un nuovo invio annulla il precedente), invio anche in blocco
  dall'elenco, `privacy_metodo = link`; consenso a foto e video nella
  scheda (`foto_video_consenso`, `foto_video_il`, `foto_video_origine`).
- **Versione dell'informativa**: impostazione `privacy_versione_corrente`
  in `gl_impostazioni` (iniziale "1.0"), registrata con ogni consenso.
- **Certificato medico: previsto ma non richiesto.** Impostazione
  `certificato_medico_richiesto` (spenta): finché è spenta il requisito
  compare come "Non richiesto" e non si può caricare nulla, quindi nessun
  dato sanitario entra nel gestionale. Se in futuro verrà richiesto, il
  certificato andrà registrato in `gl_certificati_medici` (con scadenza),
  **non** come documento generico; prima di accendere l'impostazione
  vanno attivati il modulo Certificati e il censimento del trattamento dei
  dati sanitari (`gdpr.md`, `tesseramento-sportivo.md`).
- **Anteprima** (v0.25.0): ogni documento caricato e il modulo privacy si
  aprono in una nuova scheda del browser (pulsante con l'occhio nella
  tabella, "Apri" accanto ai requisiti presenti). Il file è servito
  "inline" con il tipo corretto, `X-Content-Type-Options: nosniff` e senza
  cache; accesso con gli stessi permessi del download. Il download resta
  disponibile.
- **File**: disco privato `local`, percorso `soci/{id}/documenti|privacy/`,
  nome casuale (il nome originale resta solo nel database); PDF, JPG, PNG
  fino a 5 MB; da telefono si può scattare una foto. Scaricabili solo con
  accesso alla sezione Soci.
- **Audit**: anche `SocioDocumento` dichiara l'`AuditObserver`
  (caricamenti ed eliminazioni tracciati).
- **Stato dei documenti anche nell'elenco** (v0.23.0): colonna
  "Documenti" (Completi / N mancanti), che sostituisce la colonna
  "Scheda" del Gestionale APS. La regola dei requisiti vive in un solo
  punto, `Socio::requisitiIscrizione()`, usata da scheda ed elenco;
  l'elenco precarica documenti (e, se richiesto, certificato valido) per
  non fare una query per riga.
- **Rotte**: `SocioDocumentiController`, gruppo `soci/{socio}/…` con
  controllo esplicito che il documento appartenga al socio (lo
  `scopeBindings()` di Laravel cercherebbe una relazione "documentos").

## 5. Conformità e sicurezza

- **GDPR**: la sequenza cancellazione → pseudonimizzazione permette di
  conservare i dati contabili collegati (obbligo di conservazione) senza
  mantenere i dati personali oltre il necessario.
- **Minori**: calcolare la minore età dalla data di nascita evita di
  applicare regole da adulto a un minore (o viceversa) solo perché la
  categoria di quota non è stata aggiornata.

## 6. Interazioni con altri moduli

- **Comunicazioni**: la scelta email propria/tutore usa `isMinorenne()`,
  non `tipo`.
- **Domande/Iscrizione**: una domanda approvata genera un socio con
  `tipo` valido (enum allineato) e passa `data_prima_iscrizione`, da cui
  deriva `anno_iscrizione`.
- **Tesseramento**: il numero di tessera CSEN resta in `gl_tesseramenti`;
  la tessera interna dipende dal punto aperto di §3.
- **Rinnovi quota**: il cambio di categoria segnalato da "tipo da
  aggiornare" può incidere sull'importo della quota.
- **Permessi**: soft delete riservato al Presidente (sezione `soci`:
  Presidente e Segretario).
- **UI** (`ui.md`): stati `attivo` → badge `ok`, `sospeso` → `warn`,
  `cancellato` → `neutral`.

## 7. Storia ed evoluzione

- Prima verifica diretta del codice di `gl_soci`, fatta prima di
  scrivere le viste secondo il metodo concordato (migration e model
  confrontati con la scheda prima di ogni controller).
- Schema confermato; le quattro questioni emerse riguardano l'uso del
  modulo, non la sua struttura.
- Decisioni di Paolo: regola dei tre meccanismi di uscita accettata come
  proposta; minore età calcolata dalla data di nascita. Tessera interna:
  decisione rinviata.
- Il bug del default di `anno_iscrizione` è stato notato leggendo la
  migration con in mente il form di inserimento, non durante la
  revisione della base dati: a conferma che la verifica prima delle
  viste individua problemi diversi da quelli della revisione dello
  schema.
- v0.20.0: prime viste del gestionale (elenco, scheda, inserimento,
  modifica) costruite sui componenti Blade condivisi e sulla tavola
  approvata (desktop e telefono); controller ripreso in parte dal
  Gestionale APS (normalizzazione tessera, blocco cessazione con carica
  CD attiva), con le differenze decise in §3.
- v0.22.0: documenti per l'iscrizione e consenso privacy; certificato
  medico previsto ma spento, su decisione di Paolo (non richiesto per
  l'attività non agonistica, ma pronto se servirà).
- v0.23.0: confronto con il libro soci del Gestionale APS. Gli elementi
  in più del Dante (anno sociale, importi e totale quote, ricevute,
  contatori in sidebar, avviso HIGH, aiuto) arrivano con Rinnovi quota,
  Prima Nota, Audit Log o sono scelte diverse già approvate; mancavano
  davvero il PDF del libro soci e un indicatore dei documenti in elenco,
  aggiunti.
- v0.30.3 (30/09/2026, richieste di Paolo dal collaudo): modulo di
  inserimento e modifica a righe compatte (campi larghi quanto il
  contenuto, `.socio-riga-*`), dati di nascita dal codice fiscale,
  residenza, email e telefono obbligatori, un solo telefono, tutti i
  dati del genitore obbligatori per i minorenni (compreso il codice
  fiscale, prima assente dal modulo).
- v0.33.0 (05/10/2026): link personale per l'informativa privacy e consenso
  a foto e video (`privacy-link.md`). Nel riquadro "Documenti per
  l'iscrizione" la riga del consenso privacy ha quattro stati (mancante, in
  attesa, presente, da aggiornare) e i pulsanti "Invia il link" / "Invia
  di nuovo"; nuova riga "Foto e video" (facoltativa, non conta tra i
  mancanti) con "Registra la revoca". Nell'elenco, pulsante "Link privacy"
  con il numero dei soci da raggiungere e finestra dell'invio in blocco.
- **v0.37.1** (07/10/2026, collaudo K7): «Elimina scheda» chiede la
  motivazione (almeno 15 caratteri) e non procede se il socio ha quote
  valide, movimenti in Prima Nota o tessere CSEN (per chi esce: cessazione;
  una quota sbagliata si annulla prima). Una sola riga di audit
  `SOCIO_DELETED` (rischio alto, email al Presidente): nel dettaglio, che
  entra nell'hash, nessun dato personale; nomi, codice fiscale e motivazione
  nei valori. **Schede eliminate**: in Soci › Filtri › Stato, solo per il
  Presidente, con ricerca anche per codice fiscale e «Ripristina» (prima
  l'unico modo era reinserire il codice fiscale in «Nuovo socio»); CSV e
  libro soci non escono mai con quel filtro. Colonna «Tessera CSEN» con il
  badge della tessera (v0.37.0).

- **Importazione dei soci da Excel** (v0.40.0, 13/10/2026,
  `analisi-importazione-soci.md`):
  - pagina Soci › «Importa da Excel» (Presidente e Segretario), dal modello
    di Paolo;
  - anteprima con errori e avvisi riga per riga; conferma solo senza errori
    (tutto o niente);
  - soci `importato`, dati di nascita dal codice fiscale, genitore nei
    campi `tutore_*` per i minorenni;
  - privacy, foto e video e tessere non si importano;
  - quote del vecchio gestionale: dal 2027 in `gl_rinnovi` senza ricevuta.

  Filtro `importati` nell'elenco.
