# Governance CD (eliminazione ridondanza anagrafica, incompatibilità art. 11)

**Stato:** in sviluppo (schema consegnato)
**Introdotto in:** v0.6.0 (2026-09-22)
**File coinvolti:**
- Migration: `2026_09_22_000076` (alter gl_governance_cd)
- Model aggiornato: `GovernanceCd.php`, `Socio.php` (relazione `caricheCd()`)

## 1. Logica

`gl_governance_cd` nel core teneva una propria anagrafica completa
(cognome, nome, data di nascita, luogo di nascita, codice fiscale,
email, telefono), indipendente da `gl_soci`. Per un'ASD dove i membri
del Consiglio Direttivo sono sempre anche soci, questo produceva dati
duplicati con rischio di disallineamento (es. un cambio di indirizzo
email aggiornato su `gl_soci` ma non su `gl_governance_cd`, o
viceversa).

In parallelo, è stata verificata la normativa sull'incompatibilità
degli amministratori (art. 11 D.Lgs. 36/2021), risultata più mutevole
del previsto: la versione originaria (2021) vietava a **tutti** gli
amministratori di ricoprire cariche in altre ASD/SSD affiliate alla
stessa Federazione/Disciplina associata/EPS; il **D.L. 96/2025** (conv.
L. 119/2025, in vigore dal 10 agosto 2025) ha ristretto il divieto al
**solo Presidente/legale rappresentante**. Non è, in ogni caso, una
clausola statutaria obbligatoria (art. 7 c.1 D.Lgs. 36/2021).

Casi esclusi: il modulo non applica automaticamente la regola in base a
`carica` (es. bloccando/richiedendo la dichiarazione solo per chi ha
`carica = 'Presidente'`) — è un controllo lasciato alla fase
applicativa, proprio perché la norma è già cambiata due volte in pochi
anni e un vincolo troppo rigido nello schema rischierebbe di essere
disallineato alla prossima modifica.

## 2. Struttura dati

**Modifiche a `gl_governance_cd` (esistente, alter):**
- **Aggiunta** `socio_id` (FK **obbligatoria** → `gl_soci`, non
  nullable) — collegamento diretto all'anagrafica socio, fonte di
  verità unica per i dati anagrafici.
- **Aggiunta** `verbale_nomina_id` (FK nullable → `gl_verbali`) —
  collegamento reale al verbale che ha deliberato la nomina, stessa
  convenzione già usata per `gl_bilancio.verbale_approvazione_id` e
  `gl_volontari.verbale_autorizzazione_id`.
- **Aggiunta** `dichiarazione_incompatibilita` (testo libero, nullable)
  e `dichiarazione_incompatibilita_data` (data, nullable) — per la
  dichiarazione ex art. 11, tenuta generica per ogni carica.
- **Rimossi** 7 campi anagrafici duplicati: `cognome`, `nome`,
  `data_nascita`, `luogo_nascita`, `codice_fiscale`, `email`,
  `telefono`.
- **Invariati**: `carica`, `data_inizio_mandato`, `data_fine_mandato`,
  `in_carica`, `delibera_assemblea`/`delibera_data` (mantenuti come
  fallback testuale, stessa decisione presa per Bilancio), `curriculum`,
  `note`, `inserito_da`.

## 3. Comportamento / flusso

1. Alla nomina di un membro del CD, si seleziona il socio esistente
   (`socio_id`) invece di reinserirne i dati anagrafici — nome,
   cognome, contatti provengono sempre da `gl_soci`.
2. Si registra la carica, le date di mandato, e — se disponibile — il
   collegamento al verbale reale che ha deliberato la nomina.
3. Se la carica assegnata è soggetta all'incompatibilità (oggi: solo
   Presidente), l'applicazione richiede/mostra la dichiarazione
   `dichiarazione_incompatibilita` con relativa data.
4. Un socio può comparire più volte in `gl_governance_cd` nel tempo (più
   mandati, anche con cariche diverse) — relazione uno-a-molti, non
   uno-a-uno.

## 4. Implementazione tecnica

- **`socio_id` obbligatoria, non nullable**: scelta possibile perché la
  tabella è oggi vuota (associazione non ancora costituita) — nessun
  dato esistente da conciliare in fase di `ALTER TABLE`. Presuppone che
  ogni membro del CD sia sempre anche socio; se in futuro emergesse
  un'eccezione (es. un revisore esterno non socio, aspetto da chiarire
  in sede di redazione dello statuto), servirà una migration separata
  per renderla nullable.
- **Incompatibilità tenuta generica, non vincolata a `carica =
  'Presidente'`** nello schema: decisione deliberata dopo aver
  verificato che la norma è già cambiata due volte (da "tutti gli
  amministratori" a "solo il presidente") in meno di 5 anni — un
  vincolo CHECK o una logica troppo rigida a livello di schema
  richiederebbe una migration ad ogni ulteriore modifica normativa. La
  decisione di chi debba compilare la dichiarazione resta alla logica
  applicativa, aggiornabile senza toccare il database.
- **Relazione `Socio::caricheCd()` come `hasMany`**, non `hasOne`: un
  socio può ricoprire cariche in mandati diversi nel tempo (es.
  consigliere per un mandato, poi presidente nel successivo).

## 5. Conformità e sicurezza

- Riferimento normativo: art. 11 D.Lgs. 36/2021, come modificato dal
  D.L. 96/2025 (conv. L. 119/2025, in vigore dal 10 agosto 2025).
- Nessun dato sensibile nuovo introdotto: i campi rimossi (dati
  anagrafici) restano comunque disponibili tramite `gl_soci`, non
  persi.
- La rimozione della ridondanza anagrafica riduce anche un rischio
  latente di disallineamento dati rilevante ai fini GDPR (due copie
  della stessa informazione personale, potenzialmente in stati diversi).

## 6. Interazioni con altri moduli

- **Soci** (`gl_soci`): nuova dipendenza diretta e obbligatoria tramite
  `socio_id` — prima il modulo era completamente indipendente.
- **Governance CD / Verbali** (`gl_verbali`): nuova dipendenza tramite
  `verbale_nomina_id`, stesso pattern già usato per Bilancio e
  Volontari.
- **Assemblee** (`gl_assemblee`): nessuna modifica, riuso diretto
  confermato — le assemblee restano fuori dall'ambito di questo
  adattamento.

## 7. Storia ed evoluzione

- Punto di partenza: osservazione diretta che `gl_governance_cd` teneva
  un'anagrafica separata da `gl_soci`, senza alcun collegamento —
  ridondanza non necessaria per una compagnia dove i membri del CD sono
  sempre soci.
- Decisione esplicita di Paolo: "eliminiamo la ridondanza" — non solo
  aggiungere il collegamento, ma rimuovere davvero i campi duplicati
  (a differenza di altri adattamenti in questa sessione, dove si è
  scelto di mantenere campi legacy come fallback, es. Bilancio).
- Sull'incompatibilità (art. 11), richiesto esplicitamente un
  ragionamento esteso prima di decidere lo schema: la ricerca ha
  rivelato un'evoluzione normativa recente e non scontata (D.L.
  96/2025, agosto 2025) che ha ristretto l'ambito da "tutti gli
  amministratori" a "solo il Presidente" — informazione che ha
  direttamente motivato la scelta di non irrigidire lo schema attorno
  alla regola attuale.
