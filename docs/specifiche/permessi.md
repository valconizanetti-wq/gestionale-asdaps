# Sistema permessi (a sezione intera)

**Stato:** in sviluppo (schema consegnato)
**Introdotto in:** v0.16.0 (2026-09-22)
**File coinvolti:**
- Migration: `2026_09_22_000080` (create gl_sezioni), `2026_09_22_000081` (create gl_ruoli_sezioni)
- Model nuovo: `Sezione.php`
- Model aggiornato: `Ruolo.php` (relazione `sezioni()`)
- Seeder: `SezioneSeeder.php`

## 1. Logica

Colma il gap più importante segnalato in `MANUALE-CORE.md` §6/§8:
`gl_ruoli` era solo un'anagrafica di etichette collegata a
`gl_users.ruolo` tramite FK, senza alcun meccanismo che stabilisse
**cosa** un ruolo potesse effettivamente vedere o fare nell'applicazione
— il core non aveva alcun modo di limitare l'accesso alle sue sezioni.

Granularità scelta esplicitamente: **a sezione intera**, non per
singola funzione dentro ciascuna sezione (es. non si distingue
"visualizza" da "modifica" all'interno di una stessa sezione, salvo
l'unica eccezione della sola lettura per il Revisore, vedi §4).
Adeguata alle dimensioni della compagnia e ai 4 ruoli già seedati nel
core (`presidente`, `segretario`, `tesoriere`, `revisore`).

## 2. Struttura dati

- **`gl_sezioni`**: `codice` (unique), `label`, `ordine`. 20 righe
  seedate, una per ciascun modulo applicativo mappato durante l'intera
  revisione della base dati di questa sessione: `associazione`, `soci`, `domande`,
  `rinnovi_quota`, `prima_nota`, `bilancio`, `progetti`,
  `governance_cd`, `assemblee`, `verbali`, `volontari`, `tesseramento`,
  `compliance_rasd`, `comunicazioni`, `archivio`, `gdpr`, `scadenzario`,
  `audit_log`, `impostazioni`, `erogazioni_collaboratori`. La gestione
  Utenti/Ruoli (`gl_users`, `gl_ruoli`, `gl_ruoli_sezioni`) non ha una
  sezione propria: ricade in `impostazioni`.
- **`gl_ruoli_sezioni`**: pivot `ruolo_codice` (FK → `gl_ruoli.codice`)
  ↔ `sezione_id` (FK → `gl_sezioni.id`), con `sola_lettura` (booleano,
  default `false`) come unico attributo aggiuntivo. Vincolo unique
  sulla coppia.

## 3. Comportamento / flusso

Non ancora integrato in nessun controller (fase UI non scritta). Il
flusso previsto: ad ogni richiesta verso una sezione dell'app, il
controller verificherà `$sezione->accessibileDaRuolo($user->ruolo)`
(metodo d'istanza, non statico) prima di consentire l'accesso; per le sezioni con `sola_lettura = true`
per il ruolo corrente, la UI dovrà nascondere/disabilitare le azioni di
modifica.

## 4. Implementazione tecnica

- **FK su stringa (`ruolo_codice` → `gl_ruoli.codice`)** invece che su
  `gl_ruoli.id`: scelta per coerenza con la convenzione già esistente
  nel core, dove `gl_users.ruolo` referenzia allo stesso modo
  `gl_ruoli.codice` (non l'id numerico).
- **`Ruolo::sezioni()` / `Sezione::ruoli()`**: relazioni `belongsToMany`
  con parametri espliciti di parent/related key (`codice`/`id`),
  necessari perché Eloquent assume di default che la chiave del pivot
  corrisponda alla chiave primaria (`id`), non a un'altra colonna unica
  come `codice`.
- **`sola_lettura` come singolo flag booleano sul pivot**, non come
  sistema di permessi per-azione: è l'unica eccezione alla granularità
  "a sezione intera" concordata, usata per il Revisore (sezioni
  contabili e Audit Log) e per il Tesoriere (Volontari) — non un primo
  passo verso una granularità più fine, che resta esplicitamente fuori
  scope.
- **Helper sull'utente** (aggiunti in `User.php`, v0.18.0): 
  `puoAccedere($sezione)`, `puoModificare($sezione)`,
  `soloLettura($sezione)`, tutti basati su `permessiSezioni()`, che
  carica la mappa codice sezione → `sola_lettura` del ruolo con **una
  sola query per richiesta** e la memorizza nell'istanza. Sono il punto
  d'ingresso per viste, componenti e controller (es.
  `auth()->user()->puoModificare('soci')`), perché la sidebar interroga
  20 sezioni a ogni pagina e `Sezione::accessibileDaRuolo()` farebbe
  una query per ciascuna. `accessibileDaRuolo()` resta disponibile per
  i casi in cui si parte da una `Sezione` già caricata.
- **Omonimia colonna/relazione `ruolo` in `User.php`**: `$user->ruolo`
  restituisce sempre il codice (stringa), mai il model `Ruolo`, perché
  Eloquent dà precedenza alla colonna. Gli helper sfruttano proprio
  questo; per l'etichetta del ruolo serve `$user->ruolo()->first()`.
  Non rinominata per non rompere eventuali usi nel core; annotata nel
  model.
- **Matrice permessi seedata direttamente**, non lasciata come
  configurazione da fare a mano in UI: la matrice concordata con Paolo
  è già applicata come dati iniziali, modificabile in futuro tramite
  un'eventuale interfaccia di amministrazione (non ancora prevista).

**Matrice permessi applicata**:

| Sezione | Presidente | Segretario | Tesoriere | Revisore |
|---|---|---|---|---|
| Associazione, Soci, Domande, Governance CD, Assemblee, Verbali, Comunicazioni, Archivio documenti, GDPR, Compliance RASD | ✓ | ✓ | | |
| Scadenzario (dalla v0.38.0 anche il Tesoriere) | ✓ | ✓ | ✓ | ✓ (sola lettura) |
| Tesseramento (tessere CSEN, dalla v0.37.0) | ✓ | ✓ | ✓ | ✓ (sola lettura) |
| Volontari | ✓ | ✓ | ✓ (sola lettura) | |
| Prima Nota, Rinnovi quota, Bilancio, Progetti | ✓ | | ✓ | ✓ (sola lettura) |
| Audit Log | ✓ | | | ✓ (sola lettura) |
| Impostazioni (incl. Utenti/Ruoli; profilo dell'ente in lettura dalla v0.38.0) | ✓ | ✓ (sola lettura) | ✓ (sola lettura) | |
| Erogazioni/Collaboratori | | | | |

## 5. Conformità e sicurezza

- Introduce un controllo di accesso strutturato dove prima non esisteva
  — rilevante anche ai fini GDPR (principio di minimizzazione
  dell'accesso: ogni ruolo vede solo ciò che gli serve).
- La sezione Impostazioni riservata al solo Presidente limita
  l'accesso a configurazioni sensibili (es. soglie 398/91, split
  scontrini) a chi ha la responsabilità legale dell'associazione. Dalla
  v0.38.0 Tesoriere e Segretario la consultano (profilo dell'ente,
  affiliazione e polizza); ogni modifica resta al Presidente, e le
  configurazioni sensibili che verranno vanno protette allo stesso modo
  (`puoModificare` più il ruolo, come in `ImpostazioniEnteController`).

## 6. Interazioni con altri moduli

- **Ruoli/Utenti** (`gl_ruoli`, `gl_users`): dipendenza diretta,
  riusa la stessa convenzione di riferimento tramite `codice`.
- **Tutti i moduli applicativi**: ogni sezione seedata corrisponde a un
  modulo già rivisto in questa sessione (Tesseramento, Volontari,
  Scontrini/Prima Nota, Bilancio, Governance CD, GDPR, Comunicazioni,
  Audit Log, Scadenzario, Rinnovi quota, Progetti, Domande, Compliance
  RASD) — questo modulo è il primo a referenziarli tutti insieme in un
  unico schema.

## 7. Storia ed evoluzione

- Ultimo passo del piano concordato per la fase di "sola base dati",
  prima di passare a controller e viste (UI) — deciso esplicitamente
  di completare prima l'intera revisione dati (inclusa la verifica di
  completezza incrociata) e poi affrontare i permessi, per non doverli
  ridisegnare mano a mano che emergevano nuovi moduli.
- Granularità "a sezione intera" scelta esplicitamente da Paolo,
  scartando l'alternativa più fine (per singola funzione dentro ogni
  sezione) proposta come opzione.
- La matrice presidente/segretario/tesoriere/revisore proposta come
  default ragionevole è stata accettata senza modifiche.
- **Revisione della matrice** (stessa giornata, dopo la verifica di
  coerenza della documentazione): tre lacune individuate e chiuse su
  proposta accettata da Paolo. (1) Mancavano sezioni per l'anagrafica
  dell'ente (`gl_associazione`) e per l'Archivio documenti
  (`gl_archivio_documenti`): aggiunte `associazione` e `archivio`
  (Presidente + Segretario), sezioni da 18 a 20; la gestione
  Utenti/Ruoli resta dentro Impostazioni. (2) Il Tesoriere non vedeva i
  rimborsi volontari che deve pagare: aggiunto in sola lettura su
  Volontari. (3) Il Revisore aveva accesso pieno all'Audit Log: ridotto
  a sola lettura, l'archiviazione resta al Presidente. Nessuna
  migration: solo `SezioneSeeder.php` aggiornato (idempotente,
  `firstOrCreate`/`updateOrInsert`).
- Corretto anche il riferimento a `Sezione::accessibileDaRuolo()` in
  §3: nel model è un metodo d'istanza, non statico.
- **Helper per la sola lettura** (v0.18.0, all'avvio della fase viste):
  collocati su `User` invece che su `Sezione`, per poter servire la
  sidebar e i componenti con una sola query per richiesta.
- **Tessere CSEN** (v0.37.0, 07/10/2026, `tessere.md`): su Tesseramento
  aggiunti il Tesoriere (compra i pacchetti e li registra in Prima Nota) e
  il Revisore in sola lettura (`analisi-tessere-scadenze.md` §2.8). Solo
  `SezioneSeeder.php`, da rilanciare.
- **Scadenzario e profilo dell'ente** (v0.38.0, 11/10/2026,
  `scadenzario.md`): su Scadenzario aggiunti il Tesoriere (paga
  affiliazione e polizza) e il Revisore in sola lettura
  (`analisi-tessere-scadenze.md` §2.8); Impostazioni in sola lettura per
  Tesoriere e Segretario (tavola SC-2: vedono affiliazione e polizza; le
  modifiche le fa il Presidente, controllo nel controller). Dentro lo
  Scadenzario l'uscita in Prima Nota la registra solo chi modifica anche la
  Prima Nota. Solo `SezioneSeeder.php`, da rilanciare.
