# Volontari (adattamento ASD)

**Stato:** in sviluppo (schema consegnato)
**Introdotto in:** v0.3.0 (2026-09-22)
**File coinvolti:**
- Migration: `2026_09_22_000068` → `2026_09_22_000071`
  (gl_rimborsi_rasd_invii, gl_volontari_autodichiarazioni, alter
  gl_volontari, alter gl_rimborsi_volontari)
- Model nuovi: `RimborsoRasdInvio.php`, `VolontarioAutodichiarazione.php`
- Model aggiornati: `Volontario.php`, `RimborsoVolontario.php`

## 1. Logica

Il modulo Volontari del core (pensato per un'APS generica) viene
adattato alle regole specifiche del volontariato sportivo, che qui è
**l'unica forza lavoro** dell'associazione (nessun dipendente/
collaboratore previsto).

Normativa di riferimento: D.Lgs. 36/2021 art. 29, corretto da D.Lgs.
163/2022 e D.Lgs. 120/2023, riscritto da D.L. 71/2024 (L. 106/2024),
chiarito dalla Circolare Agenzia Entrate 7/E del 7 agosto 2026.

Regole di business principali:
- **Rimborsi ordinari** (spese documentate/autocertificate: vitto,
  alloggio, viaggio, trasporto): tetto **150 €/mese**, su delibera
  dell'organo sociale che ne definisce tipologie e attività ammesse.
- **Rimborsi forfettari**: tetto **400 €/mese**, ammessi solo per
  attività svolte durante manifestazioni/eventi sportivi riconosciuti
  (non per l'attività ordinaria), da **comunicare al RASD** entro il
  mese successivo al trimestre di svolgimento.
- **Soglia di non imponibilità cumulativa**: il rimborso forfettario
  resta non tassabile solo se il volontario, sommando eventuali
  compensi da *lavoro* sportivo percepiti altrove, non supera **15.000
  €/anno**. Oltre soglia, il rimborso forfettario diventa reddito
  imponibile.
- **Assicurazione RCT obbligatoria** per i volontari (art. 29 c.4): già
  coperta dallo schema esistente, nessuna modifica necessaria.

Casi esclusi: il modulo non calcola automaticamente il superamento della
soglia dei 15.000 € sommando dati di altri enti (informazione che
l'associazione non può avere se non dichiarata dal volontario stesso) —
resta un'autodichiarazione periodica, non un controllo incrociato.

## 2. Struttura dati

**Tabelle esistenti nel core, invariate:**
- `gl_volontari`: già include `assicurazione_compagnia`,
  `assicurazione_numero`, `assicurazione_scadenza` (copertura RCT).
- `gl_rimborsi_volontari_voci`: già descrive le singole spese
  (km/viaggio/vitto) — corrisponde al rimborso ordinario, nessuna
  modifica.
- `gl_volontari_sessioni`: sistema di vidimazione PEC a "sessioni" con
  hash chain, riusato come pattern (non modificato) per il nuovo
  `gl_rimborsi_rasd_invii`.

**Modifiche a tabelle esistenti:**
- `gl_volontari` + `verbale_autorizzazione_id` (FK nullable →
  `gl_verbali`): collega il volontario al verbale del CD che ne
  autorizza tipologie di spesa/attività.
- `gl_rimborsi_volontari` + `tipo_rimborso` (enum
  `ordinario`/`forfettario`, default `ordinario`), `evento_nome`/
  `evento_data` (placeholder testuale, vedi §4), `rasd_invio_id` (FK
  nullable → `gl_rimborsi_rasd_invii`), `data_annullamento`/
  `motivo_annullamento`. Enum `stato` esteso con il valore `annullato`
  (`bozza`/`in_attesa`/`pagato`/`annullato`).

**Nuove tabelle:**
- `gl_rimborsi_rasd_invii`: `anno`, `trimestre` (1-4), `data_invio`,
  `stato` (bozza/inviata), `ricevuta_path`. Unique `(anno, trimestre)`.
- `gl_volontari_autodichiarazioni`: `volontario_id` (FK →
  `gl_volontari`), `anno`, `importo_dichiarato`, `data_dichiarazione`,
  `sopra_soglia` (booleano, calcolato in applicazione). Unique
  `(volontario_id, anno)`.

## 3. Comportamento / flusso

1. Alla registrazione di un rimborso, si sceglie `tipo_rimborso`:
   ordinario (si compilano le voci in `gl_rimborsi_volontari_voci` come
   già previsto) o forfettario (si indica manualmente l'evento di
   riferimento).
2. I rimborsi forfettari di un trimestre vengono aggregati in un
   `gl_rimborsi_rasd_invii` (creato in stato "bozza", poi passato a
   "inviata" con data e ricevuta), sullo stesso flusso già collaudato
   per le sessioni di vidimazione PEC dei volontari.
3. Annualmente (o quando richiesto), ogni volontario fornisce
   un'autodichiarazione dei compensi da lavoro sportivo percepiti presso
   altri enti, registrata in `gl_volontari_autodichiarazioni`; se
   l'importo supera 15.000 €, il flag `sopra_soglia` segnala che i
   rimborsi forfettari di quell'anno per quel volontario vanno trattati
   come reddito imponibile (verifica/azione manuale, il modulo non
   applica automaticamente conseguenze fiscali).
4. Un rimborso può essere annullato impostando `stato = annullato` con
   motivo e data, senza eliminare il record (nessun soft delete su
   questa tabella: lo storico resta sempre consultabile).

## 4. Implementazione tecnica

- **Collegamento all'evento — nota aggiornata**: `evento_nome`/
  `evento_data` sono campi testuali. Al momento della progettazione
  iniziale di questo modulo, erano pensati come placeholder temporaneo
  in attesa di una FK `evento_id → gl_gare` una volta costruito un
  modulo Gestione gare interno. Decisione successiva (vedi
  `MANUALE-ASD.md` §4.3): Gestione gare sarà un'app Laravel separata
  ("ArcoGare", multi-tenant, propria anagrafica atleti anche esterni
  alla compagnia) — quindi **nessuna FK diretta sarà mai possibile**
  (database separati). Questi due campi restano perciò il collegamento
  **definitivo**, non più un placeholder in attesa; un eventuale
  `evento_id_esterno` (riferimento debole, senza vincolo FK, all'ID
  dell'evento in ArcoGare) potrà essere aggiunto in futuro solo se si
  costruirà un'integrazione via API tra le due app.
- **Estensione ENUM via `DB::statement`**: MySQL non permette di
  aggiungere un valore a un ENUM tramite `Blueprint` — la migration usa
  `ALTER TABLE ... MODIFY stato ENUM(...)` diretto, con `down()`
  speculare che rimuove il valore.
- **Nessuna tabella di log per gli annullamenti** (a differenza del gap
  `gs_rimborsi_annullati` segnalato nel core come tabella dedicata):
  scelta di aggiungere stato+motivo sul record principale, più snella,
  possibile perché `gl_rimborsi_volontari` non ha soft delete e il
  record resta comunque nello storico.
- Riuso esplicito del pattern "sessione con stato bozza→finalizzata" già
  presente in `gl_volontari_sessioni`, per coerenza di stile col resto
  del modulo.

## 5. Conformità e sicurezza

- Riferimenti normativi: D.Lgs. 36/2021 art. 29; correttivi D.Lgs.
  163/2022, D.Lgs. 120/2023; D.L. 71/2024 (L. 106/2024); Circolare
  Agenzia Entrate 7/E del 7 agosto 2026.
- Soglie tracciate nello schema: 150 €/mese (ordinario, verificabile
  sommando `gl_rimborsi_volontari_voci` per volontario/mese — controllo
  da implementare lato applicazione, non ancora un vincolo DB), 400
  €/mese (forfettario), 15.000 €/anno (soglia cumulativa lavoro
  sportivo, via autodichiarazione).
- Comunicazione trimestrale al RASD tracciata con stato esplicito
  (bozza/inviata) e ricevuta allegata, per dimostrabilità dell'adempimento.
- Collegamento alla delibera del CD (`verbale_autorizzazione_id`) per
  tracciabilità dell'autorizzazione richiesta dalla norma — **limite
  noto**: collega al verbale nel suo complesso, non alla singola
  delibera interna, poiché il core non ha ancora una tabella
  `gl_delibere_cd` granulare (gap segnalato in `MANUALE-CORE.md` §6).

## 6. Interazioni con altri moduli

- **Soci** (`gl_soci`): invariato, `gl_volontari.socio_id` già esistente.
- **Governance CD / Verbali** (`gl_verbali`): nuova dipendenza tramite
  `verbale_autorizzazione_id`.
- **Prima Nota** (`gl_prima_nota`): dipendenza già esistente
  (`prima_nota_id` su `gl_rimborsi_volontari`), invariata.
- **ArcoGare** (app esterna, non ancora progettata): nessuna dipendenza
  diretta possibile (database separati) — collegamento solo tramite i
  campi testuali `evento_nome`/`evento_data`, eventualmente affiancati
  in futuro da un `evento_id_esterno` debole se si costruirà
  un'integrazione via API.
- **Compliance RASD** (§4.2 di `MANUALE-ASD.md`, scheda
  `compliance-rasd.md`, v0.15.0): la comunicazione trimestrale qui
  tracciata è concettualmente parte della compliance verso il RASD, ma
  vive come tabella propria del modulo Volontari, non dentro il modulo
  Compliance RASD (che copre solo le verifiche Sport e Salute,
  `gl_rasd_verifiche`).

## 7. Storia ed evoluzione

- Analisi partita dalle norme generali (§2.2 di `MANUALE-ASD.md`), poi
  verificata contro lo schema reale del modulo Volontari del core
  (migration + model), più sofisticato del previsto (sistema di
  vidimazione PEC con hash chain già esistente per altri scopi).
- Il collegamento alla delibera del CD è stato riconosciuto come
  limitato dalla granularità attuale di `gl_verbali` solo dopo aver
  esaminato quella migration: non esiste ancora una tabella delibere
  interne al verbale, gap già noto lato core.
- Il recupero del gap `gs_rimborsi_annullati` (segnalato in
  `MANUALE-CORE.md` §6) è stato risolto qui con un approccio più
  leggero (stato sul record) rispetto a una tabella di log dedicata,
  dato il contesto di questo derivato.
- **Correzione post-consegna** (stessa giornata, sessione successiva):
  al momento della progettazione di questo modulo, Gestione gare era
  ancora ipotizzato come modulo interno a `gestionale-asd`, da cui il
  placeholder `evento_nome`/`evento_data` in attesa di una FK. La
  decisione successiva di spostare Gestione gare in un'app esterna
  ("ArcoGare") ha reso quei due campi il collegamento definitivo, non
  più temporaneo — vedi §4 e §6 aggiornati.
