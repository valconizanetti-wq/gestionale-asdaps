# Scontrini dal telefono con OCR

**Stato:** in uso (v0.39.0, 12/10/2026). Prima: solo schema (v0.4.0, 22/09/2026).
**Introdotto in:** v0.39.0 (pagine, lettura, divisione, fornitori, ruolo Consigliere); tabella e impostazione dal 22/09/2026.
**File coinvolti:**
- Migration: `2026_10_12_000102_scontrini_fornitori` (su
  `gl_prima_nota_scontrini`: numero, partita IVA letta, conto, nota, stato,
  esito della lettura, righe lette, anteprime; nuove
  `gl_prima_nota_scontrini_righe` e `gl_prima_nota_scontrini_file`; su
  `gl_soggetti`: partita IVA unica, indirizzo, comune, attivo). Dal
  22/09/2026: `2026_09_22_000072` → `000074`.
- Model: `PrimaNotaScontrino` (con l'AuditObserver, stato), 
  `PrimaNotaScontrinoRiga`, `PrimaNotaScontrinoFile`, `Soggetto` (ora con
  l'AuditObserver)
- Lettura: `App\Scontrini\ServizioOcr` (OCR.space), `Foto` (copie
  ridotte), `LettoreScontrino` (totale, data, partita IVA, fornitore,
  pagamento, righe), `Fornitori` (riconoscimento)
- Servizio: `App\Services\Scontrini` (registra, salvaDati, dividi, piano,
  aggiungiFoto, elimina)
- Controller: `ScontriniController`, `FornitoriController`; in
  `PrimaNotaController` i movimenti di uno scontrino (importo, data e conto
  bloccati)
- Viste: `scontrini/create` (SN-1), `conferma` (SN-2, SN-3), `miei` (SN-4),
  `index` (SN-5), `show` (SN-6), `fornitori` e `_fornitore` (SN-7); in
  `prima-nota/index`, `show`, `_form` l'icona, l'avviso e i campi bloccati
  (SN-8)
- Seeder: ruolo `consigliere` in `RuoloSeeder`; sezione `scontrini` e
  permessi in `SezioneSeeder`
- Configurazione: `config/services.php` › `ocr_space` (`OCR_SPACE_KEY`,
  `OCR_SPACE_CA`, `OCR_SPACE_TIMEOUT` nel `.env`)
- CSS: sezione 17 di `app.css`
- Test: `tests/Feature/ScontriniTest.php`, `tests/Unit/LettoreScontrinoTest.php`
  (testo pulito e testo vero di OCR.space,
  `tests/Fixtures/scontrini-ocrspace/risultati-ocr.json`)
- Tavole SN-1…SN-8 (`docs/mockup/Tavole-scontrini.pdf`), analisi
  `docs/analisi-scontrini.md`, script di prova `docs/strumenti/prova-ocr.php`

## 1. Logica

Funzione già in uso in `gestionale-acv` (il plugin con cui la Compagnia
registra gli scontrini fino al 31/12/2026): chi compra fotografa lo
scontrino dal telefono, il gestionale legge totale, data e negozio, e la
spesa entra in Prima Nota. Decisioni di Paolo (analisi §7–§9, 11–12/10/2026):

- caricano gli scontrini **i membri del CD**: ruolo nuovo **Consigliere**
  (e il Segretario), con la Prima Nota in **sola lettura** e nessun altro
  inserimento;
- **chi carica non modifica più**: il passo 2 (conferma dei dati letti) è
  l'ultima occasione; dopo «Registra» verificano e correggono Presidente e
  Tesoriere;
- **nota obbligatoria** (che cosa si è comprato), **fornitore
  obbligatorio**, **partita IVA** dei fornitori, **una foto** (altre dalla
  scheda);
- scontrino con spese di **categorie diverse**: le righe si conservano una
  per una e si fa **un movimento per categoria**;
- il **fornitore** si riconosce (partita IVA, poi nome), si sceglie dalla
  lista o nasce nuovo con i dati corretti;
- pagato con **cassa** o **banca** (carta della Compagnia); «di tasca mia»
  arriverà con anticipi e note di rimborso.

## 2. Struttura dati

- `gl_prima_nota_scontrini`: `numero` progressivo per `anno` (unico con
  l'anno; dà il codice `SC2027-012`), `data_scontrino`, `importo_totale`,
  `conto`, `nota`, `soggetto_id` (fornitore), `ragione_sociale_raw` e
  `partita_iva` (come letti), `stato` (`da_verificare`, `da_quadrare`,
  `verificato`; ricalcolato, mai scritto a mano), `quadrato`,
  `ocr_esito` (letto, non_letto, non_disponibile, errore), `ocr_ms`,
  `ocr_testo_grezzo`, `righe_lette` (JSON), `anteprima_path` (copia
  ridotta, 1600 px) e `miniatura_path` (240 px), `allegato_path` (la prima
  foto), `registrato_da`.
- `gl_prima_nota_scontrini_righe`: `ordine`, `descrizione`, `importo`
  (IVA compresa; negativo per uno sconto), `categoria_id`, `prima_nota_id`
  (il movimento in cui è finita).
- `gl_prima_nota_scontrini_file`: le foto (`SC2027-012.jpg`, aggiunte
  `SC2027-012-02.pdf`), in `storage/app/private/scontrini/<anno>/`.
- `gl_prima_nota.scontrino_id`: più movimenti per scontrino.
- `gl_soggetti`: `partita_iva` (unica se c'è), `indirizzo`, `comune`,
  `attivo` (un fornitore usato non si elimina, si toglie dalla lista).
- Il testo letto e le righe lette **non** entrano nell'Audit Log (campi
  esclusi nell'AuditObserver: lunghi, restano nella scheda).

## 3. Comportamento / flusso

**Passo 1 (SN-1, telefono)**: «Fotografa lo scontrino» (fotocamera
posteriore) o «Scegli un file» (JPG, PNG, PDF fino a 8 MB; HEIC respinto con
la soluzione per l'iPhone) e la nota. «Leggi lo scontrino»: il gestionale
mette da parte la foto (sessione, un giorno, al più 5 per persona), ne fa una
copia ridotta e raddrizzata (lato lungo 1600 px, JPEG 75) e la manda a
OCR.space (motore 2, italiano, `isTable=true`, una sola richiesta, 2–5
secondi; analisi §12). **Nel database non si scrive nulla.**

**Passo 2 (SN-2, SN-3)**: foto in piccolo (ingrandibile), avviso «Letti
dalla foto: controllali» o «Foto non letta» (si scrive a mano), totale (con
la riga da cui viene), data (avviso se tagliata o vecchia), riquadro
**Fornitore**:
1. partita IVA uguale a un fornitore in elenco → scelto, «già in elenco»;
2. nome uguale (senza maiuscole, punteggiatura, «S.R.L.», «& C.») → scelto;
   se in elenco non ha partita IVA, gli si aggiunge quella letta;
3. altrimenti «forse è uno di questi»: partita IVA quasi uguale (al più 3
   cifre diverse) o nome simile, al più tre; il primo è proposto;
4. «Nuovo fornitore» con ragione sociale, partita IVA (11 cifre e cifra di
   controllo), indirizzo e comune letti, da correggere; una partita IVA già
   di un altro fornitore si segnala invece di creare un doppione;
5. «Scegli dalla lista dei fornitori» (finestra con ricerca).
Poi nota, pagato con (proposto da «PAGAMENTO CONTANTE/ELETTRONICO»), righe
lette. «Registra» (anno aperto, data non futura, anno dall'avvio della
contabilità nel gestionale: 2027, o prima se c'è un anno contabile di
prova; fino ad allora gli scontrini veri restano in `gestionale-acv` e il
passo 1 lo dice): in una transazione
fornitore, scontrino numerato, foto archiviate, **un movimento su «Uscite
da classificare»** (uscita, documento «Scontrino SC2027-012»), le righe
(quelle lette se sommano al totale, altrimenti una riga col totale). Chi
carica va a «I miei scontrini» (SN-4: sola lettura con lo stato);
Presidente e Tesoriere alla scheda.

**Elenco (SN-5)**: anno, riquadri da verificare / da quadrare / verificati
/ spesa, avviso «da sistemare prima della chiusura», filtri per stato, chi
ha caricato, testo; miniature. Sotto, per Presidente e Tesoriere, lo stato
della lettura automatica (chiave presente, ultimo esito).

**Scheda (SN-6)**: a sinistra foto (ingrandibile; «Aggiungi una foto»),
dati (totale, data, fornitore, conto; nota in sola lettura; «Salva i dati»),
testo letto. A destra la **quadratura** (righe su totale, barra), le **righe**
con categoria per riga, «Aggiungi una riga», «Unisci le righe selezionate»,
«Aggiungi la differenza», «Riprendi le N righe lette», «Tutte nella stessa
categoria»; il **piano** «un movimento per categoria» (con i movimenti
riusati e quelli che sostituisce) e **«Registra la divisione»**, attivo solo
se le righe tornano al centesimo e hanno tutte la categoria; sotto, i
movimenti in Prima Nota ora.

**Stato**: `da_quadrare` se i movimenti non sommano al totale (es. totale
corretto dopo una divisione), `da_verificare` se un movimento è ancora su
«Uscite da classificare», altrimenti `verificato`. Entrambi gli stati
aperti bloccano la chiusura dell'anno (controlli in Anni e saldi).

**Prima Nota (SN-8)**: icona sui movimenti nati da uno scontrino, avviso
con il link alla scheda; nel modulo del movimento **importo, data e conto
sono bloccati** (si cambiano dalla scheda), categoria, descrizione e tag no;
cambiare qui la categoria aggiorna le righe e lo stato. Un movimento di uno
scontrino non si elimina dalla Prima Nota. Il Consigliere vede «Nuovo
scontrino» al posto di «Nuovo movimento».

**Fornitori (SN-7)**: elenco con partita IVA, comune, movimenti (di tutti
gli anni, per nome), ultimo uso, stato; possibili doppioni (stesso nome
normalizzato) con «Unisci» (movimenti, scontrini, erogazioni passano a
quello scelto; i dati mancanti si prendono dall'altro); «Modifica» (anche
«In elenco»), «Nuovo fornitore», «Elimina» solo se mai usato.

## 4. Implementazione tecnica

- **Permessi**: sezione `scontrini` piena = caricare (Presidente,
  Tesoriere, Segretario, Consigliere); sola lettura il Revisore.
  Verificare, dividere, correggere, eliminare, fornitori: chi modifica la
  Prima Nota (`puoModificare('prima_nota')`, Presidente e Tesoriere).
- **Divisione** (`Scontrini::piano`, uguale in PHP e nella scheda): righe
  raggruppate per categoria nell'ordine in cui compaiono; ogni gruppo riusa
  il movimento della stessa categoria, poi il primo rimasto libero (di
  solito quello su «Uscite da classificare», che conserva il numero); gli
  altri si tolgono senza eventi, con i loro giustificativi spostati sul
  primo movimento. Una riga `SCONTRINO_DIVISO` (MEDIUM) nell'Audit Log, più
  le modifiche dei movimenti.
- **Importi**: in centesimi interi (`Importo::centesimi`), senza bcmath (non
  c'è su ogni server); righe anche negative (sconti).
- **Anno chiuso**: «Salva i dati», «Registra la divisione», «Aggiungi una
  foto» ed «Elimina» chiedono una motivazione (`SCONTRINO_CORREZIONE_ANNO_CHIUSO`,
  HIGH, motivazione nei valori nuovi) e aggiornano la fotografia dei saldi.
  La data di uno scontrino resta nel suo anno.
- **Eliminazione**: una riga `SCONTRINO_ELIMINATO` (HIGH) con totale, data
  e movimenti; movimenti, righe e foto si tolgono senza altre righe; i
  file dopo la transazione.
- **OCR.space**: `ServizioOcr` non lancia mai eccezioni (esito «errore» o
  «non_disponibile» e si scrive a mano); timeout 15 secondi (sotto il
  limite di 30 del PHP del server); la chiave non va nei log. `OCR_SPACE_CA`
  serve solo in locale con un antivirus che controlla l'HTTPS (analisi §12;
  in alternativa l'eccezione `https://api.ocr.space/*` in Avast).
- **gd/exif**: senza `gd` niente copia ridotta: si manda l'originale se
  sotto 1 MB, altrimenti si scrive a mano.

## 5. Conformità e sicurezza

- La chiave di OCR.space sta **solo** nel `.env` (`OCR_SPACE_KEY`), mai nel
  codice, nel database o nella chat.
- Una foto di scontrino di solito non contiene dati personali; su quelli di
  un negozio individuale compare il codice fiscale del titolare, che **non
  si salva** (resta solo nel testo letto della scheda). OCR.space (a9t9
  software GmbH, Germania) tratta le foto, che riguardano negozi e non
  soci: da valutare se citarlo tra i fornitori di servizi nel registro dei
  trattamenti (`da-riportare-gestionale-aps.md` D12).
- Foto servite solo a chi ha la sezione, da `storage/app/private`.

## 6. Interazioni con altri moduli

- **Prima Nota** (`prima-nota.md`): movimenti, categoria di sistema
  `DA_CLASSIFICARE`, chiusura dell'anno (controllo «Nessuno scontrino da
  quadrare»), campo «Fornitore» (solo fornitori in elenco).
- **Impostazioni**: `scontrini_split_abilitato` (sì); spenta, tutte le
  righe hanno la categoria della prima.
- **Anticipi e note di rimborso** (prossimi): «pagato di tasca mia».

## 7. Storia ed evoluzione

- 22/09/2026 (v0.4.0): schema e impostazione dal plugin `gestionale-acv`
  (flusso in due passi, quadratura, fornitore trova-o-crea, anno chiuso).
- 06/10/2026 (v0.35.0): provenienza corretta (`gestionale-acv` della
  Compagnia); stessa chiave OCR.space.
- 11–12/10/2026: analisi (`analisi-scontrini.md`), risposte di Paolo, tavole
  SN-1…SN-8 (approvate il 12/10/2026), prova con 14 scontrini veri e con
  OCR.space vero (totale, data e pagamento 14/14, partita IVA 13/14).
- 12/10/2026 (v0.39.0): realizzazione. Differenze dalle tavole: nel menu
  nessun contatore (il menu non ne ha in nessun modulo; i numeri sono nei
  riquadri dell'elenco); «Annulla» del passo 2 torna al passo 1; in più
  «Aggiungi la differenza» e «Riprendi le righe lette» nella scheda.

**Tre giri di revisione critica** (12/10/2026), 15 difetti corretti prima
della consegna:

1. *Correttezza e sicurezza*:
   1. confronti con bcmath, assente in locale (e forse sul server; c'era
      già nel modello del 22/09) → importi in centesimi interi;
   2. giustificativi aggiunti dalla Prima Nota a un movimento tolto da una
      divisione o con lo scontrino: si cancellavano a cascata lasciando i
      file → spostati sul primo movimento, o cancellati con lo scontrino;
   3. un solo campo file riempito via JavaScript dai due pulsanti (fragile
      su Safari) → due campi «foto», si invia quello usato;
   4. attesa di OCR.space fino a 25 secondi, vicino al limite PHP di 30 →
      15 secondi, come `gestionale-acv`;
   5. bozze in sessione senza limite → al più 5 per persona.
2. *Aderenza a tavole e decisioni*:
   6. nomi di categoria sbagliati nel piano della divisione (le chiavi
      numeriche si rinumeravano) → corretti;
   7. Prima Nota del Consigliere con l'avviso generico e l'invito a
      scrivere i saldi di apertura → avviso della tavola SN-8, invito solo a
      chi modifica;
   8. righe lette che non tornano: non si potevano riprendere → «Riprendi
      le N righe lette»;
   9. stato della lettura automatica non visibile (analisi §3.9) → riga
      sotto l'elenco;
   10. fornitori tolti dalla lista ancora proposti nella Prima Nota → solo
       quelli in elenco.
3. *Bordi e telefono* (verifica a 1440 e 375 px):
   11. tabelle del piano e dei movimenti fuori schermo sul telefono → righe
       a blocchi;
   12. riquadri della scheda attaccati tra loro → spaziatura;
   13. «Righe scritte a mano» fuorviante → spiega la riga unica col totale;
   14. il Tesoriere che carica dal computer finiva su «I miei scontrini» →
       va alla scheda;
   15. fonte del totale in minuscolo → come sullo scontrino.
