# Audit Log / Archivio (analisi per il contesto ASD)

**Stato:** schema invariato; strato di scrittura e verifica consegnato (v0.21.0)
**Introdotto in:** v0.9.0 (2026-09-22) — analisi schema; v0.21.0 — scrittura e verifica;
v0.26.0 (2026-09-27) — esclusione dei record di prova
**File coinvolti (v0.21.0):**
- `app/Services/AuditLogService.php`, `AuditIntegrityService.php`,
  `RiskMatrix.php`, `app/Observers/AuditObserver.php` (ripresi dal
  Gestionale APS e adattati, vedi §8)
- `Socio.php`, `User.php`: attributo `#[ObservedBy([AuditObserver::class])]`

**File coinvolti (v0.26.0):**
- `app/Observers/AuditObserver.php`: esclusione dei record con `is_test`
  (§8, "Record di prova")
- `Domanda.php`: attributo `#[ObservedBy([AuditObserver::class])]`

**File coinvolti (v0.9.0):**
- Nessuna migration su `gl_audit_log`/`gl_audit_log_integrity`/`gl_audit_archivi` (schema confermato adeguato)
- Seeder aggiornato: `ImpostazioneSeeder.php` (nuova voce `audit_log_conservazione_anni`)

## 1. Logica

Il modulo era già stato riusato con successo, senza alcuna modifica, in
due moduli precedenti di questa sessione (Scontrini §6, Volontari §5)
per tracciare la motivazione obbligatoria richiesta su modifiche a
movimenti/rimborsi appartenenti ad anni contabili chiusi. Questa analisi
verifica se restano aspetti scoperti specifici del contesto ASD.

Un solo punto è emerso: `gl_audit_archivi` esiste per archiviare o
eliminare righe di `gl_audit_log` più vecchie di una soglia — soglia
che, però, non era codificata da nessuna parte, né come costante né
come configurazione. Dato che l'audit log traccia anche modifiche a
moduli contabili (Prima Nota, Bilancio), la soglia di conservazione non
è un dettaglio implementativo neutro: **l'art. 2220 c.c.** impone la
conservazione delle scritture contabili per **10 anni**. Una logica di
archiviazione implementata con una soglia più breve (es. i 5 anni della
conservazione rendiconto, vedi `bilancio.md` §5) rischierebbe di
eliminare tracciabilità ancora obbligatoria per legge.

## 2. Struttura dati

**Nessuna modifica** a `gl_audit_log`, `gl_audit_log_integrity`,
`gl_audit_archivi` — schema già generico e adeguato (riferimento
generico entità tramite `entita_tipo`/`entita_id`/`modello`, hash chain
per rilevazione manomissioni, campo `dettaglio` per motivazioni
testuali).

**Unica aggiunta**, in `gl_impostazioni` (non nelle tabelle di audit):
- `audit_log_conservazione_anni` = 10 (integer) — soglia minima di
  conservazione, da usare quando la logica di archiviazione verrà
  effettivamente implementata (non ancora scritta nel core).

## 3. Comportamento / flusso

Nessun cambiamento di comportamento rispetto al core: il modulo
continua a funzionare come già riusato in Scontrini e Volontari (righe
di log scritte dall'applicazione ad ogni evento rilevante, con hash
chain calcolato da un meccanismo esistente nel core, non ridisegnato
qui). L'unica differenza è che, quando si scriverà la logica di
archiviazione/pulizia periodica (`gl_audit_archivi`), questa dovrà
leggere `Impostazione::get('audit_log_conservazione_anni')` invece di
usare un valore hardcoded, e non archiviare/eliminare righe più recenti
di quella soglia.

## 4. Implementazione tecnica

- Nessuna modifica alle tabelle esistenti: la struttura generica
  (`entita_tipo`/`entita_id`/`modello`, `old_values`/`new_values` JSON,
  hash chain) si è dimostrata sufficiente per tutti i casi d'uso
  incontrati finora in questa sessione.
- La soglia di conservazione segue lo stesso pattern già adottato per le
  soglie del regime 398/91 (`scontrini.md` §6.1): valore in
  `gl_impostazioni` invece di una costante nel codice, per poter essere
  aggiornata senza deploy se la normativa cambiasse.
- La logica di archiviazione/pulizia periodica **non è stata
  implementata** in questa sessione (né lo era nel core) — resta un
  compito della fase applicativa futura.

## 5. Conformità e sicurezza

- **Art. 2220 c.c.**: conservazione delle scritture contabili per 10
  anni — riferimento diretto per la soglia `audit_log_conservazione_anni`.
- La distinzione tra la soglia di conservazione dell'audit log (10 anni,
  legata alla tracciabilità delle modifiche contabili) e quella del
  rendiconto stesso (5 anni, `bilancio.md` §5) è intenzionale: sono
  obblighi diversi, con basi normative diverse, e non vanno confusi o
  unificati sotto un unico valore.
- Hash chain e `gl_audit_log_integrity` (verifica periodica
  dell'integrità) restano gli stessi meccanismi già presenti nel core,
  non modificati né rivalutati in questa sessione.

## 6. Interazioni con altri moduli

- **Scontrini** (`gl_prima_nota_scontrini`): consumatore esistente,
  scrive in `gl_audit_log` per le modifiche su anno chiuso (vedi
  `scontrini.md` §6).
- **Bilancio**: la soglia di conservazione di questo modulo (10 anni)
  è più lunga di quella del rendiconto stesso (5 anni) — se in futuro
  emergesse la necessità di archiviare l'audit log, la soglia più lunga
  tra le due va sempre rispettata per i record legati a moduli
  contabili.
- **Impostazioni** (`gl_impostazioni`): nuovo consumatore della tabella
  già introdotta con lo Scontrini, confermandone l'utilità come
  infrastruttura condivisa tra moduli.

## 7. Storia ed evoluzione

- Modulo già riusato "silenziosamente" (senza scheda dedicata) in
  Scontrini e Volontari — questa è la prima analisi diretta e mirata
  delle sue tre tabelle.
- L'unico gap reale trovato (soglia di conservazione non configurata)
  è stato risolto con un'aggiunta minima e coerente con il pattern già
  stabilito (`gl_impostazioni`), senza toccare le tabelle di audit.
- Confermato, come per GDPR/Comunicazioni, che non tutti i moduli
  segnalati "da verificare" nella checklist iniziale richiedono
  modifiche sostanziali — qui il modulo era già solido, serviva solo
  di rendere esplicita e configurabile una soglia normativa.

## 8. Strato di scrittura e verifica (v0.21.0)

**Scoperta**: il core ha il model `AuditLog` e le tabelle, ma nessun
codice che scriva nel registro o ne verifichi la catena: `app/Services` e
`app/Observers` non sono stati portati dal Gestionale APS. Ripresi da lì
quattro file, adattati (`gs_` → `gl_`) e corretti.

**Come si usa:**
- **Automatico**: un model che dichiara
  `#[ObservedBy([AuditObserver::class])]` registra creazione, modifica,
  eliminazione e ripristino. Oggi: `Socio`, `User`, `Domanda`
  (v0.26.0). Convenzione per i moduli futuri: ogni model con dati di
  business lo dichiara.
- **Manuale**, per eventi di business senza un model dietro:
  `AuditLogService::scrivi(['evento' => '...', 'dettaglio' => '...'])`.
- **Verifica**: `AuditIntegrityService::verificaCatena()`, da collegare
  alla sezione Audit Log e a un comando periodico quando si costruiranno.

**Differenze rispetto al Gestionale APS:**
1. **Una sola implementazione di lock, inserimento e hash**: nel Dante la
   stessa logica era scritta tre volte (observer, servizio, verifica). Qui
   l'observer scrive tramite `AuditLogService`, e la formula vive solo in
   `AuditIntegrityService::rigaPerHash()`/`hashRiga()`.
2. **Registrato anche il ripristino** (`restored`), prima non tracciato:
   rilevante per il ripristino delle schede soci (`soci.md` §3).
3. **Escluso `ultimo_accesso`**, aggiornato a ogni login: altrimenti ogni
   accesso avrebbe generato una riga di audit.
4. **Dati personali fuori dalla catena** (GDPR):
   - `old_values`, `new_values`, `campi_modificati` restano **fuori**
     dall'hash, come nel Dante, ma ora per scelta dichiarata: contengono
     dati personali che alla pseudonimizzazione di un socio devono poter
     essere oscurati senza rompere la catena;
   - il **dettaglio**, che entra nell'hash, nel Dante conteneva i valori
     modificati ("codice_fiscale: X -> Y"): qui elenca **solo i nomi dei
     campi**, così nella parte immutabile del registro non finiscono dati
     personali;
   - la **notifica HIGH** al Presidente non riporta più i valori, solo
     modello, operazione, utente e numero di riga.
5. **`RiskMatrix` adattata**: tolti i casi del Dante senza equivalente
   (Prestazione, Collaboratore); aggiunti tra i campi MEDIUM `tipo`,
   `quota_scadenza`, `data_cancellazione`, `pseudonimizzato`; eliminazione e
   ripristino di un socio MEDIUM; per `User` anche la modifica di
   `attivo` è HIGH.
6. **Hash calcolato sulla riga riletta dal database**, esattamente come
   la rilegge la verifica (stessa formattazione di date e testi).

**Formula dell'hash — da congelare al primo deploy**: finché la catena
esiste solo in locale si può ancora cambiare; dal primo record in
produzione diventa immodificabile (vedi commento in
`AuditIntegrityService`).

**Conseguenza per la pseudonimizzazione** (da implementare con quella
funzione, `soci.md` §3): oltre ai dati in `gl_soci`, va oscurato il
contenuto di `old_values`/`new_values` delle righe di audit di quel socio
(`entita_tipo = 'Socio'`, `entita_id` = id), lasciando intatte le colonne
che entrano nell'hash.

**Notifiche HIGH**: partono via `Mail`; finché la posta non è configurata
(`MAIL_MAILER=log`) finiscono nel file di log, senza errori.

**Correzione v0.23.1 — valori di `operazione`**: la colonna è un ENUM del
core con i soli valori `created`/`updated`/`deleted`. Scoperto al primo
evento di business (esportazione del libro soci, operazione `export`,
rifiutata da MySQL). Conseguenze e correzioni:
- gli **eventi di business** (`AuditLogService::scrivi()` chiamato dai
  controller) lasciano `operazione` vuota, come nel Gestionale APS: il
  significato sta nell'`evento` (es. `SOCI_ESPORTATI`);
- il **ripristino** (`restored`) era rifiutato allo stesso modo, ma in
  silenzio: l'observer intercetta l'errore per non bloccare l'operazione,
  quindi il ripristino riusciva senza lasciare traccia nel registro (solo
  una riga in `laravel.log`). La migration `2026_09_24_000082` aggiunge
  `restored` all'ENUM leggendo dal database i valori esistenti, e rende la
  colonna nullable (necessario per gli eventi di business);
- **scelta confermata**: un errore di scrittura dell'audit **blocca**
  un'esportazione (il controller non intercetta l'eccezione), mentre non
  blocca le operazioni osservate sui model. Un'esportazione di dati
  personali senza traccia non deve avvenire; un salvataggio sì, con
  l'errore nel log.


**Record di prova (v0.26.0)**: l'observer non registra nulla per i
record con `is_test = true`: né creazione, né modifica, né eliminazione.
Oggi il campo esiste solo su `gl_domande`: una domanda inserita con la
modalità di prova attiva resta di prova per sempre.
- **Perché**: la catena dell'audit è permanente. Una riga scritta per una
  domanda di prova non si potrebbe più togliere senza rompere la catena.
  Così la domanda di prova si elimina senza lasciare tracce e senza
  ricalcolare gli hash.
- **Differenza rispetto al Gestionale APS**: lì il controllo
  `if (!$domanda->is_test)` era ripetuto in ogni controller (v76), e un
  punto dimenticato avrebbe scritto nella catena. Qui sta una volta sola
  nell'observer e vale per qualunque model che abbia il campo.
- **Eventi di business**: chi chiama `AuditLogService::scrivi()` a mano
  per una domanda (voto del CD, esito, accoglimento d'ufficio) deve
  comunque controllare `is_test` prima di scrivere. Il servizio non
  conosce il model e non può farlo da sé.
- **Limite accettato**: il controllo si basa sul valore attuale del campo.
  Se un record passasse da prova a reale, le sue operazioni precedenti non
  sarebbero nel registro. Non succede, perché `is_test` si fissa alla
  creazione della domanda e nessuna schermata lo modifica.

**Revisione v0.26.0 (28/09/2026)**:
- aggiunti agli esclusi dell'observer `token`, `token_conferma` e
  `token_link`: sono le chiavi dei link personali (domanda, conferma,
  voto del CD) e da `new_values` sarebbero state leggibili da chi consulta
  l'Audit Log;
- il controllo `is_test` legge l'array degli attributi, non
  `getAttribute()`, per non lanciare eccezioni sui model senza il campo
  se in futuro si attiva `Model::shouldBeStrict()`.

**Valori limitati per model (v0.26.0, secondo giro)**: un model può
dichiarare `public array $auditCampiConValore`. L'observer registra il
valore solo per quei campi; degli altri resta il nome in
`campi_modificati`, e il `dettaglio` non cambia. Oggi lo usa `Domanda`,
perché i dati delle domande non accolte o non confermate vanno cancellati
entro i termini dell'informativa, mentre il registro è permanente.

## 9. Consultazione, verifica e certificati (analisi, 05/10/2026)

**Stato:** implementato (v0.34.0, 06/10/2026); tavole `Audit-1…5`
approvate da Paolo.

**Origine.** Paolo (05/10/2026): riportare la funzione di audit log del
Gestionale Dante. File del Dante letti: `AuditLogController`,
`VerificaIntegritaAudit`, `ArchiviaAuditLog`, `AuditIntegrityService`,
viste `audit-log/*` (elenco, verifica, certificato, attestazione, export
PDF), rotte e pianificazione in `routes/console.php`. Qui esistono già lo
strato di scrittura (§8), il servizio di verifica (stessa struttura del
Dante) e le tabelle `gl_audit_log_integrity`, `gl_attestazioni_tecniche`,
`gl_audit_archivi`; mancano pagine, comandi e permessi.

**Decisioni di Paolo (05/10/2026):**
- si riportano tutte e quattro le parti: elenco con filtri, dettaglio ed
  esportazioni; verifica e certificato annuale; verifica mensile
  automatica; attestazione tecnica;
- **archiviazione rimandata** (nel Dante le righe LOW escono dal registro
  dopo 24 mesi; qui la soglia minima è 10 anni, art. 2220 c.c., e il
  registro resterà piccolo per anni);
- il certificato lo generano **Presidente e Revisore**; il Revisore resta
  in sola lettura per tutto il resto. Stessa regola per l'attestazione
  tecnica.

**Pagine (tavole):**
- *Elenco* (`Audit-1`, `Audit-2`): fascia sullo stato della catena
  (certificato / non certificato / anomalia, letta dall'ultimo
  certificato e dall'ultima verifica automatica, senza ricalcolare a ogni
  apertura); indicatori dell'anno (operazioni, rischio alto, medio,
  basso, operatori); filtri anno, mese, modulo, rischio, evento,
  operatore, ricerca; tabella con riga che si apre e mostra **prima /
  dopo** dei campi modificati, numero di riga, evento, IP, inizio
  dell'hash; esportazione CSV e PDF dei risultati filtrati. Su telefono,
  schede al posto della tabella e filtri sotto un pulsante.
- *Verifica* (`Audit-3`): spiegazione in parole semplici; anno e
  "Verifica adesso"; risultato (integra: righe, corrette, anomalie, hash
  finale, "Genera il certificato"; anomalie: elenco con numero di riga,
  data, evento, operatore, tipo di anomalia — collegamento o contenuto);
  storico dei certificati con download; attestazioni tecniche (bozza PDF,
  caricamento del PDF firmato e dell'eventuale relazione).
- *Certificato PDF* (`Audit-4`): carta intestata del periodo, anno, data
  della verifica, righe per livello di rischio, prima e ultima riga, hash
  finale completo, codice univoco del documento, chi l'ha generato, come
  si ricontrolla.
- *Email al Presidente* (`Audit-5`) quando la verifica mensile trova
  anomalie: solo numeri di riga e conteggi, nessun dato del registro.

**Etichette leggibili.** Livelli "Alto / Medio / Basso" invece di
HIGH/MEDIUM/LOW; operazioni "Creato / Modificato / Eliminato /
Ripristinato"; eventi di business con un nome in italiano (es.
`PRIVACY_LINK_INVIATO` → "Link privacy inviato") da una mappa in
`config/audit.php`, con un nome ricavato dal codice per gli eventi non in
mappa. Il codice resta visibile nel dettaglio.

**Difetti del Dante da non riportare (trovati nella lettura):**
1. Il pulsante "Ricalcola" dell'elenco invia un modulo all'indirizzo che
   risponde in JSON (pensato per la chiamata della pagina di verifica):
   chi lo preme vede il JSON grezzo. Qui: rimando alla pagina di verifica.
2. Il filtro degli eventi è un elenco scritto a mano, con molti eventi
   che non esistono più o non esistono qui. Qui: eventi presenti nel
   registro dell'anno, con l'etichetta in italiano.
3. Il certificato si salva sempre nello stesso file per anno
   (`audit/certificato-integrita-ANNO.pdf`): un secondo certificato dello
   stesso anno sovrascrive il PDF del primo, mentre la tabella ne conserva
   entrambe le righe; il codice univoco stampato nel PDF non viene
   salvato. Qui: file con il codice univoco nel nome e codice salvato in
   `uuid_documento`; ogni riga dello storico scarica il suo PDF.
4. La verifica mensile controlla solo l'anno precedente
   (`--anno=` anno scorso): un'alterazione dell'anno in corso emerge solo
   l'anno dopo. Qui: ogni mese l'intera catena; anomalie di qualunque
   anno.
5. Con un'anomalia il comando scrive solo nel log e nel registro: nessuno
   viene avvisato. Qui: email al Presidente (tavola `Audit-5`) e fascia
   rossa nell'elenco fino alla verifica successiva senza anomalie.
6. L'esportazione PDF stampa come "hash" un'impronta di numero di righe,
   id e data dell'ultima riga, che non ha a che fare con la catena e può
   trarre in inganno. Qui: si riporta l'hash finale reale della catena al
   momento dell'esportazione, con la data.
7. Gli eventi del certificato e dell'attestazione si registrano con
   `operazione = created`. Qui, come per gli altri eventi di business
   (§8, v0.23.1), operazione vuota.
8. La ricerca usa `LIKE` senza proteggere `%` e `_` scritti
   dall'utente. Qui: caratteri protetti.

**Permessi.** Sezione `audit_log`: Presidente pieno accesso, Revisore in
sola lettura (`SezioneSeeder`, già così). Eccezione esplicita: generare
il certificato e caricare un'attestazione sono permessi anche al Revisore
(controllo sul ruolo nel controller, non sul permesso di modifica).

**Organo di controllo facoltativo** (Paolo, 06/10/2026): lo statuto
(art. 20) lo prevede solo se l'Assemblea lo nomina, quindi il ruolo
Revisore può non avere utenti. Nessuna funzione dipende da lui: verifica,
certificato e attestazione li fa anche il Presidente; la verifica mensile
gira da sola. Se un Revisore c'è, riceve anche l'email delle anomalie e
vede la sezione in sola lettura.

**Comando** `audit:verifica-integrita` (pianificato il 1° di ogni mese
alle 05:00, con il cron già attivo): verifica dell'intera catena, esito
salvato nelle impostazioni (data e numero di anomalie, per la fascia
dell'elenco); con anomalie riga HIGH `AUDIT_INTEGRITY_FAIL` (senza dati
personali) ed email al Presidente e agli eventuali utenti Revisore; mai correzioni automatiche.

**Azzeramento prima dell'apertura** (v0.41.0, `php artisan
gestionale:azzera`, `docs/analisi-azzeramento.md` §3.5). Il comando
svuota `gl_audit_log`, `gl_audit_log_integrity`, `gl_audit_archivi` e
`gl_attestazioni_tecniche`, cancella `storage/app/private/audit` e toglie
l'impostazione `audit_ultima_verifica`. Poi scrive la **prima riga della
catena nuova**, `AZZERAMENTO_DATI_PROVA` (HIGH, utente «sistema
(gestionale:azzera)»), con le righe tolte per tabella, il numero di file
e il nome della copia di sicurezza. La verifica d'integrità riparte
pulita. Il certificato 2026 non si fa (dati di prova): il primo vero sarà
quello del 2027. Come operazione di sistema, l'avviso per email arriva al
Presidente.

### v0.34.0 — realizzazione (06/10/2026)

**File:** `AuditLogController`, `App\Services\AuditConsultazione`
(filtri, statistiche, etichette, stato della fascia, riepilogo dell'anno),
`config/audit.php` (etichette), comando `VerificaIntegritaAudit`
(`audit:verifica-integrita`, opzione `--silenzioso` per le prove), mail
`AuditAnomalia` (`emails/audit/anomalia`), viste `audit-log/index`,
`audit-log/verifica`, PDF `pdf/audit-log`, `pdf/audit-certificato`,
`pdf/audit-attestazione`; rotte in `routes/web.php` (gruppo
`permesso:audit_log`); pianificazione in `routes/console.php`; sezione 12
di `app.css`; test `tests/Feature/AuditLogTest.php` (7 test).

**Scelte di realizzazione:**
- *Esito dell'ultima verifica* nell'impostazione `audit_ultima_verifica`
  (json: data, modo automatica/manuale, righe, anomalie, prima riga
  anomala, impronta finale). La fascia dell'elenco lo legge senza
  ricalcolare la catena. Con anomalie la fascia resta rossa fino a una
  verifica senza anomalie.
- *Verifica a mano*: modulo che invia la richiesta, salva l'esito e
  torna alla pagina, che lo mostra (al posto della chiamata in JSON del
  Dante).
- *Certificato*: l'impronta stampata è quella dell'**ultima riga
  dell'anno** (non dell'ultima riga del registro): un certificato di un
  anno chiuso resta ricontrollabile negli anni successivi. Si rifiuta se
  ci sono anomalie fino a quella riga. PDF in
  `storage/app/private/audit/certificati/ANNO-CODICE.pdf`.
- *Operazioni e eventi* con nomi italiani: "Creazione / Modifica /
  Eliminazione / Ripristino"; gli eventi dell'osservatore diventano
  "Socio: modifica", "Utente: creazione"…
- *Esportazioni* CSV e PDF dei risultati filtrati, registrate come
  `AUDIT_ESPORTATO` (MEDIUM), come quelle dei soci. Il PDF si ferma a
  1.000 righe (le più recenti) e lo dice; per l'elenco completo il CSV.
- *Organo di controllo facoltativo*: senza Revisore fa tutto il
  Presidente; l'email delle anomalie va ai soli utenti attivi con email.

**Difetto trovato e corretto: data delle righe e fuso orario.**
`gl_audit_log.created_at` era un `TIMESTAMP`: MySQL lo converte secondo
il fuso della connessione e la data letta entra nell'impronta. In locale
un riavvio di MariaDB in un altro fuso ha fatto risultare anomala la
prima riga del registro. Sul server succederebbe con un cambio del fuso
di MySQL o con la migrazione al dominio definitivo su un altro server:
tutta la catena risulterebbe alterata senza che nessuno l'abbia toccata.
La migration `2026_10_06_000093` porta la colonna a `DATETIME`
(convertita con il fuso di adesso, lo stesso con cui le righe sono state
scritte: le impronte restano valide). Test: la verifica dà lo stesso
esito con un altro fuso della connessione. **Da segnalare al Gestionale
APS**, che ha lo stesso tipo di colonna.

**Difetto trovato e corretto (non solo Audit Log): larghezze delle
colonne nei PDF.** DomPDF ignora le larghezze messe sugli elementi
`<col>`: nel libro soci e nel nuovo PDF dell'Audit Log tutte le colonne
uscivano uguali. Ora le larghezze stanno sulle celle d'intestazione
(`th.c-…`). Da segnalare anche al Gestionale APS se usa lo stesso modo.

**Revisione critica (tre giri, 06/10/2026):**
1. *Codice*: gli eventi dell'osservatore comparivano nei filtri come
   "Socio updated" (corretto con i nomi italiani, al sostantivo per non
   sbagliare il genere: "Domanda: eliminazione"); il fuso orario della
   colonna `created_at` (sopra).
2. *Pagine e PDF*: nel PDF di esportazione le colonne erano tutte
   uguali (anche nel libro soci) e la "ultima riga all'estrazione" era
   quella dell'esportazione stessa, che non compare nell'elenco (ora
   l'impronta si legge prima di registrare l'esportazione); su telefono
   modulo e numero di riferimento finivano ai due lati della scheda.
3. *Regressioni*: 48 test su MySQL superati (7 nuovi); migration provata
   anche all'indietro; pagine a 1440 e 375 px senza scorrimento
   orizzontale; nessun altro PDF usa `<col>`.

### v0.34.1 — correzioni dal collaudo (06/10/2026)

Collaudo di Paolo, blocco I:
- **I1/I4 numero di riga**: si vedeva solo nel dettaglio. Ora colonna
  «N.» su desktop e «n. 22» accanto alla data su telefono, dove numero,
  data, ora e freccia stanno su una sola riga (prima la freccia occupava
  una riga a sé).
- **I1 righe per pagina**: 10 / 50 / 100 / Tutte, nel piè della tabella
  (componente `x-paginazione` con `scelte`), ricordata nella sessione
  (`audit_per_pagina`). Senza scelta 50; dal telefono la pagina chiede 10
  una volta (solo se l'indirizzo non ha già `per_pagina`, per non
  ricaricare all'infinito se la sessione non si salva).
- **I3 una riga aperta alla volta**: lo stato è sulla tabella
  (`aperta` = numero della riga), non su ogni riga.
- **I3 `luogo_nascita` nella modifica dell'indirizzo**: corretto così.
  `SocioRequest` ricalcola sempre data, luogo e sesso dal codice fiscale;
  sui soci inseriti senza luogo (prove, dati di prima della v0.30.x) il
  primo salvataggio lo scrive, e il registro lo riporta. Succede una volta.
- **I5 CSV e PDF vuoti**: non riprodotto (qui escono pieni; test sul
  contenuto del CSV aggiunto). In attesa dei dati di Paolo.
- **I6/I7 storico dei certificati**: quattro colonne (anno con impronta,
  data con righe, chi con il ruolo sotto, PDF), senza scorrimento;
  attestazioni in tre colonne. PDF del certificato: nome e ruolo su due
  righe, codice del documento a capo su una riga sola.
- **I8 finestra dell'attestazione**: tutte le finestre ora stanno nello
  schermo (corpo che scorre, piede fermo; su telefono al massimo il 92%
  dell'altezza) e i moduli nelle finestre sono più compatti (campi da 34
  px su desktop, 38 su telefono con testo a 16 px per non far ingrandire
  la pagina su iPhone).
- **I8 firma digitale**: prima nessun controllo (come nel Gestionale APS,
  che accetta qualunque PDF). Ora `App\Support\FirmaDigitale` rifiuta un
  file senza firma: PDF con firma dentro (PAdES: `/ByteRange` e
  `/SubFilter` di firma) o busta `.p7m` (CAdES, `signedData`, anche in
  base64). Controlla che la firma ci sia, non la validità del certificato
  (catena di fiducia, revoca), che si verifica con gli strumenti dei
  certificatori o dell'AgID. Il tipo di firma va nel riepilogo della riga
  di audit.

**Revisione critica (tre giri):**
1. *Codice*: la richiesta automatica di 10 righe dal telefono poteva
   ricaricare la pagina all'infinito senza sessione (aggiunto il controllo
   sull'indirizzo); valore di `per_pagina` non previsto ignorato.
2. *Pagine*: con la finestra a corpo scorrevole i suoi elementi si
   schiacciavano (pulsante della bozza alto pochi pixel): bloccato il
   restringimento. Verificate a 1440 e 375 px elenco, verifica, finestra
   dell'attestazione e, perché il cambio delle finestre è generale, la
   scheda socio con «Carica documento» e l'elenco soci.
3. *Regressioni*: 49 test (nuovi: righe per pagina, contenuto del CSV,
   PDF senza firma rifiutato, `.p7m` binario e in base64 accettati).

### v0.34.2 — secondo giro di collaudo (06/10/2026)

- **I5 chiarito**: il PDF «vuoto» mandato da Paolo riporta «Righe: 0 ·
  ricerca «%»», cioè era stato estratto con la ricerca della prova I4
  ancora attiva; il CSV dopo l'azzeramento esce pieno. Nessun difetto
  nell'esportazione. Per non produrre file vuoti, ora i pulsanti CSV e
  PDF sono spenti quando i filtri non trovano righe.
- **I1**: pulsante del dettaglio più piccolo su telefono (28 px).
- **I8**: oggetto dell'attestazione già compilato («Attestazione tecnica
  sullo stato del registro delle operazioni al gg/mm/aaaa»), modificabile.

Revisione (tre giri): oggetto proposto che non sovrascrive quello scritto
in caso di errore (`old()`); pulsanti spenti con il motivo nel
suggerimento; 49 test, pagine a 1440 e 375 px senza scorrimento.

- **v0.40.0 (13/10/2026), avvisi delle operazioni delicate**:
  - ogni riga HIGH scritta da un utente collegato fa partire, dopo il
    commit, un'email agli altri fra Presidente, Segretario e Tesoriere che
    vedono la sezione dell'operazione (`App\Services\AvvisiOperazioni`);
  - una sola email per persona e per richiesta, con tutte le righe;
  - non arriva a chi ha eseguito;
  - per le operazioni di sistema arriva solo al Presidente.

  Sostituisce `AuditObserver::notificaHigh`. Eventi nuovi: `UTENTE_RUOLI`
  (HIGH), `UTENTE_LINK_PASSWORD` (LOW), `UTENTE_PASSWORD` (MEDIUM),
  `IMPORT_SOCI` (HIGH).
