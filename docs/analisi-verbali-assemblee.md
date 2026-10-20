# Verbali e Assemblee — analisi (20/10/2026)

Analisi prima delle tavole e del codice (Fase 1, `piano-2027.md` §2,
punto 1), fatta in parallelo al collaudo Q. Copia nel progetto:
`claude/analisi-verbali-assemblee.md`.

Regola di Paolo: per le funzioni che esistono già nel Dante si recupera il
suo codice e lo si corregge o migliora (§3), in vista della migrazione.

**Materiale letto:**
- **codice del Dante**, allegato il 20/10/2026 (`app.zip`, `resources.zip`,
  `routes.zip`):
  - controller `AssembleeController`, `VerbaliController` e
    `RsvpController`;
  - model `Assemblea`, `AssembleaRsvp`, `Verbale`, `VerbaleAllegato`,
    `DelibereCd`, e l'observer `VerbaleArchivioObserver`;
  - viste `assemblee/*`, `verbali/*` e le email di convocazione, rinvio,
    sollecito e verbale depositato;
  - rotte, compresa l'Area Consiglieri (`/cd`);
- **statuto 1.0**: artt. 7, 9, 12–19, 23 e 26;
- **Gestionale ASD**:
  - le tabelle `gl_assemblee`, `gl_assemblee_rsvp`, `gl_verbali` e
    `gl_verbali_allegati`, copiate dal Dante il 22/09/2026 e senza pagine;
  - `VerbaleAmmissione` e `VotazioneCd` (domande);
  - `AnniContabili::verbaliApprovazione()`;
  - i permessi delle sezioni `assemblee` e `verbali`.

---

## 1. Che cosa chiede lo statuto

| Tema | Regola | Articolo |
|---|---|---|
| Chi convoca l'Assemblea | Il Presidente, su delibera del CD; almeno una volta l'anno entro 4 mesi dalla chiusura (rendiconto); su richiesta di 1/10 dei soci entro 30 giorni | 12.2 |
| Avviso dell'Assemblea | Data, ora e luogo della 1ª e della 2ª convocazione (almeno un'ora tra le due), ordine del giorno, eventuale partecipazione a distanza | 12.3 |
| Come e quando si convoca | Email dal libro soci, posta ordinaria a chi non ha email, affissione in sede; almeno **10 giorni** prima, **15** per la straordinaria | 12.3, 26 |
| Chi riceve la convocazione | Tutti i soci; per i minorenni chi ha la responsabilità genitoriale, che assiste senza parola né voto | 7.3, 26.1 |
| Chi vota | I soci maggiorenni in regola con la quota (un voto, niente deleghe); il moroso dopo il 31/01 non vota fino al versamento | 7.2, 9.1 b, 12.1 |
| Svolgimento | Presiede il Presidente (o il Vicepresidente, o un socio eletto); il presidente nomina il segretario e, se serve, due scrutatori; si può partecipare e votare a distanza | 13.1, 13.2 |
| Votazioni | Palesi; **scrutinio segreto** sempre per le elezioni, e per le altre votazioni sulle persone se lo chiede 1/10 dei presenti; segreto anche telematico | 13.3 |
| Quorum dell'ordinaria | 1ª convocazione: metà più uno dei soci con diritto di voto; 2ª: qualunque numero. Maggioranza dei voti espressi | 14.2 |
| Quorum della straordinaria | Modifiche dello statuto: 1ª metà più uno, 2ª un terzo, voto dei 2/3 dei presenti. Scioglimento e trasformazioni: 3/4 dei soci con diritto di voto | 14.4, 14.5 |
| Verbale dell'Assemblea | Firmato dal presidente e dal segretario dell'Assemblea, trascritto nel **libro dei verbali delle Assemblee**, a disposizione dei soci | 13.4, 26.2 |
| Riunione del CD | Almeno due volte l'anno; convocazione per email con l'ordine del giorno almeno **3 giorni** prima (24 ore se urgente); anche in videoconferenza | 16.1, 16.2 |
| Quorum del CD | Maggioranza dei componenti; delibera a maggioranza dei presenti; in parità prevale il Presidente | 16.3 |
| Consultazione telematica del CD | Proposta e link personale; termine di almeno 3 giorni; voto registrato con data, ora e link; non per il rendiconto né per l'esclusione | 16.4, 16.5 |
| Verbale del CD | Con il **voto di ciascun consigliere**, firmato da Presidente e Segretario, nel **libro dei verbali del CD**; li redige il Segretario | 16.6, 19.1 |
| Conflitto di interessi | Il consigliere lo dichiara e non partecipa alla delibera | 16.7 |
| Assenze | Decade il consigliere che non partecipa a tre riunioni consecutive senza giustificato motivo | 15.5 |
| Libri elettronici | Ammessi, se ne garantiscono integrità, conservazione e tracciabilità di ogni modifica | 23.2 |

## 2. Cosa c'è oggi nel Gestionale ASD

- **Schema senza pagine**: `gl_assemblee`, `gl_assemblee_rsvp`, `gl_verbali` e
  `gl_verbali_allegati` sono quelli del Dante, con `domanda_id` e
  `periodo_da/a` aggiunti per le domande. Le voci «Assemblee» e «Verbali»
  sono già in `config/navigazione.php`, ma non si vedono perché manca la
  rotta.
- **Permessi**: scrivono il Presidente e il Segretario; l'Amministratore
  legge. CD e Revisore oggi non vedono le due sezioni.
- **Verbale di ammissione**: `VerbaleAmmissione` scrive una bozza per ogni
  domanda votata (tipo `cd_ammissione`, testo generato), che resta in bozza
  per sempre: non c'è approvazione, numero, firma, PDF né elenco.
- **Chiusura dell'anno**: `AnniContabili::verbaliApprovazione()` propone i
  verbali di tipo `assemblea` con `data_seduta` nell'anno in corso. Oggi
  l'elenco è sempre vuoto.
- `GovernanceCd.verbale_nomina_id` c'è già: il verbale dell'Assemblea che
  ha eletto il membro.

## 3. Il codice del Dante (letto il 20/10/2026)

**Come funziona.**
- *Assemblee* (`gs_assemblee`). Tipo `cd`, `ordinaria` o `straordinaria`.
  Stati bozza → convocata → svolta, oppure rinviata → di nuovo bozza.
  - La convocazione va per email con gli allegati e un link RSVP per
    destinatario («confermata» o «assente»). Ci sono tre destinatari di
    prova e il sollecito a chi non ha risposto.
  - Il rinvio può avvisare per email e cancella i link RSVP.
  - «Segna svolta» abilita il verbale.
- *Verbali* (`gs_verbali`). Tre tipi: `assemblea` (legato a un'assemblea
  svolta), `cd_delibera` (riunione del CD con presenze e delibere numerate
  con i voti) e `cd_ammissione` (domande).
  - Corpo in HTML con l'editor Jodit, ripulito da `HtmlSanitizer`.
  - Stati bozza → approvato → archiviato.
  - L'archiviazione chiede **un file con firma digitale** (CIE `.p7m` o
    PAdES) e salva l'impronta SHA-256. Per l'Assemblea che approva il
    rendiconto blocca anche la contabilità dell'anno: impronta dei
    movimenti, `is_locked` ed email al Presidente.
  - Documenti di appoggio in PDF fusi nel PDF del verbale con Ghostscript;
    PDF/A con `PdfService`.
  - Notifica ai consiglieri, che leggono i verbali nell'Area Consiglieri
    (`/cd`, accesso con codice per email).

**Si riprende:**
- il ciclo dell'Assemblea (bozza, convocazione per email con allegati,
  conferma di presenza con link personale, sollecito, rinvio, svolta);
- il verbale legato alla seduta, con presenze e delibere numerate con i
  voti;
- gli stati del verbale fino a quello immutabile, con la copia firmata e la
  sua impronta;
- i documenti di appoggio;
- la notifica ai consiglieri.

**Si migliora** (difetti trovati nel codice):
1. **Verbali senza numero.** Non c'è una numerazione per libro: il «libro
   dei verbali» (statuto 13.4, 16.6, 23) non esiste come tale, l'elenco è
   per data di creazione.
2. **Un verbale approvato si modifica ancora.** Solo l'archiviato è
   immutabile. `update()` su un approvato cancella il PDF e lo rifà con il
   testo nuovo: il verbale «approvato» può cambiare senza traccia.
   L'approvazione, poi, non registra chi né quando nell'Audit Log.
3. **Le riunioni del CD in due modi.** Come «assemblea» di tipo `cd` con
   RSVP, o come `cd_delibera`. Due strade per la stessa cosa, con la regola
   `isVisibileComeCd()` duplicata in quattro posti (difetto del verbale #55,
   corretto tre volte).
4. **Presenze e quorum a mano.** `soci_presenti` e `soci_deleghe` sono
   numeri scritti a mano: nessun legame con il libro soci, nessun controllo
   del diritto di voto (morosi, minorenni) né del quorum (art. 14). Le
   deleghe, poi, lo statuto della Compagnia non le ammette.
5. **Voti senza controllo.** Nelle delibere del CD favorevoli + contrari +
   astenuti possono superare i presenti; l'esito si sceglie a mano e può
   contraddire i numeri. Manca il voto di ciascun consigliere (art. 16.6).
6. **Termini di convocazione non controllati.** Si convoca anche il giorno
   prima, o per una data passata. Dopo l'invio data e luogo si cambiano
   senza riconvocare: i soci hanno ricevuto l'avviso vecchio.
7. **Destinatari incompleti.** Per l'Assemblea solo i soci attivi con
   email: niente genitori dei minorenni (art. 7.3), nessun elenco per la
   posta ordinaria (art. 12.3). Per il CD le email da `gs_governance_cd`,
   non dal socio.
8. **«Svolta» prima del giorno**, senza controllo della data.
9. **Rinvio che cancella i link RSVP**: si perde la storia di chi aveva
   risposto.
10. **Allegati di ogni tipo**: per le assemblee la regola è solo `file`,
    senza limite di peso e con l'estensione presa dal nome del file.
11. **HTML libero nel verbale.** Il «sorgente» di Jodit obbliga a una
    pulizia complessa (`HtmlSanitizer`); il PDF si conta rendendolo due
    volte (`contaPagine`).
12. **Dettaglio dell'Audit Log con dati personali**: titoli e motivi dentro
    il `dettaglio` in JSON, che entra nell'hash (stesso difetto B1 di
    `da-riportare-gestionale-aps.md`).
13. **Blocco della contabilità dentro l'archiviazione del verbale.** Nel
    Gestionale ASD c'è già la chiusura dell'anno contabile, che sceglie il
    verbale (`AnniContabili::chiudi`): non si riprende, si collega.
14. **Fusione dei PDF con Ghostscript**, che può mancare sul server: se la
    fusione fallisce, il verbale esce in silenzio senza allegati.

Da riportare al Gestionale APS quando il modulo sarà fatto: i punti 1, 2, 5,
6, 9 e 12.

## 4. Proposta

### 4.1 Un concetto solo: la seduta

Una **seduta** è una riunione di un organo: **Assemblea ordinaria**,
**Assemblea straordinaria** o **Consiglio Direttivo**. Ha il suo verbale,
uno solo. Le **consultazioni telematiche** del CD sono sedute senza
riunione: oggi solo quelle delle domande (§4.6).

Due **libri**: *Verbali delle Assemblee* e *Verbali del Consiglio
Direttivo*. Un verbale entra nel suo libro, con il numero, quando diventa
**definitivo**.

Tabelle: si adattano le quattro che ci sono. `gl_assemblee` diventa la
seduta: tipo con `cd`, seconda convocazione, partecipazione a distanza,
stato `annullata`. Si aggiungono:
- `gl_sedute_presenze`: un socio o un membro del CD per riga, con presente
  in sede, a distanza o assente, e il diritto di voto alla data;
- `gl_delibere`: punto dell'ordine del giorno, testo, votazione, esito;
- `gl_delibere_voti`: voto di ciascun consigliere, solo per il CD.

Sono tutte vuote: niente da migrare.

### 4.2 Assemblea

1. **Bozza**: tipo, ordine del giorno (punti), allegati in PDF.
   - 1ª convocazione: data, ora e luogo;
   - 2ª convocazione: data e ora, proposta un'ora dopo e controllata;
   - partecipazione a distanza (sì o no, con le istruzioni del
     collegamento).
2. **Convocazione**: il gestionale controlla il termine (10 o 15 giorni) e
   prepara i destinatari:
   - i soci attivi con email, compresi i morosi (sono soci e possono
     assistere, ma non votano);
   - per i minorenni, il genitore;
   - **l'elenco di chi non ha email**, da stampare per la posta ordinaria,
     e l'**avviso da affiggere** in sede (PDF).

   L'email porta l'avviso e un link personale per dire «in sede», «a
   distanza» o «assente». Si può sollecitare chi non ha risposto. Una prova
   va solo all'indirizzo di chi la manda.
3. **Cambiare dopo la convocazione**: data, luogo e ordine del giorno non
   si cambiano più. Si **rinvia**, con il motivo e l'avviso, e si
   riconvoca con i termini di nuovo controllati. Le risposte ricevute
   restano nella storia. Un'Assemblea non tenuta si **annulla** con il
   motivo.
4. **Svolgimento**, dal giorno della seduta:
   - **presenze** dall'elenco dei soci con il **diritto di voto calcolato
     alla data**: maggiorenni con la quota dell'anno versata (Rinnovi
     quota). Si parte dalle risposte ricevute;
   - **quorum** calcolato secondo il tipo e la convocazione (§1),
     mostrato in chiaro («costituita in 2ª convocazione: 9 presenti su 31
     con diritto di voto»);
   - **presidente, segretario e scrutatori**, scelti tra i presenti.
5. **Verbale**: un riquadro per ogni punto dell'ordine del giorno, con la
   discussione (testo semplice) e, se c'è, la **delibera**:
   - votazione palese: favorevoli, contrari e astenuti, controllati sui
     presenti con diritto di voto; l'esito lo **calcola il gestionale**
     con la maggioranza richiesta (espressi, 2/3 dei presenti, 3/4 degli
     aventi diritto);
   - scrutinio segreto (elezioni): schede, voti per candidato, eletti.
     Nessun voto nominativo.
6. **Definitivo, firma, libro**: vedi §4.4.

### 4.3 Consiglio Direttivo

- **Riunione**: convocazione per email ai membri in carica (email dal
  socio), con l'ordine del giorno. Almeno 3 giorni prima, o 24 ore con la
  spunta «urgente» e il motivo. Si può tenere in videoconferenza.
- **Presenze** dai membri in carica, con il quorum (maggioranza dei
  componenti).
- Per ogni delibera, il **voto di ciascun consigliere**: favorevole,
  contrario, astenuto, oppure «non partecipa» per conflitto di interessi
  (art. 16.7). L'esito si calcola: maggioranza dei presenti, e in parità
  prevale il Presidente.
- **Avviso**: un consigliere assente a tre riunioni consecutive viene
  segnalato (art. 15.5). È solo un avviso: il giustificato motivo lo
  valuta il CD.

### 4.4 Verbale definitivo, firma e libro

- **Bozza**: si scrive e si corregge, e si vede l'anteprima del PDF.
- **«Rendi definitivo»** (Presidente o Segretario): il verbale prende il
  **numero nel suo libro**, con lock e vincolo unico. Il PDF si salva con
  l'impronta e da qui **non si modifica più** (difetto 2).
- **Copia firmata**: si carica il PDF firmato dal presidente e dal
  segretario della seduta (scansione o firma digitale). Il gestionale salva
  l'impronta. Con `FirmaDigitale::rileva` riconosce se c'è una firma
  digitale e lo mostra, ma non la chiede (domanda V2).
- **Rettifica**: un errore in un verbale definitivo si corregge con una
  **nota di rettifica** datata e motivata, aggiunta in fondo, che non tocca
  il testo originale. È l'equivalente elettronico della postilla sul libro
  cartaceo (art. 23.2: tracciabilità di ogni modifica).
- **Messa a disposizione** (art. 26.2): per i verbali dell'Assemblea,
  «Invia ai soci» manda il PDF definitivo per email (domanda V10).
- **Documenti di appoggio**: PDF allegati al verbale ed elencati nel testo
  («Allegato 1: rendiconto 2027»). Si scaricano a parte; non si fondono
  (difetto 14).
- **Registra un verbale già redatto**: per le sedute verbalizzate fuori dal
  gestionale, come l'Assemblea della B.A.S. del 16/11/2026 o la prima
  riunione del nuovo CD. Si scrivono i dati essenziali (organo, data,
  presidente e segretario, delibere in breve) e si carica la copia firmata.
  Il verbale entra nel libro con il suo numero, come gli altri.

### 4.5 Collegamenti

- **Chiusura dell'anno contabile**: il verbale da scegliere è quello
  definitivo di un'Assemblea con una delibera **«Approva il rendiconto
  dell'anno …»** (un tipo di delibera). Così `verbaliApprovazione()` non
  propone più tutte le Assemblee dell'anno.
- **Consiglio Direttivo**: dopo un'elezione, «Aggiungi un membro» propone
  il verbale da cui viene la nomina (`verbale_nomina_id`).
- **Domande**: i verbali delle consultazioni telematiche diventano verbali
  del libro del CD; il Segretario li rende definitivi (domanda V11).
- **Decadenze per morosità** (art. 9.1 b): il CD «prende atto». Una
  delibera può riportare l'elenco dei decaduti preso da Rinnovi quota: si
  propone come delibera già scritta.
- **Audit Log**: un evento per passo (convocazione, rinvio, annullamento,
  definitivo, copia firmata, rettifica, invio ai soci). Definitivo,
  rettifica e annullamento sono a rischio alto, con l'avviso agli altri.
  Nel dettaglio niente nomi né motivi (difetto 12).
- **Azzeramento**: tutte le tabelle nuove vanno nel gruppo SVUOTA; le
  cartelle dei file in `CARTELLE`.

### 4.6 Fuori da questo lavoro (dopo)

- **Consultazione telematica del CD per qualunque delibera** (art. 16.4),
  non solo per le domande. Il motore c'è già (`VotazioneCd`); va reso
  generale. Proposta: dopo, quando serve (domanda V8).
- **Voto a scrutinio segreto telematico** (art. 13.3): solo il risultato,
  per ora.
- **Area dei consiglieri o dei soci** per leggere i verbali (nel Dante
  `/cd` con codice per email): i membri del CD hanno già un utente e
  leggono dalla sezione; i soci ricevono il PDF per email.
- **Rimborsi ai volontari** del Dante: modulo Volontari.

## 5. Domande per Paolo

**V1. Numero dei verbali.** Proposta: progressivo **per libro e per anno**
(«Assemblee 2027 n. 1», «CD 2027 n. 4»), assegnato quando il verbale
diventa definitivo; mai riusato. In alternativa un progressivo unico che
non riparte ogni anno.

**V2. Firma.** Il Dante chiede la firma digitale (CIE o PAdES) per
archiviare. Proposta per la Compagnia: basta la **scansione del verbale
firmato a mano** dal presidente e dal segretario; la firma digitale è
facoltativa (il gestionale la riconosce e lo scrive).

**V3. Testo del verbale.** Proposta: **testo semplice** per ogni punto
(paragrafi e a capo), niente editor HTML, per evitare il problema della
pulizia del Dante. Il PDF lo impagina sulla carta intestata.

**V4. Termini di convocazione.**
- Assemblea: con meno di 10 giorni (15 per la straordinaria) la
  convocazione **non parte**.
- CD: con meno di 3 giorni parte solo con la spunta «urgente» e il motivo,
  e comunque non meno di 24 ore prima.

Va bene?

**V5. Destinatari dell'Assemblea.** Proposta:
- i soci attivi, morosi compresi (ricevono, ma non votano);
- per i minorenni, il genitore;
- l'elenco per la posta di chi non ha email;
- l'avviso da affiggere in PDF.

**V6. Diritto di voto e quorum calcolati.** Proposta: il diritto di voto
alla data della seduta lo calcola il gestionale (maggiorenne e quota
dell'anno versata, da Rinnovi quota), e così il quorum. Si può
correggere a mano un singolo socio con il motivo (per esempio una quota
versata e non ancora registrata).

**V7. Voti.**
- Assemblea: numeri per delibera; per le elezioni solo i risultati dello
  scrutinio.
- CD: il voto di ciascun consigliere (art. 16.6).

Esiti calcolati dal gestionale. Va bene?

**V8. Consultazione telematica per le delibere generiche** (oltre alle
domande): adesso o dopo? Proposta: dopo, per stare nei tempi.

**V9. Conferma di presenza (RSVP).** Proposta: sì, con tre risposte (in
sede, a distanza, assente), solo per l'Assemblea; la storia resta anche
dopo un rinvio. Per il CD non serve.

**V10. Messa a disposizione dei soci.** Proposta: «Invia ai soci» manda il
PDF del verbale definitivo dell'Assemblea per email, quando lo decide il
Presidente o il Segretario. La copia resta in sede.

**V11. Verbali delle domande.** Proposta: restano uno per domanda. Il
Segretario li rende definitivi dall'elenco del libro del CD, anche più
insieme («Rendi definitivi i 3 verbali chiusi»), con il numero.

**V12. Chi fa che cosa.** Proposta:
- scrivono il Presidente e il Segretario (come oggi);
- il CD (Vicepresidente, Tesoriere, Consiglieri) e il Revisore leggono
  sedute e verbali;
- l'Amministratore legge.

**V13. Verbali registrati da fuori** (§4.4): servono per l'Assemblea del
16/11/2026 e per il primo CD? Proposta: sì, nella stessa versione.

## 6. Tavole da disegnare (dopo le risposte)

- **VB-1** Libri dei verbali: due schede (Assemblee, CD), numero, data,
  stato, copia firmata; computer e telefono.
- **AS-1** Seduta in bozza: tipo, 1ª e 2ª convocazione, distanza, ordine
  del giorno, allegati.
- **AS-2** Convocazione: termine, destinatari (email, genitori, posta),
  prova, avviso da affiggere.
- **AS-3** Scheda della seduta convocata: risposte, sollecito, rinvio,
  annullamento.
- **AS-4** Presenze e quorum (Assemblea), con il diritto di voto calcolato.
- **CD-5** Riunione del CD: presenze e voto di ciascun consigliere per
  delibera.
- **VB-2** Verbale per punti con le delibere e l'esito calcolato.
- **VB-3** Definitivo, copia firmata, rettifica, invio ai soci.
- **VB-4** PDF del verbale (Assemblea e CD).
- **VB-5** Registra un verbale già redatto.
- **RS-1** Pagina pubblica della conferma di presenza (telefono).

## 7. Stima

Lavoro più grande degli anticipi. Proposta in due versioni, ognuna con il
suo collaudo:
- **v0.44.0 — Verbali e CD**:
  - libri, verbale definitivo, firma e rettifica;
  - riunioni del CD con il voto di ciascuno;
  - verbali registrati da fuori;
  - verbali delle domande nel libro;
  - collegamento con la chiusura dell'anno.

  Serve dal 16/11 (V13).
- **v0.45.0 — Assemblee**: convocazione con i termini, destinatari,
  conferma di presenza, rinvio, presenze con il diritto di voto, quorum,
  elezioni, invio ai soci. Serve per l'Assemblea di aprile 2027: se non
  sta in novembre, va a gennaio.
