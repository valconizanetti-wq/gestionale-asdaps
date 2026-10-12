# Analisi — Rendiconto (consuntivo) e preventivo

**Data:** 12/10/2026 (sera) · **Base:** v0.39.1 (commit `bd99c40`, 139 test verdi)
**Stato:** analisi con domande a Paolo; tavole e codice dopo le risposte.
**Copia nel progetto:** `claude/analisi-rendiconto-preventivo.md`.

Materiale letto: `docs/specifiche/bilancio.md`, MANUALE §2.4, §6.1, §6.2,
`prima-nota.md` (anni contabili, categorie, tag), `scadenzario.md`,
`permessi.md`, statuto pubblicato (`resources/content/statuto/1.0.md`),
migration e model di `gl_bilancio`, `gl_bilancio_voci`, `gl_verbali`,
`gl_anni_contabili`, `gl_tag`, servizio `AnniContabili`,
`PrimaNotaCategoriaSeeder`; nel progetto `analisi-contabilita-esistente.md`
e `acv-contabilita.md` (preventivo e consuntivo di `gestionale-acv`).

---

## 1. Cosa chiedono legge e statuto

**Legge** (già verificata, MANUALE §2.4): rendiconto economico-finanziario
annuale per cassa, senza modello ministeriale né deposito pubblico; redatto
dal CD, approvato dall'Assemblea entro 4 mesi (6 con motivate esigenze);
la mancata approvazione può far perdere le agevolazioni (art. 148 TUIR);
dal regime 398 (2028) distinzione istituzionale/commerciale; conservazione
5 anni, copia allegata al verbale.

**Statuto pubblicato** (1.0, uguale per B.A.S. e A.S.D.): articoli che
toccano il modulo, alcuni non ancora riportati nella scheda `bilancio.md`.

| Art. | Regola | Conseguenza nel gestionale |
|---|---|---|
| 22.1 | Esercizio 1° gennaio – 31 dicembre | Un rendiconto per anno solare (già così: `anno`) |
| 17.1.4, 19.2 | Il CD redige il rendiconto; il Tesoriere lo predispone con il CD | Bozza del Tesoriere → **proposta del CD** → approvazione dell'Assemblea |
| 16.5 | **La consultazione telematica non può essere usata per la proposta del rendiconto** | La proposta del CD nasce da una **riunione** (verbale del CD), non dal voto via link |
| 12.2.1, 14.1.1, 22.2 | Assemblea entro 4 mesi (6 per particolari esigenze) | Scadenzario «Approvazione del rendiconto» al 30/04 (già fatto) |
| 22.2 | Il rendiconto **distingue** entrate e uscite istituzionali da quelle delle attività diverse e commerciali | Due sezioni nel documento; per la B.A.S. (2027) quella commerciale è vuota |
| 22.3 | Depositato in sede e messo a disposizione dei soci, anche per via telematica, **almeno 15 giorni prima** dell'Assemblea | Data di messa a disposizione registrata; PDF della proposta; avviso se meno di 15 giorni |
| 22.4 | Raccolta pubblica di fondi → **rendiconto separato** | Caso raro: vedi domanda 12 |
| 22.5 | Carattere secondario delle attività diverse: nella relazione o, se per cassa, **in un'annotazione in calce** | Testo in calce al rendiconto (`note_integrative` esiste già) |
| 20.2 | L'Organo di controllo (facoltativo) redige una **relazione sul rendiconto** | Allegato facoltativo, solo se l'organo c'è |
| 13.4 | Verbale dell'Assemblea firmato e trascritto | Collegamento al verbale (modulo Verbali, dopo) |

**Il preventivo non compare nello statuto.** Né la legge né lo statuto lo
rendono obbligatorio: è una scelta di buona gestione (in `gestionale-acv`
lo approvava l'Assemblea e serviva ad aprire l'anno). Nel Gestionale ASD
può passare all'Assemblea come «altro argomento sottoposto dal CD» (art.
14.1.7). Decide Paolo (domanda 2).

## 2. Cosa c'è già nel Gestionale ASD

- **`gl_bilancio`**: `anno`, `tipo` (preventivo/consuntivo), `stato`
  (enum `bozza`/`approvato`), `approvato_il`, `data_assemblea`,
  `numero_verbale` (testo), `verbale_approvazione_id` (FK `gl_verbali`),
  `note_integrative`, `pdf_approvato_path`, `codice_documento` (residuo
  RUNTS), `note`, `creato_da`; unico per `(anno, tipo)`.
- **`gl_bilancio_voci`**: `tipo` (entrata/uscita), `codice` (residuo DM39),
  `descrizione`, `importo_preventivo`, `ordine`, `categoria_id`.
- **Model** `Bilancio`, `BilancioVoce` (senza AuditObserver: va aggiunto);
  `RiskMatrix` già classifica come alto l'approvazione e l'eliminazione di
  un `Bilancio`.
- **Menu**: «Bilancio» nella navigazione (rotta `bilancio.index` mai
  costruita); sezione `bilancio` nei permessi: Presidente e Tesoriere,
  Revisore in lettura.
- **Categorie**: 33, con `uso` (`movimenti`, `solo_bilancio`, `sistema`),
  `flag_non_istituzionale`, `natura_da_verificare`; «Attivo anno
  precedente» (`ATTIVO_PREC`) e «Disavanzo anno precedente»
  (`DISAVANZO_PREC`) sono `solo_bilancio`.
- **Anni contabili**: saldi di apertura (primo anno a mano, poi ereditati),
  saldi finali calcolati con lo scope `effettivi` (esclusi gli anticipi non
  rimborsati), chiusura con lista di controllo e verbale dell'Assemblea
  scelto dall'elenco (oggi vuoto), fotografia dei saldi, correzioni motivate
  sull'anno chiuso, riapertura del Presidente.
- **Scadenzario**: «Approvazione del rendiconto» ricorrente al 30/04;
  `scadenzario.md` prevede già che diventi «Fatta» con l'approvazione.
- **Impostazione** `contabilita_anno_avvio` (2027): prima di quell'anno i
  movimenti stanno in `gestionale-acv`.

## 3. Lacune e incoerenze trovate

1. **`tag_id` sulle voci del preventivo**: deciso il 06/10/2026
   («`tag_id` su movimenti e voci del preventivo»), ma la migration
   `000095` lo ha aggiunto solo a `gl_prima_nota`. Manca su
   `gl_bilancio_voci`.
2. **Un solo importo per voce** (`importo_preventivo`): il consuntivo
   approvato non ha dove congelare le cifre. `bilancio.md` §4 dice che si
   calcolano sempre al volo dalla Prima Nota, ma così un rendiconto
   approvato cambierebbe a ogni correzione motivata sull'anno chiuso, contro
   il principio di `gestionale-acv` («ogni bilancio pubblicato resta
   congelato»).
3. **Stati insufficienti**: manca il passaggio intermedio «proposto dal CD»
   (art. 17.1.4, 16.5) che in `gestionale-acv` era «verificato dal CD /
   pubblicato». L'enum è `bozza`/`approvato`.
4. **Messa a disposizione dei soci** (art. 22.3): nessun dato.
5. **Saldi del raccordo** cassa/banca: `bilancio.md` §4 li vuole calcolati
   al volo; per il documento approvato vanno fotografati (oggi lo fa solo
   la chiusura dell'anno contabile, che è un passo distinto).
6. **Doppio passo all'Assemblea**: oggi l'approvazione del rendiconto
   (`gl_bilancio`) e la chiusura dell'anno (`gl_anni_contabili`) chiedono
   ciascuna il verbale; la chiusura non controlla che il rendiconto sia
   approvato. Vanno legate (domanda 9).
7. **`bilancio.md` §3.4** dice che il termine di approvazione si genera
   all'apertura di ogni consuntivo: superato dalla v0.38.0 (scadenza
   ricorrente al 30/04). Da correggere nella scheda.
8. **Model senza audit**: `Bilancio` e `BilancioVoce` non hanno
   l'`AuditObserver`, mentre `RiskMatrix` li prevede.
9. **Preventivo 2027 e azzeramento**: se il preventivo 2027 si scrive sul
   server prima dell'azzeramento di fine dicembre, è un dato **vero** in
   mezzo ai dati di prova: la procedura di azzeramento (J12…N12) non deve
   cancellarlo (domanda 14).
10. **Consuntivo 2026 fuori dal gestionale**: il preventivo 2027 non può
    mostrare in automatico la colonna «consuntivo dell'anno prima»
    (domanda 6).

## 4. Come lo faceva `gestionale-acv`

- **Preventivo**: righe di dettaglio (descrizione, importo, categoria, tag)
  → bozza → pubblicato (dopo la verifica del CD, ancora modificabile) →
  approvato dall'Assemblea (data + almeno un allegato) e congelato; serviva
  ad aprire l'anno. Principio: **mai un importo secco senza dettaglio**.
- **Consuntivo**: «pubblicato» dal primo giorno, calcolato dal vivo dai
  movimenti (anche quelli in bozza); chiusura con verifica del CD, nessun
  movimento da quadrare, allegato e data dell'Assemblea; i totali per
  categoria diventano una fotografia congelata e **l'anno si chiude nella
  stessa transazione**; dopo, vista semplice dei soli totali.
- Allegati multipli per il verbale (`conta_bilancio_verbali`); riepilogo
  per tag; esportazione .xlsx.

## 5. Proposta

### 5.1 Un documento, due momenti

Una pagina **Bilancio** con l'elenco per anno (preventivo e rendiconto
affiancati, con lo stato) e una scheda per documento.

**Preventivo dell'anno N** (di solito scritto in autunno dell'anno N−1):
1. *Bozza* — il Tesoriere (o il Presidente) scrive le **righe di
   dettaglio**: categoria (obbligatoria, tra quelle attive d'uso
   «movimenti» o «solo bilancio»), descrizione, importo, tag facoltativo
   («trova o crea», come in Prima Nota). Il documento le somma per
   categoria; la riga «Attivo/Disavanzo anno precedente» è la stima del
   fondo di partenza.
2. *Proposto dal CD* — data della riunione del CD e riferimento del verbale
   (testo; poi il verbale vero); da qui il PDF «Proposta» per i soci. Le
   righe restano modificabili, come il «pubblicato» di `gestionale-acv`,
   per le correzioni chieste in Assemblea.
3. *Approvato* — data dell'Assemblea, riferimento del verbale, scansione
   del verbale firmato (finché manca il modulo Verbali); il documento si
   congela e si genera il PDF definitivo con l'impronta (hash) registrata
   nell'Audit Log.

**Rendiconto dell'anno N** (dall'avvio della contabilità, quindi dal 2027):
1. *In corso* — calcolato **dal vivo** dalla Prima Nota, sempre
   consultabile, con le stesse regole dei saldi: movimenti `effettivi`
   (senza anticipi non rimborsati), **giroconti esclusi**, entrate e uscite
   per categoria. Accanto, il preventivo approvato e lo **scostamento**
   (importo e %).
2. *Proposto dal CD* — possibile solo ad anno finito e senza «Uscite da
   classificare»; data della riunione del CD (non una consultazione
   telematica: art. 16.5), data di messa a disposizione dei soci (avviso
   se meno di 15 giorni prima dell'Assemblea, art. 22.3), annotazione in
   calce (art. 22.5), relazione dell'Organo di controllo se c'è.
3. *Approvato* — data dell'Assemblea e verbale; le cifre per categoria, i
   saldi iniziali e finali di cassa e banca e il riepilogo per tag si
   **fotografano**; PDF definitivo con hash; lo Scadenzario segna
   «Approvazione del rendiconto» come fatta; l'anno contabile si chiude
   (domanda 9).

Dopo l'approvazione, se una correzione motivata sull'anno chiuso cambia la
Prima Nota, il rendiconto resta quello approvato e la scheda mostra un
avviso rosso: «La Prima Nota del 2027 differisce dal rendiconto approvato
di X €» (con il dettaglio per categoria).

### 5.2 Forma del rendiconto (per cassa)

```
RENDICONTO ECONOMICO-FINANZIARIO 2027 (per cassa)
                                     Preventivo  Consuntivo  Scostamento
A. ATTIVITÀ ISTITUZIONALI
   Entrate   Quote associative          2.100,00    2.240,00   +140,00
             …
   Uscite    Affiliazione EPS             …
             …
   Risultato dell'attività istituzionale
B. ATTIVITÀ DIVERSE E COMMERCIALI          (2027: «Nessuna»)
RISULTATO DELLA GESTIONE (A + B)
RACCORDO CON CASSA E BANCA
   Saldo iniziale cassa / banca / totale   (= «Attivo/Disavanzo anno prec.»)
   + entrate − uscite
   Saldo finale cassa / banca / totale
Anticipi non ancora rimborsati al 31/12 (fuori saldo), se ce ne sono
Riepilogo per tag (Tettoia, …)
Annotazione in calce (art. 22.5)        Firme: Presidente, Tesoriere
```

La natura (istituzionale o commerciale) viene da `flag_non_istituzionale`
della categoria e si fotografa all'approvazione, così un cambio successivo
della categoria non altera i rendiconti già approvati.

### 5.3 Schema (da confermare nelle tavole)

Una migration, nessuna tabella nuova obbligatoria:

- `gl_bilancio`: `stato` con `proposto` in mezzo; `proposta_cd_il`,
  `proposta_cd_rif`, `proposta_verbale_id` (FK `gl_verbali`, nullable:
  si completa con il modulo Verbali); `messo_a_disposizione_il`;
  `verbale_firmato_path` (scansione provvisoria);
  `relazione_controllo_path`; `pdf_approvato_hash`; `approvato_da`;
  `saldo_iniziale_cassa/banca`, `saldo_finale_cassa/banca` (fotografia);
  `riepilogo_tag` (JSON, fotografia).
- `gl_bilancio_voci`: `tag_id` (lacuna 1), `importo_consuntivo`
  (fotografia, nullable), `istituzionale` (fotografia della natura), e
  l'etichetta a video «Importo» al posto di `importo_preventivo` nel
  preventivo. Nel rendiconto approvato una voce per categoria con preventivo
  (copiato) e consuntivo: il documento congelato sta tutto in sé.
- `Bilancio`, `BilancioVoce` con `AuditObserver`; eventi
  `BILANCIO_PROPOSTO`, `BILANCIO_APPROVATO` (alto), `BILANCIO_PDF`.

### 5.4 Collegamento al verbale (regola di Paolo)

Finché non c'è il modulo Verbali: data + riferimento testuale (campi
`data_assemblea`, `numero_verbale` già presenti) + scansione del verbale
firmato. Quando arriva il modulo: nella scheda del documento approvato
compare «Collega il verbale» (solo se `verbale_approvazione_id` è vuoto),
che sceglie dall'elenco delle Assemblee come la chiusura dell'anno; il
testo resta come copia. Lo stesso per la proposta del CD
(`proposta_verbale_id`, verbali del CD). Nessun dato da reinserire.

### 5.5 Permessi

Sezione `bilancio`: Presidente e Tesoriere scrivono; Revisore in lettura
(e carica la relazione se è l'Organo di controllo); proposta: Segretario e
Consigliere in lettura (domanda 11). Solo il Presidente registra
l'approvazione? (domanda 11).

## 6. Domande a Paolo

Per ognuna la mia proposta; basta «sì» o la correzione.

**Preventivo**
1. **Righe di dettaglio** (categoria + descrizione + importo + tag,
   sommate per categoria) come in `gestionale-acv`, o un solo importo per
   categoria? *Proposta: righe di dettaglio.*
2. **Chi approva il preventivo**: l'Assemblea (come oggi) o basta il CD,
   visto che lo statuto non lo prevede? E l'approvazione deve bloccare
   qualcosa (in `gestionale-acv` apriva l'anno)? *Proposta: CD propone,
   Assemblea approva, nessun blocco.*
3. **Pareggio**: il preventivo deve chiudere in pareggio (entrate + attivo
   precedente = uscite)? *Proposta: si mostra la differenza con un avviso,
   senza bloccare.*
4. **Variazioni in corso d'anno**: si possono fare? *Proposta: no nella
   prima versione; il confronto usa il preventivo approvato; se servirà,
   «variazione» con delibera e nuova versione.*

**Rendiconto**
5. **Attivo/Disavanzo anno precedente** nel rendiconto: riga calcolata
   dai saldi di apertura (cassa + banca), così si confronta con la stima
   del preventivo? *Proposta: sì, calcolata, non scritta a mano.*
6. **Colonna «anno precedente»**: dal 2028 automatica (consuntivo 2027).
   Per il preventivo 2027 la si scrive a mano dal consuntivo 2026 di
   `gestionale-acv`, o niente? *Proposta: niente per il 2027.*
7. **Scostamenti**: importo e percentuale per categoria, evidenziati sopra
   una soglia (es. ±20%)? *Proposta: sì, soglia fissa 20%.*
8. **Tag**: riepilogo per tag in fondo al rendiconto (e nel PDF)?
   *Proposta: sì, solo i tag con movimenti nell'anno.*
9. **Approvazione e chiusura dell'anno insieme**: un solo passo
   «Registra l'approvazione dell'Assemblea» che congela il rendiconto e
   chiude l'anno contabile nella stessa transazione (come `gestionale-acv`),
   con la lista di controllo di oggi? *Proposta: sì; la pagina Anni
   contabili rimanda al Bilancio.*

**Documenti e persone**
10. **PDF**: «Proposta» (per i soci, 15 giorni prima) e «Approvato» (con
    hash, da allegare al verbale e pubblicare sul sito a mano)? Serve anche
    l'esportazione Excel/CSV? *Proposta: due PDF e un CSV.*
11. **Chi fa cosa**: Tesoriere e Presidente scrivono; registrare proposta e
    approvazione anche il Tesoriere o solo il Presidente? Segretario e
    Consiglieri vedono il Bilancio in lettura? *Proposta: entrambi
    registrano; CD in lettura.*
12. **Organo di controllo e raccolte fondi**: nel 2027 c'è un Organo di
    controllo (relazione da allegare)? Le raccolte pubbliche di fondi (art.
    22.4) si rimandano finché non capita il caso? *Proposta: relazione
    facoltativa; raccolte fondi rimandate (bastano i tag).*
13. **Istituzionale/commerciale**: la struttura a due sezioni si fa subito
    (per il 2027 la sezione B dice «Nessuna attività diversa o
    commerciale»)? *Proposta: sì, subito.*

**Tempi**
14. **Quando si approva il preventivo 2027** (data dell'Assemblea, forse la
    stessa che adotta lo statuto)? Se cade prima dell'azzeramento, il
    preventivo si scrive sul server in mezzo ai dati di prova: lo teniamo
    fuori dall'azzeramento, o lo si reinserisce dopo? *Proposta: escluso
    dall'azzeramento, con un passo apposta nella procedura.*

Materiale utile, se c'è: il preventivo 2026 o 2027 della B.A.S. (PDF o
esportazione di `gestionale-acv`) per la forma a cui i soci sono abituati;
`contabilita/preventivo.php` e `consuntivo.php`; indicazioni del
commercialista sul formato.

## 7. Prossimi passi

1. Risposte di Paolo → aggiornamento di questa analisi (§8 Decisioni).
2. Tavole sulla tela (proposta): **BL-1** elenco per anno; **BL-2** scheda
   del preventivo con le righe; **BL-3** rendiconto in corso con confronto
   e scostamenti; **BL-4** proposta del CD e messa a disposizione;
   **BL-5** approvazione dell'Assemblea (e chiusura dell'anno); **BL-6**
   PDF del rendiconto; **BL-7** telefono (consultazione).
3. Codice, tre giri di revisione, scheda `bilancio.md` riscritta, MANUALE,
   `prima-nota.md` (chiusura legata al rendiconto), `scadenzario.md`,
   `permessi.md`, lista di collaudo `docs/test/collaudo-O-rendiconto.md`.
