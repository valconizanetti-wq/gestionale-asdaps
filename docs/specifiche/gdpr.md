# GDPR (analisi per il contesto ASD — nessuna modifica necessaria)

**Stato:** confermato riuso diretto (nessun cambiamento di schema)
**Introdotto in:** v0.7.0 (2026-09-22) — analisi, non modifica
**File coinvolti:** nessuno (schema esistente confermato adeguato):
`gl_gdpr_trattamenti`, `gl_gdpr_documenti`, `gl_gdpr_incaricati`,
`gl_gdpr_responsabili`, `gl_gdpr_responsabili_docs`, `gl_gdpr_richieste`,
`gl_gdpr_violazioni`, `gl_gdpr_log_accessi`, `gl_gdpr_registro_firmato`

## 1. Logica

In §2.6 di `MANUALE-ASD.md` era stato ipotizzato un probabile bisogno di
adattamento GDPR per due ragioni specifiche dell'ASD: trattamento di dati
di minori (arcieri minorenni) e dati sanitari (certificati medici del
futuro modulo Tesseramento). L'analisi diretta di tutte le 9 tabelle del
modulo GDPR del core ha escluso la necessità di modifiche: il modulo era
già progettato in modo sufficientemente generico da assorbire questi casi
senza cambiamenti strutturali.

## 2. Struttura dati

**Nessuna modifica.** Punti rilevanti dello schema esistente per questa
analisi:
- `gl_gdpr_trattamenti`: registro dei trattamenti ex art. 30 GDPR, con
  campi interamente testuali liberi — `categorie_speciali` (100 char),
  `categorie_interessati` (200 char), `categorie_dati` (text). Censire
  un nuovo trattamento (es. "Certificati medico-sportivi") significa
  inserire una riga, non modificare lo schema.
- `gl_soci` (modulo Soci, non GDPR in senso stretto ma rilevante qui):
  già dotato di campi tutore (`tutore_nome`, `tutore_relazione`,
  `tutore_telefono`, `tutore_email`) e consenso privacy strutturato
  (`privacy_accettata_il`, `privacy_ip`, `privacy_versione`,
  `privacy_metodo`, `privacy_doc_path`) — copre già il trattamento dei
  dati dei minori, verificato in sede di revisione dell'anagrafica soci
  (checklist moduli, riuso diretto confermato).
- `gl_gdpr_responsabili`: per soggetti esterni che trattano dati per
  conto dell'associazione (con DPA ex art. 28), non applicabile a CSEN
  (vedi §5).

## 3. Comportamento / flusso

Nessun cambiamento rispetto al comportamento già previsto dal core.
Azione futura (non ora): quando il modulo Tesseramento
sportivo/Certificati medici verrà effettivamente attivato (oggi non
attivo, nessun tesserato agonista né certificato raccolto — vedi scheda
`tesseramento-sportivo.md`), andrà inserita una riga in
`gl_gdpr_trattamenti` per il trattamento "Certificati medico-sportivi",
valorizzando `categorie_speciali` con "dati sanitari".

## 4. Implementazione tecnica

Nessuna. L'analisi ha confermato l'adeguatezza dello schema esistente
senza richiedere modifiche — incluso in questa scheda per tracciare che
l'analisi è stata fatta (evitando di doverla ripetere in una sessione
futura) e la motivazione della conclusione.

## 5. Conformità e sicurezza

- **Registro trattamenti (art. 30 GDPR)**: deve riflettere l'attività di
  trattamento reale, non quella potenziale — motivo per cui il
  trattamento "certificati medico-sportivi" non va censito ora, essendo
  il modulo Tesseramento non attivo.
- **CSEN come titolare autonomo**: l'invio dei dati dei tesserati a CSEN
  per l'affiliazione **non richiede** un accordo di responsabile del
  trattamento (`gl_gdpr_responsabili`) — CSEN riceve i dati come titolare
  autonomo per le proprie finalità federative (obbligo di
  legge/affiliazione), non come soggetto che tratta per conto
  dell'associazione.
- **ArcoGare (valutazione futura)**: se si costruirà un'integrazione via
  API tra `gestionale-asd` e ArcoGare (vedi `MANUALE-ASD.md` §4.3), andrà
  valutato in quel momento se ArcoGare agisce da titolare autonomo (come
  CSEN) o da responsabile del trattamento (necessitando quindi un
  accordo ex art. 28) — prematuro deciderlo ora, il progetto non è
  ancora disegnato.

## 6. Interazioni con altri moduli

- **Soci** (`gl_soci`): il trattamento dei dati dei minori è già coperto
  a livello di questo modulo, non del modulo GDPR.
- **Tesseramento sportivo / Certificati medici**: dipendenza futura —
  il censimento del trattamento "certificati medico-sportivi" è
  condizionato all'attivazione di quel modulo (vedi scheda
  `tesseramento-sportivo.md` §5, dove il promemoria è già annotato).
- **ArcoGare** (app esterna, non ancora progettata): possibile
  valutazione futura su titolarità del trattamento in caso di
  integrazione via API.

## 7. Storia ed evoluzione

- L'ipotesi iniziale (§2.6, prima sessione di analisi) presupponeva un
  adattamento necessario. L'analisi diretta delle tabelle, condotta in
  una sessione successiva insieme alla revisione di Governance CD, ha
  smentito questa ipotesi: il modulo era già sufficientemente generico.
- Risultato in linea con altri moduli confermati "riuso diretto" dopo
  verifica puntuale in questa sessione (Assemblee, Verbali,
  `gl_cassa_chiusure`) — un pattern ricorrente: non tutti i moduli
  segnalati come "da adattare" nella checklist iniziale richiedono
  effettivamente modifiche, alcuni erano già adeguati.
- Emerso durante l'analisi, non previsto inizialmente: il chiarimento
  sul ruolo di CSEN come titolare autonomo (non responsabile del
  trattamento), rilevante per evitare di richiedere inutilmente un DPA
  in fase di affiliazione.
