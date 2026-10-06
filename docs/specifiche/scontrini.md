# Scontrini (Prima Nota — registrazione da foto con OCR)

**Stato:** in sviluppo (schema consegnato)
**Introdotto in:** v0.4.0 (2026-09-22)
**File coinvolti:**
- Migration: `2026_09_22_000072` → `2026_09_22_000074`
  (gl_impostazioni, gl_prima_nota_scontrini, alter gl_prima_nota)
- Model nuovi: `Impostazione.php`, `PrimaNotaScontrino.php`
- Model aggiornato: `PrimaNota.php` (relazione `scontrino()`, campo
  `scontrino_id` in fillable)
- Seeder: `ImpostazioneSeeder.php`

## 1. Logica

Il modulo permette di registrare un movimento di Prima Nota a partire
dalla foto di uno scontrino/scontrino fiscale, con riconoscimento
automatico best-effort (OCR) di totale, data e ragione sociale
dell'esercizio — pensato per l'uso mobile sul momento (es. subito dopo
un acquisto), rimandando la classificazione contabile precisa a un
momento successivo da desktop.

Origine della funzionalità: individuata analizzando **`gestionale-acv`**,
il plugin WordPress con cui la Compagnia (B.A.S.) tiene oggi la contabilità
(tabelle `conta_*`/`doc_archivio`, non `gl_*`; scansione con OCR.space,
stessa chiave). Correzione del 06/10/2026: prima era attribuito per errore
all'«Area Riservata» del Dante. Non è stato portato codice: la logica di
business è stata analizzata e ridisegnata da zero per lo schema Laravel
del derivato ASD. Si appoggia alla Prima Nota della v0.35.0
(`prima-nota.md`); la realizzazione segue Rinnovi quota (v0.36.0), versione da definire.

Regole di business:
- Un movimento nasce sempre con una categoria segnaposto ("da
  classificare"), raffinabile in un secondo momento.
- Un giustificativo (scontrino) può avere **uno o più movimenti
  collegati** (split su categorie diverse), a seconda di
  un'impostazione configurabile (`gl_impostazioni`, chiave
  `scontrini_split_abilitato`).
- Se lo split è abilitato: la somma dei movimenti collegati deve
  coincidere con il totale dello scontrino (quadratura) prima che siano
  considerati confermati.
- Se lo split è disabilitato: si crea sempre un solo movimento pari al
  totale, quadrato per costruzione.
- Non si può scollegare l'ultimo movimento rimasto collegato a uno
  scontrino (si elimina l'intero scontrino).
- Su un anno contabile chiuso, qualunque modifica o scollegamento di un
  movimento collegato a uno scontrino richiede una motivazione
  obbligatoria e genera una riga di audit log.

## 2. Struttura dati

- **`gl_impostazioni`** (`chiave` unique, `valore`, `tipo`,
  `descrizione`): tabella chiave/valore generica per impostazioni
  applicative. Seed iniziale: `scontrini_split_abilitato` (boolean,
  `true`), `regime_398_plafond_soglia` (integer, `400000`),
  `regime_398_tracciabilita_soglia` (integer, `1000`) — queste ultime
  due per il monitoraggio L. 398/1991, vedi §6.
- **`gl_prima_nota_scontrini`**: `data_scontrino`, `importo_totale`,
  `anno`, `soggetto_id` (FK → `gl_soggetti`, riuso diretto della tabella
  esistente per il fornitore "trova o crea"), `ragione_sociale_raw`
  (testo grezzo OCR prima del collegamento a `soggetto_id`),
  `ocr_utilizzato`, `ocr_testo_grezzo`, `allegato_path`/
  `allegato_originale`, `quadrato` (booleano, ricalcolato
  dall'applicazione), `registrato_da` (FK → `gl_users`).
- **`gl_prima_nota`** (esistente, alter): nuova colonna `scontrino_id`
  (FK nullable → `gl_prima_nota_scontrini`). Più righe possono
  condividere lo stesso `scontrino_id`. `giustificativo_path`/
  `giustificativo_nome` (esistenti) restano invariati e continuano a
  coprire i movimenti inseriti a mano, senza passare dal flusso
  scontrino.

## 3. Comportamento / flusso

1. **Upload + riconoscimento**: l'utente carica una foto (o PDF) dello
   scontrino. Il sistema chiama il servizio OCR e propone totale, data e
   ragione sociale letti automaticamente, sempre editabili.
2. **Conferma**: l'utente verifica/corregge i dati proposti e conferma.
   Il fornitore viene collegato con logica "trova o crea" su
   `gl_soggetti` (per nome).
3. **Split (se abilitato)**: da Prima Nota, l'utente può aggiungere
   ulteriori movimenti collegati allo stesso scontrino, distribuendo
   l'importo su categorie diverse. Il flag `quadrato` su
   `gl_prima_nota_scontrini` viene ricalcolato ad ogni variazione.
4. **Anno chiuso**: se lo scontrino appartiene a un anno chiuso
   (`gl_anni_contabili`, stato `chiuso`), qualunque
   modifica o scollegamento richiede una motivazione, registrata in
   `gl_audit_log`.

## 4. Implementazione tecnica

- **OCR**: confermato **OCR.space** (già in uso e funzionante nel
  sistema originale). Da incapsulare in un service Laravel dedicato
  (es. `OcrScontrinoService`, con `Http::` facade invece di cURL
  diretto) — dettaglio implementativo rimandato alla fase di sviluppo
  dell'interfaccia, non ancora scritto.
- **Quadratura**: calcolata e salvata (non solo calcolata al volo) sul
  campo `quadrato` di `gl_prima_nota_scontrini`, tramite il metodo
  `PrimaNotaScontrino::ricalcolaQuadratura()` (confronto con
  `bccomp()` per evitare problemi di arrotondamento sui decimali), da
  invocare ad ogni aggiunta/modifica/rimozione di un movimento
  collegato.
- **Configurazione a runtime invece di costanti**: `scontrini_split_abilitato`
  e le due soglie del regime 398/91 vivono in `gl_impostazioni`
  (chiave/valore) invece che come costanti nel codice, così sono
  modificabili senza deploy — pensate per diventare, in futuro, domande
  del setup wizard del core (`MANUALE-CORE.md` §8).
- **`Impostazione::get()`/`set()`**: helper statici con cast automatico
  in base al campo `tipo` (string/boolean/integer/json), per evitare
  cast manuali sparsi nel codice applicativo.
- Nessuna struttura di log dedicata per la quadratura/split: il log
  delle modifiche su anno chiuso riusa `gl_audit_log` esistente, senza
  introdurre un meccanismo parallelo.

## 5. Conformità e sicurezza

- **Tracciabilità pagamenti** (L. 398/1991): il flusso non impone
  regole rigide a livello di schema, ma fornisce i dati (`conto`,
  `importo` su `gl_prima_nota`, soglia in `gl_impostazioni`) perché
  l'applicazione segnali un'anomalia quando un movimento ≥ soglia
  transita in cassa invece che in banca — vedi §6.1 di `MANUALE-ASD.md`.
- **Audit trail**: le modifiche su anno chiuso passano da
  `gl_audit_log` (hash chain esistente, nessuna modifica al suo
  schema), garantendo la stessa integrità già in uso per gli altri
  moduli del core.
- Nessun dato sensibile specifico introdotto da questo modulo (i dati
  OCR grezzi sono testo di scontrini commerciali, non categorie
  particolari GDPR).

## 6. Interazioni con altri moduli

- **Prima Nota** (`gl_prima_nota`): modulo ospite diretto, tramite la
  nuova FK `scontrino_id`.
- **Soggetti** (`gl_soggetti`): riuso diretto per l'anagrafica
  fornitori, nessuna modifica al suo schema (incluso il campo orfano
  `categoria_id`, lasciato invariato).
- **Audit Log** (`gl_audit_log`): riuso diretto per la tracciabilità
  delle modifiche su anno chiuso.
- **Anni contabili** (`gl_anni_contabili`, v0.35.0, al posto di
  `gl_prima_nota_anni_chiusi`): `AnniContabili::chiuso()` dice se un
  movimento appartiene a un anno chiuso; la correzione rifà la fotografia
  dei saldi (`prima-nota.md` §3). La categoria segnaposto è
  `DA_CLASSIFICARE` («Uscite da classificare», di sistema), che blocca la
  chiusura dell'anno finché resta usata.
- **Impostazioni** (`gl_impostazioni`): nuova tabella generica,
  introdotta da questo modulo ma pensata per essere riusata da
  qualunque altra funzionalità futura necessiti di configurazione a
  runtime.

## 7. Storia ed evoluzione

- Funzionalità richiesta esplicitamente da Paolo per mantenere una
  capacità già in uso in `gestionale-acv`, il plugin WordPress della
  Compagnia (scansione
  scontrino → movimento Prima Nota).
- Analisi del codice originale (WordPress, non Laravel) ha permesso di
  estrarre tutta la logica di business (flusso in due passaggi,
  quadratura, fornitore trova-o-crea, regola anno chiuso) senza portare
  codice non compatibile con lo stack Laravel/Eloquent.
- Decisione di rendere lo split configurabile (anziché fisso) presa su
  richiesta esplicita, con l'intuizione di riusare l'occasione per
  introdurre finalmente `gl_impostazioni`, colmando il gap `gs_config`
  segnalato ma mai risolto in `MANUALE-CORE.md` §6.
- Il monitoraggio del regime L. 398/1991 (plafond, tracciabilità) è
  stato aggiunto nella stessa sessione subito dopo, scoprendo che il
  campo `flag_non_istituzionale` già esistente su
  `gl_prima_nota_categorie` copriva già la distinzione necessaria — 
  nessuna nuova tabella richiesta, solo due voci di configurazione
  aggiuntive in `gl_impostazioni`.
- 06/10/2026 (v0.35.0): corretta la provenienza (`gestionale-acv` della
  Compagnia, non il Dante); `gl_prima_nota_anni_chiusi` sostituita da
  `gl_anni_contabili`; confermata la stessa chiave OCR.space di
  `gestionale-acv`. Realizzazione sopra la Prima Nota, dopo Rinnovi quota (decisione del 06/10/2026).
