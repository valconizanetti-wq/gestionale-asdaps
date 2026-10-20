# Verbali, riunioni del CD e consultazioni telematiche

**Stato:** consegnato (v0.44.0, 21/10/2026), da collaudare (blocco R).
**Introdotto in:** v0.44.0. Prima: tabella `gl_verbali` con i soli verbali
delle domande (v0.30.0) e tabella `gl_assemblee` vuota.
**Le Assemblee** (convocazione dei soci, conferma di presenza, quorum,
elezioni, invio ai soci) sono la **v0.45.0**: qui solo i loro verbali
registrati da fuori.

**File coinvolti:**
- Migration `2026_10_21_000106_verbali_libri_consiglio`:
  - `gl_verbali`: tipo e stato in testo (`cd_delibera` → `cd_riunione`;
    gli stati `approvato` e `archiviato`, mai usati, tornano `bozza`);
    nuove colonne del libro (`libro`, `anno_libro`, `numero_libro`, unici
    insieme), `da_fuori`, `organo`, `luogo`, `approva_rendiconto_anno`,
    `pdf_impronta`, definitivo (`definitivo_il`, `definitivo_da`) e firma
    (`firma_tipo`, `firme_numero`, `firma_dichiarata`, `firmatari`,
    `firmato_nome`, `firmato_il`, `firmato_da`);
  - `gl_assemblee` diventa la **seduta**: stato in testo,
    videoconferenza, collegamento, urgenza con il motivo, convocati,
    presidente e segretario della seduta, annullamento;
  - tabelle nuove: `gl_sedute_presenze`, `gl_verbali_punti`,
    `gl_verbali_voti`, `gl_verbali_rettifiche`, `gl_consultazioni`,
    `gl_consultazioni_voti`, `gl_organi_documenti`;
  - permessi: la sezione Verbali in lettura a Vicepresidente, Tesoriere,
    Consigliere e Revisore (V12).
- Model nuovi: `Seduta`, `SedutaPresenza`, `VerbalePunto`, `VerbaleVoto`,
  `VerbaleRettifica`, `Consultazione`, `ConsultazioneVoto`,
  `OrganoDocumento`. Riscritto `Verbale`.
- Servizi: `Verbali` (libro, definitivo, copia firmata, rettifica,
  registrato da fuori, PDF), `Riunioni` (CD-5 e VB-2), `Consultazioni`
  (CT-1…CT-3), `Votazioni` (esito di tutte le votazioni),
  `DocumentiOrgani` (PDF allegati). `Support\HtmlSanitizer` (dal Dante),
  `Support\FirmaDigitale::contaFirme()` e `firmatari()`.
- Toccati: `VerbaleAmmissione` (libro del CD, chi firma),
  `AnniContabili::verbaliApprovazione()`, `ConsiglioDirettivo` (verbale
  della nomina), `AvvisiOperazioni`, `Azzeramento`, `SezioneSeeder`.
- Controller: `VerbaliController`, `RiunioniController`,
  `ConsultazioniController` (area riservata), `ConsultazioneVotoController`
  (pagina pubblica dal link).
- Mail: `ConvocazioneSeduta`, `ConsultazioneInvito`, `ConsultazioneEsito`
  (viste in `emails/organi`).
- Viste: `verbali/index` (VB-1), `show` (VB-3), `scrivi` (VB-2),
  `registra` (VB-5), `_corpo` e `_firmato`; `verbali/riunioni/form` e
  `show` (CD-5); `verbali/consultazioni/form` (CT-1) e `show` (CT-3);
  `consultazione-voto/pagina` (CT-2); `pdf/verbale` (VB-4) e
  `pdf/rettifica`; componente `x-editor`.
- Editor: `resources/js/editor.js`, Jodit 4.17.1 da npm (versione fissa),
  entrata Vite a parte.
- Rotte: `verbali.*` nel gruppo `permesso:verbali`; `consultazione.*`
  pubbliche in `routes/iscrizione.php`; comando `consultazioni:scadenze`
  ogni 15 minuti in `routes/console.php`.
- CSS: sezione 21 di `app.css`.
- Test: `tests/Feature/VerbaliTest.php` (13 test). Aggiornato
  `PrimaNotaTest` (scelta del verbale alla chiusura dell'anno).
- Analisi: `docs/analisi-verbali-assemblee.md`. Tavole VB-1…VB-5, CD-5,
  CT-1…CT-3: `Tavole-verbali.pdf`, fuori dal repository.
- Collaudo: `docs/test/collaudo-R-verbali.md`.

## 1. Logica

Due **libri**: *Consiglio Direttivo* e *Assemblee*. Un verbale entra nel
suo libro con il **numero progressivo nell'anno** («CD 2027/4») quando
diventa **definitivo** (V1). Da lì il testo non cambia più: un errore si
corregge con una **rettifica** datata e motivata, in fondo, con il suo PDF
(statuto art. 23.2).

Tre stati: **bozza** → **definitivo** (numero, PDF salvato con
l'impronta SHA-256) → **firmato** (copia con la firma digitale del
presidente della seduta e del segretario verbalizzante, V2 e V14).

Nel libro del CD entrano:
- le **riunioni** (presenze, punti, voto di ciascuno);
- le **consultazioni telematiche** su una proposta di delibera (art. 16.4,
  V8);
- i verbali delle **domande di adesione** (dal voto del CD, v0.30.0), resi
  definitivi anche insieme (V11). Le domande di prova non entrano.

In tutti e due i libri si può **registrare un verbale già redatto** fuori
dal gestionale (V13, V15): dati essenziali, delibere in breve, copia
firmata digitalmente; entra già firmato, con il suo numero.

**Dal Dante** si riprendono l'editor Jodit, il suo `HtmlSanitizer`, la
firma digitale per archiviare, il numero e il PDF. I difetti corretti
(analisi §3): numero con lock e vincolo unico (1); il definitivo non si
modifica (2); presenze dai membri in carica e voti di ciascuno con l'esito
calcolato (4, 5); termini di convocazione controllati, niente modifiche
dopo la convocazione (6); email dalla scheda del socio (7); presenze dal
giorno della seduta (8); allegati solo PDF (10); un evento dell'Audit Log
per passo, senza dati personali nel dettaglio (12); la chiusura dell'anno
sceglie il verbale con la delibera sul rendiconto (13); i documenti non si
fondono nel PDF (14).

**Regola di tutte le votazioni** (Paolo, 20/10/2026, V17 e V18), in
`Votazioni::esito()`:
- la maggioranza si conta tra i votanti, favorevoli contro contrari; gli
  astenuti restano fuori;
- in parità prevale il Presidente, se ha votato; senza nessun voto
  favorevole o contrario la delibera non passa;
- chi «non partecipa» per conflitto di interessi (art. 16.7) non vota;
- l'**esclusione di un socio** passa con i favorevoli almeno pari alla
  maggioranza assoluta dei componenti (art. 9.1 c), solo in riunione;
- (v0.45.0) modifica dello statuto 2/3 dei presenti, scioglimento e simili
  3/4 dei soci con diritto di voto, elezioni a scrutinio segreto.

Il verbale scrive sempre la regola usata.

**Quorum** (diverso dalla maggioranza):
- riunione del CD: presenti almeno la maggioranza dei componenti (art.
  16.3); altrimenti si verbalizza la discussione ma non si delibera;
- consultazione: votanti (astenuti compresi) almeno la maggioranza dei
  componenti (art. 16.4 c), altrimenti «non valida».

## 2. Struttura dati

**`gl_verbali`** (`Verbale`): `tipo` (`cd_riunione`, `cd_consultazione`,
`cd_ammissione`, `assemblea`), `libro`, `organo` (`cd`, `ordinaria`,
`straordinaria`), `assemblea_id` (la seduta), `domanda_id`, `titolo`,
`data_seduta`, `luogo`, ore, `presidente_assemblea` e
`segretario_verbalizzante` (nomi di chi firma), `presenze_cd` (fotografia),
`corpo_html` (domande e registrati da fuori), `approva_rendiconto_anno`,
numero e anno del libro, PDF e impronta, copia firmata e firme.

**`gl_assemblee`** (`Seduta`; il vecchio model `Assemblea` resta): `tipo`
`cd` (dalla v0.45.0 anche le Assemblee), data e ora, luogo,
videoconferenza e collegamento, `ordine_del_giorno` (elenco di punti),
urgenza e motivo, stato (`bozza`, `convocata`, `svolta`, `rinviata`,
`annullata`) con le date, convocati, presidente e segretario della seduta.

**`gl_sedute_presenze`**: un membro per riga, con nome e carica
fotografati, `presenza` (`sede`, `video`, `assente`), `giustificato`.

**`gl_verbali_punti`**: ordine, titolo, discussione, `ha_delibera`,
`tipo_delibera` (`ordinaria`, `propone_rendiconto`, `decadenze`,
`convoca_assemblea`, `esclusione`), testo della delibera, esito e testo
dell'esito, conteggi.

**`gl_verbali_voti`**: voto di ciascun presente per punto (`favorevole`,
`contrario`, `astenuto`, `non_partecipa`).

**`gl_verbali_rettifiche`**: numero nel verbale, testo, motivo, PDF con
l'impronta, copia firmata.

**`gl_consultazioni`**: oggetto, testo della proposta, termine, stato
(`bozza`, `in_corso`, `chiusa`, `ritirata`), esito (`approvata`,
`respinta`, `non_valida`, `riunione`, `ritirata`), componenti, chi l'ha
preparata e avviata, `in_sostituzione`, `verbale_id`.

**`gl_consultazioni_voti`**: per componente, fotografia di nome, carica ed
email; token come impronta SHA-256 più copia cifrata con `APP_KEY` (per il
sollecito), come per le domande; voto (`favorevole`, `contrario`,
`astenuto`, `riunione`), nota, data e ora, indirizzo, browser.

**`gl_organi_documenti`**: PDF allegati a seduta, consultazione o verbale
(`allegabile_type`, `allegabile_id`). Alla chiusura della consultazione i
riferimenti si copiano al verbale (stesso file); un file si cancella solo
quando nessuno lo usa più.

File in `storage/app/private/verbali/`: `<anno>/CD-2027-4.pdf`, copie
firmate e rettifiche accanto; documenti in `documenti/<tipo>/<id>/`.

## 3. Comportamento / flusso

**Libri (VB-1)**, `verbali`. Due schede, l'anno, i numeri (bozze, da
firmare, firmati) e l'elenco: numero, data, verbale, stato, copia firmata
(con l'avviso se manca la firma del presidente). Nel libro del CD anche
«In corso»: riunioni non ancora verbalizzate e consultazioni in bozza o in
corso. Se ci sono verbali di domande decise, l'avviso con «Rendi
definitivi i N verbali»: si numerano nell'ordine di chiusura del voto.

**Riunione del CD (CD-5).** «Riunione del CD» apre la bozza: data, ora,
luogo o videoconferenza (con il collegamento), ordine del giorno a punti,
urgenza con il motivo, documenti PDF. Dalla scheda:
- «Invia una prova a me»;
- «Invia la convocazione» ai membri in carica, con l'email della scheda
  del socio. Il termine (V4): almeno 3 giorni prima; con meno, solo come
  urgente con il motivo; mai meno di 24 ore. Chi non ha l'email è
  segnalato;
- dopo la convocazione data, luogo e ordine del giorno non cambiano:
  «Rinvia» (motivo, avviso facoltativo ai membri) e poi «Riconvoca», che
  la riporta in bozza; oppure «Annulla la riunione» con il motivo.

**Presenze**, dal giorno della riunione: per ogni membro in sede, in
videoconferenza o assente (giustificato o no), chi presiede e chi
verbalizza tra i presenti. Il quorum si vede mentre si segna. Al
salvataggio nasce il verbale in bozza con un punto per voce dell'ordine
del giorno. Se un membro è alla terza assenza di fila senza
giustificazione, compare l'avviso dell'art. 15.5 (lo valuta il CD).

**Verbale per punti (VB-2).** Per ogni punto la discussione (editor) e,
se il Consiglio delibera, il tipo, il testo e il voto di ciascun presente
(«Tutti favorevoli» per fare prima). Il conto si aggiorna sulla pagina;
l'esito si calcola al salvataggio. Con il CD non costituito la spunta
della delibera è spenta. In fondo l'ora di chiusura e i documenti.

**Rendi definitivo (VB-3).** Controlli: data; per la riunione presidente
e segretario, testo e voti di ogni delibera, discussione o delibera per
ogni punto; per una domanda la decisione presa. Il verbale prende il
numero, il PDF si salva con l'impronta; l'operazione arriva per email agli
altri (rischio alto). Per i verbali delle domande e delle consultazioni,
se mancano i nomi di chi firma si prendono il Presidente e il Segretario
in carica.

**Copia firmata (V2, V14).** PDF con la firma dentro (PAdES) o `.p7m`
(CAdES), fino a 10 MB; una scansione firmata a mano non basta.
- PDF con due o più firme: accettato, «2 firme digitali (PAdES)».
- Una firma sola: solo se è del segretario verbalizzante, dichiarato da
  chi carica; resta l'avviso «Manca la firma del presidente della
  seduta».
- `.p7m`: le firme non si contano; chi carica dichiara chi ha firmato.

Il gestionale legge i nomi dei firmatari scritti nel file, come testo, e
non verifica il certificato. La copia si sostituisce solo con il motivo
(rischio alto); la vecchia resta conservata.

**Rettifica.** Dal definitivo: testo (editor) e motivo; nasce il PDF
della rettifica, da firmare e caricare (basta una firma digitale).

**Consultazione telematica (CT-1…CT-3).** La prepara in bozza chi scrive
i verbali: oggetto, testo della proposta, termine, documenti. **La avvia
solo il Presidente**, o il Vicepresidente con la spunta «in sostituzione
del Presidente» (art. 18.3, V16). All'avvio:
- il termine deve essere almeno 3 giorni dopo;
- tutti i membri in carica devono avere l'email;
- ognuno riceve il link personale; da qui la proposta non cambia.

Dalla pagina del link (CT-2, telefono) si leggono proposta e documenti e
si vota: favorevole, contrario, astenuto o «Ne discutiamo in riunione»,
con una nota facoltativa. Il voto non si cambia. Si chiude da sola:
- quando hanno votato tutti;
- quando almeno un terzo dei componenti chiede la riunione (esito «Da
  discutere in riunione»);
- al termine (comando ogni 15 minuti), anche «non valida» se hanno votato
  meno della maggioranza dei componenti.

Alla chiusura nasce il verbale in bozza nel libro del CD (con voti, date,
ore e codice del collegamento, art. 16.4 e) e l'esito parte per email.
Dalla scheda (CT-3): voti, «Esito fin qui», «Sollecita chi non ha
votato», «Ritira la consultazione» (Presidente o Vicepresidente, con il
motivo). Non si usa per il rendiconto né per l'esclusione (art. 16.5): lo
dice la pagina.

**Registra un verbale già redatto (VB-5).** Libro, organo, data,
presidente e segretario, presenti, titolo, delibere in breve, numero
(vuoto = primo libero; scritto = per riprendere il libro di carta, se
libero), «Approva il rendiconto dell'anno …» (solo Assemblee), copia
firmata digitalmente, documenti.

## 4. Implementazione tecnica

- Numero: `Verbali::numeroBloccato()` con `lockForUpdate` sulle righe del
  libro e dell'anno, più il vincolo unico `libro + anno_libro +
  numero_libro`.
- PDF del definitivo generato una volta e salvato; l'anteprima della bozza
  si genera a ogni richiesta, con la scritta BOZZA.
- Testi dell'editor ripuliti da `HtmlSanitizer::pulisci()` a ogni
  salvataggio (tag ammessi, link solo http e https, niente script,
  iframe, attributi `on…`, stili solo da una lista). L'editor non ha il
  pulsante «sorgente».
- Firme PAdES contate con i dizionari `/ByteRange`
  (`FirmaDigitale::contaFirme`); nomi dai `/Name` dei dizionari `/Sig`.
- Audit Log (niente AuditObserver: gli eventi li scrivono i servizi):
  `SEDUTA_CONVOCATA` (basso), `SEDUTA_PRESENZE` (basso),
  `SEDUTA_RINVIATA` (medio), `SEDUTA_ANNULLATA` (alto);
  `VERBALE_DEFINITIVO` (alto), `VERBALE_FIRMATO` (medio),
  `VERBALE_FIRMATO_SOSTITUITO` (alto), `VERBALE_RETTIFICATO` (alto),
  `VERBALE_RETTIFICA_FIRMATA` (medio), `VERBALE_REGISTRATO_DA_FUORI`
  (alto); `CONSULTAZIONE_AVVIATA` e `…_CHIUSA` (medi), `…_RITIRATA`
  (alto). I motivi stanno nei valori, fuori dal dettaglio.
- Errori nella borsa del modulo da cui partono: `firmato`, `rettifica`,
  `rettifica_firmata`, `documenti`, `rinvio`, `annulla`, `ritiro`.
- Menu: la voce «Verbali» si accende su tutte le rotte `verbali.*`
  (riunioni e consultazioni comprese).

## 5. Conformità e sicurezza

- Statuto: artt. 9.1 c, 13.4, 15.5, 16.1–16.7, 18.3, 23.2.
- Firma digitale: il gestionale controlla che ci sia, non che sia valida;
  la verifica del certificato si fa con il servizio dell'AgID o del
  certificatore.
- Link della consultazione: chiave personale, salvata come impronta; la
  pagina pubblica non mostra i voti degli altri.
- Documenti: solo PDF; si aprono solo dalla sezione o dal link.

## 6. Interazioni con altri moduli

- **Domande**: i verbali delle domande sono nel libro del CD; la pagina
  del verbale della domanda porta al libro.
- **Prima Nota**: la chiusura dell'anno propone solo i verbali firmati del
  libro delle Assemblee con «Approva il rendiconto dell'anno …».
- **Consiglio Direttivo**: «Aggiungi un membro» può collegare il verbale
  della nomina (definitivi o firmati dell'ultimo anno).
- **Avvisi delle operazioni delicate**, **Audit Log**, **Azzeramento**
  (tabelle nuove svuotate, cartella `verbali`).
- **v0.45.0**: Assemblee con convocazione dei soci, quorum, elezioni,
  invio del verbale ai soci (V10).

## 7. Storia ed evoluzione

- 20/10/2026: analisi, risposte di Paolo (V1–V18), tavole approvate con la
  regola delle votazioni. Codice v0.44.0 il 21/10/2026.
- **Tre giri di revisione critica** prima della consegna:
  1. *Correttezza e sicurezza.* La voce «Anticipi» del menu restava
     accesa su tutte le pagine (la chiave `attiva` della configurazione si
     sommava a quella calcolata): rinominata `accendi`, con il test. Un
     verbale con un punto senza discussione né delibera diventava
     definitivo: ora è una mancanza. Nel PDF un `@if` attaccato a una
     parola non veniva letto da Blade (errore 500 sul verbale della
     consultazione): riscritto.
  2. *Dati esistenti.* Gli stati `approvato` e `archiviato` della
     migration sarebbero diventati «definitivo» senza numero né PDF:
     tornano in bozza (non sono mai stati usati). I verbali delle domande
     nati prima non hanno i nomi di chi firma: al definitivo si prendono
     il Presidente e il Segretario in carica (test). Rollback della
     migration provato.
  3. *Pagine* (1440 e 375 px, nessuno scorrimento orizzontale). Sul
     telefono la barra fissa dei pulsanti del verbale copriva metà
     schermo: lì non è più fissa. Conteggio dei voti al singolare («1
     contrario»). Nel PDF il titolo andava a capo: colonna più larga. Data
     ripetuta nel sottotitolo del verbale.
- Test: 194 verdi (13 nuovi in `VerbaliTest`).
