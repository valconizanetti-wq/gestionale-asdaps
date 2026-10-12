# Scontrini dal telefono con OCR — analisi (11/10/2026)

Analisi prima delle tavole e del codice (versione dopo la v0.38.0). Materiale
letto:
- `docs/specifiche/scontrini.md` e `MANUALE-ASD.md` §6 (schema del 22/09/2026,
  mai usato da una pagina);
- `gestionale-acv`, il plugin con cui la B.A.S. registra oggi gli scontrini
  (`claude/acv-contabilita.md`, `acv-contabilita-addendum.md`,
  `analisi-contabilita-esistente.md` §2 nel progetto);
- la Prima Nota com'è oggi (`prima-nota.md`), i giustificativi
  (`Concerns\Giustificativi`), i fornitori «trova o crea» (`gl_soggetti`);
- la documentazione di OCR.space (pagina dell'API, 11/10/2026).

## 1. Cosa c'è oggi

**In `gestionale-acv`** (in uso, va in pensione il 01/01/2027):
1. dal telefono: foto (JPG o PDF, 8 MB; HEIC respinto) e nota obbligatoria;
   nella stessa richiesta la chiamata a OCR.space (motore 2, `scale`,
   base64, 15 secondi); stato in memoria per 15 minuti, niente righe a metà;
2. conferma di totale, data, ragione sociale (obbligatoria), cassa o banca:
   in una transazione allegato, scontrino, un movimento su «Uscite da
   classificare», fornitore «trova o crea».
- Euristiche: totale dalla riga con «total» (escluso «subtotale»), se no il
  numero più grande; ragione sociale = prima riga di almeno 3 caratteri;
  data = prima GG/MM/AAAA plausibile. Sempre proposte, mai applicate da
  sole.
- Dal computer: split dello scontrino su più categorie, con i movimenti «in
  bozza» finché la somma non torna; non si scollega l'ultimo movimento;
  sull'anno chiuso motivazione e log.

**Nel Gestionale ASD** (v0.38.0):
- tabella `gl_prima_nota_scontrini` (data, totale, anno, fornitore, testo
  OCR, **un solo** allegato, `quadrato`, note) e `gl_prima_nota.scontrino_id`:
  create il 22/09/2026, nessuna pagina le usa;
- impostazione `scontrini_split_abilitato` (sì);
- categoria di sistema «Uscite da classificare» (`DA_CLASSIFICARE`): blocca
  la chiusura dell'anno finché resta usata;
- la Prima Nota non ha movimenti «in bozza»: un movimento c'è o non c'è;
- i giustificativi dei movimenti sono in `gl_prima_nota_documenti`, più file
  per movimento, nome codificato `PN<anno>-<n.>-<progr.>`;
- i fornitori sono `gl_soggetti` (solo il nome), il movimento ne copia il
  nome nel campo `soggetto`;
- PHP del server: `gd` ed `exif` da verificare in *Select PHP Version* (in
  locale ci sono).

## 2. Che cosa dice OCR.space (piano gratuito)

- **File al massimo di 1 MB**, 25.000 richieste al mese, 500 al giorno per
  indirizzo, PDF fino a 3 pagine.
- Italiano: `language=ita` (oppure `auto` con il motore 2, che è quello
  predefinito); `isTable=true` restituisce il testo riga per riga, consigliato
  per gli scontrini; `detectOrientation=true` raddrizza la foto; `scale=true`
  ingrandisce le immagini piccole.
- Risposta: `ParsedResults[].ParsedText`, `IsErroredOnProcessing`,
  `ErrorMessage`, `OCRExitCode` (1 tutto bene, 2 in parte, 3 e 4 errore).

**Conseguenza importante**: la foto di un telefono pesa 2–5 MB. Con la chiave
gratuita OCR.space la rifiuta. In `gestionale-acv` la foto va intera in
base64 (un terzo più pesante): o la chiave è a pagamento, o l'OCR fallisce
spesso in silenzio e si scrive tutto a mano. Da chiedere a Paolo (§6). In
ogni caso il gestionale deve **mandare all'OCR una copia ridotta** (lato
lungo 1600 px, JPEG di qualità 75: di solito 200–500 KB) e **archiviare
l'originale**.

## 3. Scelte proposte

1. **Pagina dal telefono** `/scontrini/nuovo`, in due passi come oggi, dentro
   il gestionale (stesso accesso, niente password condivise):
   - passo 1: un grande pulsante «Fotografa lo scontrino» (fotocamera
     posteriore) o «Scegli un file»; nota (cosa si è comprato) obbligatoria;
   - passo 2: la foto in piccolo e i dati letti, tutti da confermare: totale,
     data, fornitore (con il «trova o crea» e, se letta, la partita IVA),
     come è stato pagato; «Registra»;
   - se l'OCR non risponde o non c'è la chiave: si va lo stesso al passo 2
     con i campi vuoti e un avviso («scrivi tu i dati»);
   - la foto del passo 1 resta messa da parte (come i giustificativi dopo un
     errore) per un giorno; nessuna riga nel database finché non si
     registra.
2. **Foto**: si accettano JPG, PNG, PDF fino a 8 MB. Il gestionale fa una
   copia ridotta e raddrizzata (orientamento EXIF) per l'OCR e la anteprima;
   archivia l'originale. HEIC: Safari su iPhone di solito lo converte già in
   JPEG nel caricamento; se arriva HEIC, messaggio con la soluzione
   («Impostazioni › Fotocamera › Formati › Più compatibile»), come oggi.
3. **Euristiche migliori** di quelle di `gestionale-acv`, sempre come
   proposta:
   - totale: righe «TOTALE COMPLESSIVO», «TOTALE EURO», «IMPORTO PAGATO»,
     «TOTALE» (escluse «SUBTOTALE», «TOTALE IVA», «DI CUI IVA», «RESTO»,
     «CONTANTE»); importi con la virgola; se nessuna, il più grande;
   - data: GG/MM/AAAA, GG/MM/AA, GG-MM-AAAA, GG.MM.AAAA, tra due anni fa e
     domani;
   - fornitore: la riga prima di «P.IVA» o «Partita IVA», se no la prima riga
     utile (saltando «DOCUMENTO COMMERCIALE», «di vendita o prestazione»);
   - partita IVA: 11 cifre dopo «P.IVA», controllata con la cifra di
     controllo;
   - si salva il testo letto (`ocr_testo_grezzo`), per capire gli errori e
     migliorare le regole con scontrini veri.
4. **Movimento**: sempre un'uscita su «Uscite da classificare», con conto
   cassa o banca (carta della Compagnia = banca). Il movimento entra subito
   nei saldi; la categoria vera la sceglie poi il Tesoriere dal computer.
   L'anno deve essere aperto e dal 2027 in poi (prima: vecchio gestionale).
5. **Allegato**: la foto va nei giustificativi dello scontrino con un nome
   codificato `SC<anno>-<n.>.<est>` (oggi lo schema prevede un solo file;
   proposta: lasciare un file per scontrino, il secondo foglio di una
   fattura si aggiunge dalla scheda). Il movimento lo mostra come i suoi
   giustificativi.
6. **Split e quadratura** (impostazione già prevista, sì):
   - dalla scheda dello scontrino si aggiungono righe su categorie diverse
     (es. ferramenta: «Acquisto materiali» + «Manutenzione campi»);
   - la somma deve tornare: finché non torna lo scontrino è «da quadrare»,
     i movimenti restano nei saldi (come in `gestionale-acv`) e l'anno non si
     chiude (stessa regola di «Uscite da classificare»); niente stato «in
     bozza» nella Prima Nota;
   - non si toglie l'ultima riga: si elimina lo scontrino intero;
   - importo, data e conto dei movimenti di uno scontrino si cambiano solo
     dalla scheda dello scontrino; categoria, descrizione e tag anche dalla
     Prima Nota.
7. **Pagina Scontrini** (dal computer, sotto Contabilità): elenco con foto in
   miniatura, data, fornitore, totale, stato (da classificare, da quadrare,
   a posto), filtri; «Nuovo scontrino» porta al passo 1 anche da computer
   (con «Scegli un file»). Nella Prima Nota un'icona sui movimenti che
   vengono da uno scontrino e il link alla scheda.
8. **Anno chiuso**: modifica ed eliminazione con motivazione nell'Audit Log,
   come i movimenti (regola già della Prima Nota).
9. **Chiave OCR** nel `.env` (`OCR_SPACE_KEY`), mai nel database né nel
   codice (decisione del 06/10/2026: «`.env` o Impostazioni»; il `.env` la
   tiene fuori dall'Audit Log e dai backup del database). Impostazioni ›
   Ente mostra solo se c'è e se l'ultima chiamata è andata bene.
10. **Permessi** (oggi la Prima Nota è di Presidente e Tesoriere, il Revisore
    legge): sezione nuova `scontrini`, in modo che chi compra (es. il
    Segretario o un consigliere con un utente) possa **solo** fotografare e
    registrare i suoi scontrini, senza vedere la Prima Nota; classificare e
    quadrare restano a Presidente e Tesoriere.
11. **Dati personali**: una foto di scontrino di solito non ne contiene, ma
    può contenere le ultime cifre della carta o il nome su una fattura.
    OCR.space (a9t9 software, Germania) diventa un responsabile esterno: da
    aggiungere nell'informativa (punto dei fornitori di servizi) e nel
    registro dei trattamenti; da verificare le sue condizioni sulla
    conservazione delle immagini.

## 4. Schema (modifiche a quello del 22/09/2026)

- `gl_prima_nota_scontrini`: in più `numero` (progressivo per anno, per il
  nome del file), `partita_iva`, `conto`, `nota` (cosa si è comprato),
  `ocr_esito` (letto, parziale, non disponibile, errore), `anteprima_path`;
  `allegato_path` col nome codificato; `quadrato` resta, ricalcolato con il
  lock.
- `gl_soggetti`: in più `partita_iva` (facoltativa, unica se c'è): il «trova o
  crea» cerca prima per partita IVA, poi per nome («ACQUA & SAPONE SRL» e
  «Acqua e Sapone» diventano lo stesso fornitore).
- `gl_prima_nota.scontrino_id` resta (più movimenti per scontrino).
- Sezione `scontrini` in `SezioneSeeder`.

## 5. Tavole da disegnare (dopo le risposte)

- SN-1 telefono, passo 1 (foto e nota);
- SN-2 telefono, passo 2 (dati letti da confermare; anche «OCR non
  disponibile»);
- SN-3 elenco Scontrini (computer e telefono);
- SN-4 scheda dello scontrino con le righe dello split e la quadratura;
- SN-5 Prima Nota: icona sui movimenti da scontrino, avviso «da quadrare».

## 6. Domande per Paolo

1. **Chiave di OCR.space**: è quella gratuita o a pagamento? Con la gratuita
   le foto sopra 1 MB non vengono lette: oggi in `gestionale-acv` l'OCR
   funziona davvero, o si scrive quasi sempre a mano?
2. **Chi registra gli scontrini dal telefono**: solo Presidente e Tesoriere,
   o anche altri (Segretario, consiglieri)? Se altri, devono avere un utente
   del gestionale con la sola sezione «Scontrini».
3. **Come si paga**: cassa, banca (carta o bancomat della Compagnia) e anche
   «di tasca mia»? Il terzo caso è un **anticipo da rimborsare**, cioè il
   modulo anticipi e note di rimborso (il prossimo in lista): proposta di
   fare gli scontrini con cassa e banca e aggiungere «anticipato da me»
   insieme agli anticipi, oppure di progettarli insieme.
4. **Nota obbligatoria** al passo 1 (come oggi) o facoltativa?
5. **Fornitore obbligatorio** (come oggi) anche quando l'OCR non lo legge?
6. **Partita IVA dei fornitori**: va bene aggiungerla, per riconoscere lo
   stesso negozio scritto in modi diversi?
7. **Un file per scontrino** (più eventuali aggiunte dalla scheda) va bene,
   o servono più foto già al passo 1 (scontrini lunghi)?
8. **Ci sono scontrini veri** (anche già registrati in `gestionale-acv`) da
   usare come prova per le euristiche? Bastano le foto, anche 5 o 6.

## 7. Risposte di Paolo (11/10/2026, 23:38)

1. **Chiave OCR.space**: l'OCR in `gestionale-acv` legge correttamente gli
   scontrini. Si tiene la stessa chiave; la copia ridotta per l'OCR resta
   comunque (sicurezza contro il limite di 1 MB del piano gratuito, foto più
   veloce da mandare).
2. **Chi registra dal telefono**: i **membri del CD**. Serve un ruolo nuovo
   **«Consigliere»** (oggi i ruoli sono Presidente, Segretario, Tesoriere,
   Revisore) con la sola sezione `scontrini`: registra e vede i suoi
   scontrini, non la Prima Nota. Presidente, Segretario e Tesoriere hanno
   anche loro la sezione; classificare e dividere restano a Presidente e
   Tesoriere.
3. **«Di tasca mia» (anticipi)**: dopo, con il modulo anticipi e note di
   rimborso. Per ora cassa e banca.
4. **Nota** al passo 1: obbligatoria.
5. **Fornitore**: obbligatorio, anche quando l'OCR non lo legge.
6. **Partita IVA dei fornitori**: sì.
7. **Una foto** per scontrino, se basta a leggere tutto; per gli scontrini
   lunghi resta «Aggiungi un'altra foto» nella scheda.
8. **Scontrini veri** per la prova: Paolo li manda.

## 8. Scontrino con spese di categorie diverse (domanda di Paolo, 23:38)

Esempio: alla ferramenta colla per le frecce («Acquisto materiali»), una
cerniera per la porta dei bagni («Manutenzione e gestione bagni») e una
pinza («Acquisto attrezzature»), 37,40 € in tutto.

Proposta: **le righe dello scontrino si conservano una per una**, e i
movimenti di Prima Nota si fanno **uno per categoria**.

1. **L'OCR legge anche le righe degli articoli** (descrizione e importo:
   `isTable` le restituisce una per riga), comprese le righe di sconto
   (negative), e le propone nella scheda dello scontrino.
2. **Nella scheda** (dal computer, Presidente o Tesoriere) ogni riga ha la
   sua categoria:
   - una tendina per riga, con «Tutte nella stessa categoria» per fare
     prima;
   - righe sbagliate o mancanti si correggono o si aggiungono a mano
     (descrizione e importo), e si possono unire («colla» e «nastro» in una
     sola riga);
   - la barra di quadratura dice quanto manca al totale dello scontrino.
3. **«Registra la divisione»**: il gestionale fa un movimento per categoria
   con la somma delle sue righe (descrizione: le righe, es. «Colla, nastro»),
   tutti collegati allo stesso scontrino, con la stessa foto e lo stesso
   fornitore, conto e data. Lo scontrino diventa «a posto» solo se la somma
   torna al centesimo.
4. **Sul telefono** non si divide nulla: il consigliere fotografa, conferma
   totale, fornitore e nota; il movimento nasce intero su «Uscite da
   classificare». Se sa già che lo scontrino contiene cose diverse, lo
   scrive nella nota.
5. **Righe** in una tabella nuova `gl_prima_nota_scontrini_righe`
   (scontrino, ordine, descrizione, importo, categoria, movimento): così
   resta anche *che cosa* si è comprato, utile per il rendiconto e per i tag
   (es. «Tettoia»), mentre la Prima Nota resta leggibile (un movimento per
   categoria, non uno per articolo).
6. **Regole**:
   - un movimento per categoria e per scontrino; cambiare la categoria di
     una riga sposta l'importo da un movimento all'altro (e toglie il
     movimento rimasto vuoto);
   - importo, data e conto dei movimenti di uno scontrino si cambiano solo
     dalla scheda dello scontrino;
   - anno chiuso: motivazione nell'Audit Log, come per i movimenti;
   - con l'impostazione `scontrini_split_abilitato` spenta le righe restano
     (per sapere cosa si è comprato) ma tutte nella stessa categoria.
7. **IVA**: per la Compagnia (ente non commerciale) la spesa è il totale
   pagato, IVA compresa; le righe dello scontrino sono già IVA compresa,
   quindi la somma delle righe è il totale.

Tavola in più: **SN-4** diventa la scheda con le righe e le categorie (era
lo split «a movimenti»).

## 9. Due note di Paolo (12/10/2026, 07:39)

**1. Il CD vede la Prima Nota, ma inserisce solo scontrini.**
- Ruolo «Consigliere»: sezione `prima_nota` **in sola lettura** (elenco,
  schede, giustificativi, esportazioni) e sezione `scontrini` per
  **caricare** gli scontrini. Nessun «Nuovo movimento», giroconto, modifica
  o eliminazione.
- Lo stesso per il **Segretario** (membro del CD, oggi senza Prima Nota):
  Prima Nota in sola lettura, scontrini sì.
- Classificare, dividere per categorie, correggere ed eliminare gli
  scontrini: Presidente e Tesoriere. **Chi carica uno scontrino non lo
  modifica più** (Paolo, 12/10/2026, 07:50): dopo «Registra» lo vede in
  sola lettura; verifica ed eventuali correzioni sono di Presidente e
  Tesoriere. Il passo 2 (conferma dei dati letti) resta l'unico momento in
  cui chi carica corregge.
- Revisore: sola lettura come oggi, anche sugli scontrini.

**2. Il fornitore si acquisisce sempre, dopo la correzione dell'OCR.**
Al passo 2, nel riquadro «Fornitore»:
1. l'OCR propone ragione sociale, partita IVA e, se le legge, indirizzo e
   comune (le righe in cima allo scontrino);
2. il gestionale cerca tra i fornitori già registrati: prima per **partita
   IVA** (sicura), poi per **nome** senza badare a maiuscole, punteggiatura e
   forme come «S.R.L.», «SRL», «& C.» (es. «ACQUA & SAPONE S.R.L.» = «Acqua e
   Sapone srl»);
3. tre casi, sempre modificabili:
   - **trovato**: è già scelto, con l'etichetta «già in elenco»;
   - **simile ma non sicuro**: «Forse è uno di questi?» con i due o tre più
     somiglianti da toccare;
   - **nessuno**: «Nuovo fornitore» con i dati letti, da correggere;
4. in ogni momento si può aprire la **lista dei fornitori** (con ricerca) e
   sceglierne un altro, oppure passare a «Nuovo»;
5. alla registrazione il fornitore nuovo nasce **con i dati corretti**
   (ragione sociale, partita IVA, indirizzo, comune), nella stessa
   transazione dello scontrino: se la registrazione non va, non resta un
   fornitore a metà; una partita IVA già presente su un altro fornitore
   viene segnalata invece di creare un doppione.

Schema: `gl_soggetti` riceve `partita_iva` (unica se c'è), `indirizzo`,
`comune`, `attivo` (un fornitore usato non si elimina, si toglie dalla lista).
Una pagina «Fornitori» (Presidente e Tesoriere) per correggere, unire due
doppioni e disattivare; la Prima Nota usa lo stesso elenco nel suo campo
«Fornitore».

Tavole: SN-2 mostra i tre casi del fornitore; in più **SN-6** pagina
Fornitori.

## 10. Tavole (12/10/2026)

Sulla tela «Gestionale ASD — Riferimento UI», sezione «Scontrini dal
telefono con OCR» (sotto le tavole SC), da approvare:
- **SN-1** telefono, passo 1: foto (o file) e nota obbligatoria;
- **SN-2** telefono, passo 2: totale, data, fornitore nel caso «forse è uno
  di questi», nota, pagato con, righe lette; avviso «dopo Registra lo
  modifica solo il Tesoriere»;
- **SN-3** telefono: fornitore trovato (per partita IVA), nuovo (dati letti
  da correggere), lista con ricerca; foto non letta;
- **SN-4** telefono: registrato e «I miei scontrini» in sola lettura;
- **SN-5** elenco Scontrini (Tesoriere): da verificare, da quadrare,
  verificati, spesa dell'anno, filtri per stato e consigliere;
- **SN-6** scheda: foto e dati a sinistra; righe con la categoria per
  riga, unisci, «tutte nella stessa categoria», quadratura; riepilogo «un
  movimento per categoria» e «Registra la divisione» (sostituisce il
  movimento su «Uscite da classificare»);
- **SN-7** Fornitori: partita IVA, comune, uso, doppioni da unire, tolti
  dalla lista; finestra «Unisci»;
- **SN-8** Prima Nota vista dal Consigliere: sola lettura, solo «Nuovo
  scontrino», icona sui movimenti nati da uno scontrino.

Le tavole PN-4, PN-5, PN-6 della Prima Nota (06/10/2026) sono superate da
queste.
