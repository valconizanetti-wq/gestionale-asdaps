# Bilancio (rendiconto ASD — collegamento al verbale di approvazione)

**Stato:** in sviluppo (schema consegnato)
**Introdotto in:** v0.5.0 (2026-09-22)
**File coinvolti:**
- Migration: `2026_09_22_000075` (alter gl_bilancio)
- Model aggiornato: `Bilancio.php`

> Scheda ricostruita a posteriori da `MANUALE-ASD.md` §2.4 e §6.2 (la
> scheda originale non era stata redatta al momento della consegna) e
> verificata sul codice: migration del core `000034`/`000035`, migration
> alter `000075`, model `Bilancio.php`/`BilancioVoce.php`. Il numero di
> versione (v0.5.0) è dedotto dalla sequenza delle altre schede ed è
> coerente con la numerazione delle migration.

## 1. Logica

Il **Bilancio DM39** (schema RUNTS) **non si applica** a un'ASD non
iscritta al RUNTS. Resta però obbligatorio un **rendiconto
economico-finanziario annuale**, redatto dal Consiglio Direttivo e
approvato dall'Assemblea.

L'analisi ha mostrato che `gl_bilancio`/`gl_bilancio_voci` era già
adatto a questo scopo senza modifiche sostanziali. Le voci sono libere,
coerentemente con l'assenza di un modello ministeriale obbligatorio. Il
`tipo` preventivo/consuntivo è già presente. Il collegamento a
`gl_prima_nota_categorie` porta con sé la distinzione
istituzionale/commerciale (`flag_non_istituzionale`) richiesta dal
regime 398/91.

L'unico adattamento riguarda l'**iter deliberativo specifico** di
approvazione, richiesto dall'art. 90 c.18 lett. d) L. 289/2002 (richiamato
dall'art. 7 D.Lgs. 36/2021). `gl_bilancio` lo tracciava solo come testo
libero, senza un collegamento reale al verbale.

Casi esclusi: il modulo non genera automaticamente la scadenza di
approvazione (vedi §3) e non calcola il raccordo cassa/banca
di inizio/fine esercizio come dato salvato (vedi §4).

## 2. Struttura dati

**Modifica a `gl_bilancio` (esistente, alter):**
- **Aggiunta** `verbale_approvazione_id` (FK nullable → `gl_verbali`,
  posizionata dopo `data_assemblea`, `nullOnDelete`): collegamento al
  verbale dell'assemblea che approva il rendiconto. Se il verbale viene
  eliminato, il bilancio resta e perde solo il collegamento. `down()`
  usa `dropConstrainedForeignId`.

**Invariati:**
- `data_assemblea`/`numero_verbale` (testo libero): **mantenuti** come
  campi di comodo/fallback accanto alla nuova FK, che diventa la fonte
  di verità per i collegamenti reali.
- `tipo` (string, `preventivo`/`consuntivo`, non enum), `stato`
  (`bozza`/`approvato`), `approvato_il`, vincolo unique `(anno, tipo)`:
  un solo preventivo e un solo consuntivo per anno.
- `pdf_approvato_path`: già adatto a conservare la copia del rendiconto
  approvato da allegare al verbale (vedi §5).
- `codice_documento` (su `gl_bilancio`): residuo RUNTS non applicabile,
  lasciato non popolato — stesso campo e stesso trattamento già visti su
  `gl_progetti` (`progetti.md` §2).
- `gl_bilancio_voci.codice` (commento `// codice DM 39/2020` nel core):
  resta come campo generico non tipizzato, semplicemente non popolato
  per questa ASD — stesso trattamento di `codice_dm39` su
  `gl_prima_nota_categorie`.
- Tutto il resto di `gl_bilancio`/`gl_bilancio_voci`.

## 3. Comportamento / flusso

1. Il CD redige il rendiconto consuntivo (criterio di cassa, prospetto
   entrate/uscite, distinzione istituzionale/commerciale ereditata dalle
   categorie di Prima Nota).
2. L'Assemblea lo approva entro **4 mesi** dalla chiusura
   dell'esercizio, estensibili a **6** in presenza di motivate esigenze.
3. All'approvazione `stato` passa ad `approvato` con `approvato_il`, si
   collega il verbale reale tramite `verbale_approvazione_id` e si
   archivia il PDF approvato in `pdf_approvato_path` (copia da allegare
   al verbale).
4. **Termine di approvazione**: nessuna nuova tabella. La relativa riga
   andrà generata lato applicazione in `gl_scadenzario` (categoria
   `contabile`, già prevista nell'enum) all'apertura di un nuovo
   bilancio consuntivo. Non ancora implementato (fase applicativa).

## 4. Implementazione tecnica

- **FK nullable**, stessa convenzione di
  `gl_volontari.verbale_autorizzazione_id` e (successivamente)
  `gl_governance_cd.verbale_nomina_id`.
- **Campi testuali legacy non rimossi**: decisione esplicita di
  prudenza e minima invasività su campi già esistenti. È la scelta
  opposta a quella fatta per Governance CD, dove la ridondanza
  anagrafica è stata eliminata (`governance-cd.md` §7).
- **Raccordo cassa/banca di inizio/fine esercizio**: verificato che
  **non richiede** modifiche a `gl_cassa_chiusure`, che ha uno scopo
  diverso (verifica mensile cassa fisica vs Prima Nota, di tipo
  antifrode). Il raccordo annuale è un calcolo al volo su
  `gl_prima_nota` (entrate meno uscite per `conto`, fino a una data),
  non salvato — stesso principio già applicato a plafond 398/91 e
  quadratura scontrini.
- **Importi del consuntivo**: `gl_bilancio_voci` ha un solo campo
  importo, `importo_preventivo`, anche nelle righe di un bilancio di
  tipo `consuntivo`. Gli importi effettivi non sono salvati: si ricavano
  al volo da `gl_prima_nota` tramite `categoria_id`, coerentemente con
  il criterio di cassa. Due note per la fase UI: (a) una voce di
  consuntivo senza `categoria_id` (campo nullable) non ha una fonte per
  l'importo effettivo, quindi l'applicazione dovrà richiederla o
  segnalarla; (b) nel contesto consuntivo il nome `importo_preventivo`
  è fuorviante e l'interfaccia dovrà etichettarlo in modo appropriato.
  Nessuna modifica di schema decisa.
- **Nessun riferimento diretto a `gl_scadenzario`**: la generazione del
  termine di approvazione resta responsabilità applicativa, come per
  Tesseramento e Compliance RASD.

## 5. Conformità e sicurezza

- **Art. 20 DPR 600/1973**: obbligo di tenuta contabile per gli enti
  non commerciali, richiamato per chi adotta la L. 398/1991.
- **Art. 148 TUIR**: la mancata approvazione assembleare del rendiconto
  può far perdere le agevolazioni fiscali (requisito sostanziale).
- **Art. 90 c.18 lett. d) L. 289/2002, richiamato dall'art. 7 D.Lgs.
  36/2021**: modalità di approvazione predeterminate nello statuto,
  iter deliberativo specifico — motivo diretto della FK
  `verbale_approvazione_id`.
- **Nessun modello ministeriale obbligatorio** e nessun deposito
  pubblico (a differenza del DM39 RUNTS).
- **Regime 398/91**: distinzione istituzionale/commerciale richiesta,
  coperta da `flag_non_istituzionale` (vedi `scontrini.md` §6.1 e
  `MANUALE-ASD.md` §6.1).
- **Conservazione rendiconto: 5 anni**. Da non confondere con la
  soglia di conservazione dell'audit log (10 anni, art. 2220 c.c., vedi
  `audit-log.md` §5): obblighi diversi, con basi normative diverse.

## 6. Interazioni con altri moduli

- **Verbali** (`gl_verbali`): nuova dipendenza tramite
  `verbale_approvazione_id`.
- **Prima Nota / Categorie** (`gl_prima_nota`,
  `gl_prima_nota_categorie`): dipendenza esistente e invariata; fonte
  della distinzione istituzionale/commerciale e del raccordo
  cassa/banca calcolato.
- **Cassa chiusure** (`gl_cassa_chiusure`): verificata, nessuna
  sovrapposizione né modifica.
- **Scadenzario** (`gl_scadenzario`, categoria `contabile`):
  destinazione futura del termine di approvazione, generato lato
  applicazione.
- **Audit Log** (`gl_audit_log`): le modifiche a movimenti/voci contabili
  restano soggette alla soglia di conservazione di 10 anni, più lunga di
  quella del rendiconto.
- **Permessi** (`gl_sezioni`, sezione `bilancio`): Presidente e
  Tesoriere accesso pieno, Revisore in sola lettura (`permessi.md` §4).

## 7. Storia ed evoluzione

- Analisi partita dall'esclusione del Bilancio DM39 (non applicabile a
  un'ASD non iscritta al RUNTS) e dalla verifica dei riferimenti
  normativi del rendiconto ASD, fatta in una sessione successiva al
  primo inquadramento (§2.4 del manuale).
- Esito strutturale migliore del previsto: schema già adatto, un solo
  adattamento (la FK al verbale).
- Decisione esplicita di Paolo di mantenere `data_assemblea`/
  `numero_verbale` come fallback testuale; la stessa scelta è stata poi
  replicata per `delibera_assemblea`/`delibera_data` in Governance CD.
- Scheda redatta a posteriori, durante la revisione di coerenza della
  documentazione successiva a v0.16.0, perché mancante tra le schede
  consegnate pur essendo già citata da `audit-log.md` e
  `scadenzario.md`.
