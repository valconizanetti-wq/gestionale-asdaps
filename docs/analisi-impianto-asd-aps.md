# Riflessione sull'impianto — B.A.S. come A.S.D., modello comune con l'APS

**Data:** 13/10/2026 · **Base:** v0.39.1 + analisi rendiconto (commit `3e8adea`)
**Copia nel progetto:** `claude/analisi-impianto-asd-aps.md`
**Stato:** analisi richiesta da Paolo; nessuna decisione presa.

## 0. In breve

Le tre premesse di Paolo (13/10/2026):
1. nel 2027 la B.A.S. si gestisce **come un'A.S.D.**: statuto, contabilità,
   privacy e bilanci uguali, cambia solo la denominazione;
2. il gestionale resta **sull'impianto dell'APS** (Dante), con qualche
   libertà (per esempio le categorie), per avere un modello unico da
   replicare per altre associazioni;
3. **semplicità d'uso** che rispetti normativa, rigore e controllo.

Esito: le premesse reggono. La normativa dell'A.S.D. chiede **meno**
dell'APS sul bilancio e **qualcosa in più** sullo sport. L'impianto
dell'APS si può quindi tenere quasi tutto. I punti critici veri sono sei:

| # | Punto critico | Gravità |
|---|---|---|
| C1 | Tre basi di codice che si allontanano (Dante `gs_`, Core `gl_`, ASD) | alta, strategica |
| C2 | Contabilità costruita su `gestionale-acv` invece che sul modello dell'APS | alta |
| C3 | Rendiconto e preventivo progettati più complessi del necessario | media (si corregge ora) |
| C4 | Verbali, Assemblee e Governance CD mancano, ma da loro dipendono le approvazioni | alta, di calendario |
| C5 | Moduli «si tiene tutto» pesanti per una realtà di circa 30 soci | media |
| C6 | Calendario fino al 01/01/2027 con azzeramento e preventivo 2027 | alta |

## 1. Cosa chiede la normativa a un'A.S.D. (e cosa cambia rispetto all'APS)

| Ambito | A.S.D. (D.Lgs. 36/2021, L. 289/2002, TUIR) | APS (D.Lgs. 117/2017) | Nel gestionale |
|---|---|---|---|
| Statuto | Contenuti dell'art. 7 D.Lgs. 36/2021, tra cui l'obbligo dei rendiconti e le modalità di approvazione | Art. 21 CTS | Fatto (1.0, uguale per B.A.S. e A.S.D.) |
| Registro | RASD (Sport e Salute): tesserati, attività, documenti | RUNTS | Modulo Compliance RASD solo dal 2028 |
| Rendiconto | **Obbligatorio, schema libero**; approvato in Assemblea; non si deposita al RASD | **Schema obbligatorio** (DM 5/3/2020, Modello D per cassa sotto 220.000 €), deposito al RUNTS | Da fare |
| Schema consigliato | CNDCEC e FNC, «Schemi di bilancio delle ASD» (13/12/2023): ripresi dagli schemi del Terzo settore, con una versione per cassa | — | **Si può usare il Modello D anche per l'A.S.D.** |
| Preventivo | Non richiesto | Non richiesto | Scelta interna (Paolo: sì, Assemblea entro il 31/12) |
| Istituzionale / diverse | Richiesto (statuto 22.2; regime 398) | Richiesto (sezioni A e B del Modello D) | Coincide con le sezioni A/B del Modello D |
| Raccolte fondi | Rendiconto separato (statuto 22.4) | Sezione C del Modello D | Coincide |
| Libri sociali | Soci, verbali di Assemblea e CD (statuto art. 26; codice civile per le associazioni) | Art. 15 CTS | Libro soci fatto; **verbali mancanti** |
| Fiscale | Non commerciale (art. 148 TUIR); dal 2028 opzione 398: plafond, prospetto dei proventi commerciali, tracciabilità | Regime del Terzo settore | 2027: niente di commerciale |
| Sport | Affiliazione EPS, tesseramento, certificati medici, **tutela dei tesserati** (art. 16 D.Lgs. 39/2021, statuto art. 28), volontari sportivi (art. 29 D.Lgs. 36/2021) | — | Affiliazione, tessere e polizza fatti; certificati e tecnici in standby; **tutela non tracciata** |
| Privacy, audit | Uguale | Uguale | Fatto |

Conclusione della tabella: tutto ciò che riguarda soci, organi, contabilità
per cassa, bilanci e privacy può essere **lo stesso codice** per APS e
A.S.D. Le differenze stanno in tre blocchi attivabili:
- **registro** (RUNTS o RASD);
- **regime fiscale** (Terzo settore, 398, non commerciale);
- **sport** (EPS, tessere, certificati, tutela dei tesserati).

Il profilo dell'ente per periodi (`haModulo('rasd'|'l398'|'runts')`) è già
la leva giusta per accenderli.

**Limite della premessa 1.** Nel 2027 la B.A.S. può gestirsi come
un'A.S.D., ma per legge non lo è. Non può iscriversi al RASD né optare per
la 398, e non può chiamarsi A.S.D. sui documenti. I processi sono identici;
denominazione, registro e regime fiscale no. Nel 2027 senza attività
commerciali il regime non cambia nulla in pratica. Anche per la B.A.S. va
verificato con CSEN se servono già il responsabile per la tutela dei
tesserati e i modelli dell'art. 16 D.Lgs. 39/2021: lo statuto li prevede
(art. 17.1.8 e art. 28).

## 2. Stato attuale rispetto alle premesse

| Area | Stato | Rispetto alle premesse |
|---|---|---|
| Statuto unico B.A.S./A.S.D. | Fatto | ✓ premessa 1 |
| Profilo dell'ente per periodi | Fatto | ✓ però più ricco del necessario: con la premessa 1 il periodo A.S.D. cambia solo denominazione, RASD e regime. L'«informativa 2.0 da scrivere» non serve più se il titolare si legge dal profilo (oggi il nome è scritto nel testo) |
| Domande online, voto del CD, privacy link | Fatto, più avanti del Dante | ✓ da riportare (D1…) |
| Soci, quote, ricevute, promemoria | Fatto | ✓ generale |
| Tessere CSEN, affiliazione, polizza | Fatto | blocco «sport» |
| Prima Nota | Fatta, impostata su `gestionale-acv` | ✗ premessa 2: categorie senza codice Modello D, conto `banca` invece di `cc` (vedi C2) |
| Scontrini con OCR | Fatto | in più rispetto al Dante, generale |
| Scadenzario, Audit Log | Fatto | ✓ |
| Rendiconto e preventivo | Analisi | da riallineare (C3) |
| Verbali, Assemblee, Governance CD | **Mancano** (tabelle del core sì, schermate no) | ✗ (C4) |
| Anticipi, riconciliazione, cassa mensile | Da fare | peso da valutare (C5) |

## 3. Punti critici

### C1. Tre basi di codice che si allontanano

Il Dante (`gs_*`), il Core (`gl_*`) e il Gestionale ASD (derivato dal Core)
sono tre alberi. `da-riportare-gestionale-aps.md` elenca già **circa 40
voci** da portare al Dante: sicurezza (A1–A13), privacy (B1–B6), Audit
(C1–C10), funzioni (D1–D13), PDF ed email. Ogni modulo nuovo dell'ASD
allunga l'elenco. Se l'obiettivo è un modello da replicare, conviene
decidere quale albero sarà il modello:
- **proposta**: il Gestionale ASD diventa il nuovo Core, con i moduli
  attivati dal profilo dell'ente; il Dante ci migra in seguito, invece di
  ricevere le correzioni una a una;
- in alternativa il Dante resta il riferimento e l'ASD rinuncia alle
  differenze di impianto (soprattutto contabili).

Non serve deciderlo oggi, ma decide come si fa la contabilità (C2).

### C2. Contabilità impostata su `gestionale-acv`

Con la decisione del 06/10 la Prima Nota ha preso da `gestionale-acv` le
33 categorie, i tag e la regola dei saldi, e dal Dante giroconti, allegati
ed esportazioni. Ne risultano tre scostamenti dall'impianto dell'APS:
- le categorie non hanno il **codice del Modello D** (`codice_dm39`
  esiste ma è stato deciso di lasciarlo vuoto): senza, il rendiconto
  Modello D non si calcola;
- il conto `banca` (Dante: `cc`);
- `uso` delle categorie e righe «solo bilancio» (Attivo/Disavanzo) che il
  Dante non ha: nel Modello D il riporto è il saldo iniziale, non una
  categoria.

La «libertà» che Paolo chiede si ottiene così: **le categorie restano
libere** (le 33 della B.A.S.), ma **ognuna porta il codice della riga del
Modello D** (EA.1, UA.2, UE.4…). Si scrive come si è abituati, e il
rendiconto esce nello schema riconosciuto. È lo stesso meccanismo del
Dante (`calcolaRendiconto()` per `codice_dm39`). Mappa da fare con
Paolo, per esempio:
- «Quote associative» → EA.1;
- «Contributi e donazioni da terzi» → EA.4 o EA.6;
- «Interessi attivi» → ED.1;
- «Affiliazione EPS» e «Tesseramento Soci EPS» → UA.5;
- «Polizze Assicurative» → UA.2;
- «Taglio erba» e manutenzioni → UA.2 o UE.2;
- «Acquisto attrezzature» → UF.1;
- «Imposte e tasse» → UE.4;
- «Gare, manifestazioni e iniziative» → EA o EB a seconda della natura
  (dal 2028).

### C3. Rendiconto e preventivo più complessi del necessario

Le risposte del 13/10 e il materiale di `gestionale-acv` hanno portato
stati intermedi, quantità × importo unitario, avanzo presunto, pareggio,
variazioni con soglia e una scadenza nuova. Riletti con le tre premesse:

**Rendiconto** = quello del Dante (Modello D per cassa, sezioni A–G
calcolate dalle categorie, confronto con l'anno prima, avanzo/disavanzo,
saldi di cassa e banca iniziali e finali, relazione, firme, PDF con codice
di verifica, approvazione con data e verbale, copia firmata e
archiviazione). Con quattro aggiunte già decise:
- approvazione e chiusura dell'anno nello stesso passo;
- riepilogo per tag;
- scostamenti dal preventivo;
- data di messa a disposizione dei soci (statuto 22.3).

Niente stato «proposto»: la data della riunione del CD è un campo.

**Preventivo** = quello del Dante: righe con descrizione, importo e
categoria (tag facoltativo), consuntivo per riga calcolato dalla Prima
Nota, scostamento, approvazione con data e verbale, PDF. Rispetto alle
risposte del 13/10:
- **quantità × importo unitario**: facoltativi (utili per «25 soci ×
  70 €», inutili per una spesa una tantum), non obbligatori come in
  `gestionale-acv`;
- **pareggio**: la riga «avanzo/disavanzo previsto» calcolata, nessun
  blocco;
- **avanzo dell'anno prima**: nel preventivo è una **stima scritta a
  mano** (l'anno non è finito; per il 2027 sta in `gestionale-acv`), nel
  rendiconto è il saldo di apertura vero;
- **variazioni**: rimandate. Il confronto e la relazione spiegano gli
  scostamenti; se un anno servisse davvero una variazione, si approva in
  Assemblea un preventivo aggiornato (stesso documento, nuova versione).
  È la soluzione semplice e rigorosa, ma è da confermare perché Paolo ha
  detto sì alle variazioni;
- **31/12**: una scadenza dello Scadenzario e un promemoria, non una
  regola del codice.

Così rendiconto e preventivo sono **un solo modulo, uguale per APS e
A.S.D.**; cambia solo il titolo delle sezioni A/B («attività di interesse
generale» o «attività istituzionali sportive»).

### C4. Verbali, Assemblee e Governance CD mancano

Approvazione del preventivo, approvazione del rendiconto, chiusura
dell'anno, decadenze dei soci e verbale di ammissione puntano tutti a un
**verbale**, e il modulo non c'è. Il Dante ha Verbali, Assemblee,
convocazioni con risposta (RSVP), Governance CD e Area Consiglieri già in
uso (`VerbaliController`, `AssembleeController`, `GovernanzaController`).
Proposta: **portarli dal Dante**, adattati (privacy e audit dell'ASD),
invece di progettarli da zero e dopo i bilanci. È anche il modo più
diretto per ridurre C1.

### C5. Moduli «si tiene tutto» pesanti per la dimensione dell'ente

Decisione del 06/10: anticipi con nota di rimborso, riconciliazione
bancaria con importazione dell'estratto, chiusura mensile della cassa,
tag e Progetti. Per un'associazione di circa 30 soci con poche centinaia
di movimenti l'anno:
- **anticipi**: servono (spese pagate di tasca propria), da tenere;
- **riconciliazione bancaria** e **chiusura mensile della cassa**:
  rigorose ma pesanti; proposta di farle diventare **moduli attivabili**,
  spenti per la Compagnia nel 2027;
- **tag e Progetti**: si sovrappongono; per la Compagnia bastano i tag,
  Progetti spento finché non arriva un bando.

Regola generale: la semplicità d'uso viene dal **non mostrare** ciò che
l'ente non usa, non dal togliere il codice.

### C6. Calendario fino al 01/01/2027

Restano circa 11 settimane. Con la regola del 31/12 il **preventivo 2027
va approvato in Assemblea entro fine 2026**: è l'unico pezzo di bilancio
urgente, perché il primo rendiconto nel gestionale (2027) serve entro
aprile 2028. Sequenza proposta:
1. preventivo semplice (C3) sul server entro novembre, escluso
   dall'azzeramento; oppure approvato fuori e registrato dopo;
2. Verbali e Assemblee dal Dante (C4), almeno per registrare
   l'approvazione;
3. anticipi;
4. azzeramento e apertura a fine dicembre;
5. nel 2027, con calma: rendiconto Modello D, mappa delle categorie,
   riconciliazione e cassa se si accendono.

## 4. Decisioni da prendere

1. **Modello di riferimento** (C1): il Gestionale ASD diventa il nuovo
   Core da cui ripartire anche per il Dante? Oppure resta un derivato?
2. **Modello D anche per l'A.S.D.** (C2), con le categorie libere che
   portano il codice della riga del Modello D?
3. **Rendiconto e preventivo «come il Dante»** (C3), con le quattro
   aggiunte; variazioni rimandate; quantità e prezzo unitario facoltativi?
4. **Verbali e Assemblee dal Dante, prima del rendiconto** (C4)?
5. **Moduli attivabili** (C5): riconciliazione, cassa mensile e Progetti
   spenti per la Compagnia nel 2027?
6. **Preventivo 2027** (C6): nel gestionale prima dell'azzeramento, o
   approvato fuori? E in che data è l'Assemblea?
7. **Tutela dei tesserati**: chiedere a CSEN se vale già per la B.A.S. nel
   2027; nel gestionale basterebbe un riquadro nel profilo dell'ente
   (responsabile, data di adozione del modello e del codice di condotta).

## Fonti

- [Il bilancio per le ASD e le SSD: quali regole applicare (EC News, 30/09/2025)](https://www.ecnews.it/fiscale/societa-e-bilancio/bilancio/il-bilancio-per-le-asd-e-le-ssd-quali-regole-applicare-per-la-sua-redazione/)
- [Schemi di bilancio delle ASD dal CNDCEC (eDotto, 14/12/2023)](https://edotto.com/articolo/associazioni-sportive-dilettantistiche-schemi-di-bilancio-dal-cndcec)
- Statuto pubblicato (`resources/content/statuto/1.0.md`), MANUALE §2, `profilo-ente.md`,
  `da-riportare-gestionale-aps.md`, codice del Gestionale APS allegato il
  13/10/2026 (`BilancioController`, `BilancioConsuntivoController`).
