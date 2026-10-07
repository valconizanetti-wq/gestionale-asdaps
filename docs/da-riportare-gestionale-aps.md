# Modifiche da riportare sul Gestionale APS

Elenco unico delle correzioni e delle funzioni nate nel Gestionale ASD che
vanno riportate sul Gestionale APS (Società Dante Alighieri — Comitato di
Orvieto). Aggiornato al 07/10/2026, con la v0.37.1 (correzioni dal collaudo K).

**Come si usa.** Ogni voce dice cosa succede nel Gestionale APS, cosa si è
fatto qui e dove guardare. I difetti sono stati trovati leggendo il codice
del Gestionale APS caricato nelle sessioni (settembre–ottobre 2026): prima
di correggere, controllare che la versione in uso sia ancora quella. Quando
una voce è riportata, si spunta la casella e si scrive la data e la
versione del Gestionale APS.

Le correzioni da riportare sul **Gestionale Core** restano in
`MANUALE-ASD.md` §7; qui sono segnate con *(anche nel core)* quelle che
valgono per entrambi.

Ordine: prima quello che tocca sicurezza e integrità dei dati, poi la
protezione dei dati personali, poi le funzioni.

---

## A. Sicurezza e integrità

- [ ] **A1. Catena dell'Audit Log dipendente dal fuso orario di MySQL.**
  - *Nel Gestionale APS*: `gs_audit_log.created_at` è un `TIMESTAMP`.
    MySQL lo converte secondo il fuso della connessione, e la data letta
    entra nell'impronta. Basta un cambio del fuso di MySQL o un
    trasloco su un altro server perché **tutta la catena risulti
    alterata** senza che nessuno l'abbia toccata.
  - *Qui*: colonna portata a `DATETIME`, convertita con il fuso di adesso
    (lo stesso con cui le righe sono state scritte: le impronte restano
    valide). Test che ripete la verifica con un altro fuso della
    connessione.
  - *Dove*: migration `2026_10_06_000093_created_at_datetime_in_gl_audit_log.php`;
    test in `tests/Feature/AuditLogTest.php`; `audit-log.md` §9
    ("v0.34.0 — realizzazione"). *(anche nel core)*
  - *Attenzione*: prima di cambiare la colonna, fare una verifica della
    catena e confrontare l'esito dopo la migration.

- [ ] **A2. Link delle email di conferma e annullamento della domanda
  che agiscono con un GET.**
  - *Nel Gestionale APS*: aprire il link basta a confermare o annullare
    la domanda. I filtri antispam e le anteprime delle caselle aprono i
    link da soli: possono confermare o annullare al posto del
    richiedente.
  - *Qui*: il link apre una pagina con un pulsante; il GET mostra, il
    POST agisce. Stessa regola per il link della privacy.
  - *Dove*: `domande-iscrizione.md` (controller e rotte di `iscrizione`);
    `MANUALE-ASD.md` §7.

- [ ] **A3. Token dei link salvati in chiaro nel database.**
  - *Nel Gestionale APS*: i token di conferma email, annullamento e voto
    del CD sono in chiaro. Chi accede al database o a un backup può
    confermare o annullare una domanda, o votare al posto di un
    consigliere.
  - *Qui*: nel database solo lo SHA-256 del token; il token in chiaro
    sta solo nell'email. Le colonne da 64 caratteri bastano, nessuna
    migration.
  - *Dove*: `Domanda::hashToken()`, `VotoCd::perToken()`, `PrivacyLink`;
    `domande-iscrizione.md`, "Secondo giro di revisione (28/09/2026)",
    punto 1.

- [ ] **A4. Silenzio-assenso che approva anche con la maggioranza
  contraria.**
  - *Nel Gestionale APS*: `SilenzioAssensoCommand` approva le domande
    scadute senza guardare i voti già espressi, scrive nell'audit senza
    hash (fuori dalla catena) e aggiunge un finto voto "Sistema".
  - *Qui*: comando `domande:scadenze` riscritto: esito calcolato sui voti
    (quorum, maggioranza, parità), audit tramite il servizio unico con
    hash, nessun voto finto; ogni passo si segna e non si ripete;
    opzione `--prova`.
  - *Dove*: `domande-iscrizione.md` ("Comando orario").

- [ ] **A5. Scrittura dell'audit ripetuta in tre punti.**
  - *Nel Gestionale APS*: lock, inserimento e calcolo dell'hash sono
    scritti tre volte (observer, servizio, verifica). Una differenza tra
    le tre copie rompe la catena.
  - *Qui*: l'observer scrive tramite `AuditLogService`; la formula
    dell'hash vive solo in `AuditIntegrityService::rigaPerHash()` /
    `hashRiga()`, e l'hash si calcola sulla riga riletta dal database,
    come la rilegge la verifica.
  - *Dove*: `audit-log.md` §8, differenze 1 e 6. *(anche nel core)*

- [ ] **A6. Ripristino non tracciato nell'audit.**
  - *Nel Gestionale APS*: l'observer non registra `restored`; inoltre la
    colonna `operazione` è un ENUM `created/updated/deleted` che
    rifiuterebbe qualunque altro valore.
  - *Qui*: registrato anche il ripristino; ENUM con `restored` e colonna
    nullable (gli eventi di business lasciano `operazione` vuota).
  - *Dove*: migration `2026_09_24_000082`; `audit-log.md` §8 (correzione
    v0.23.1). *(anche nel core)*

- [ ] **A7. Record di prova: controllo `is_test` ripetuto in ogni
  controller.**
  - *Nel Gestionale APS*: `if (!$domanda->is_test)` è ripetuto nei
    controller; un punto dimenticato scrive nella catena una riga che non
    si potrà più togliere.
  - *Qui*: il controllo sta una volta sola nell'`AuditObserver` e vale per
    ogni model che ha il campo.
  - *Dove*: `audit-log.md` §8 ("Record di prova").

- [ ] **A8. Login senza blocco dei tentativi.**
  - *Nel Gestionale APS*: nessun limite ai tentativi; un utente
    disattivato non viene rifiutato già al login.
  - *Qui*: blocco di 60 secondi dopo 5 tentativi per coppia email+IP;
    messaggio generico; utente disattivato rifiutato al login e
    disconnesso subito, dal middleware `attivo`, se lo si disattiva
    mentre è collegato.
  - *Dove*: `AuthController`, middleware `attivo`; `auth.md` §3.

- [ ] **A9. Esportazioni CSV senza protezione dalle formule.**
  - *Nel Gestionale APS*: le esportazioni CSV della Prima Nota (e degli
    altri elenchi) scrivono i testi così come sono: una descrizione o un
    nome che comincia con `=`, `+`, `-` o `@` viene eseguito da Excel come
    formula all'apertura del file (CSV injection).
  - *Qui*: i testi che cominciano con quei caratteri ricevono un `'`
    davanti; gli importi restano numeri.
  - *Dove*: `PrimaNotaController::exportCsv()`; `prima-nota.md` §7 (giro 1).
    *(anche nel core)* Da applicare anche agli altri CSV.

- [ ] **A10. Download dei giustificativi con nomi di file accentati.**
  - *Nel Gestionale APS*: il nome del file va nell'intestazione
    `Content-Disposition` così com'è; con lettere accentate o caratteri
    speciali (es. «Fattura Libreria Università.pdf») il download può
    fallire o arrivare con un nome sbagliato. Manca anche
    `X-Content-Type-Options: nosniff`.
  - *Qui*: `HeaderUtils::makeDisposition()` con il nome originale e un
    ripiego ASCII (RFC 6266), `nosniff`, controllo che il documento
    appartenga al movimento dell'indirizzo.
  - *Dove*: `PrimaNotaController::documento()`; `prima-nota.md` §4.

- [ ] **A11. Audit che fallisce in silenzio con un metodo `anno()` nel
  model.**
  - *Nel Gestionale APS*: l'`AuditObserver` legge `$model->anno`; se un
    model osservato ha un **metodo** `anno()` (non una colonna), Laravel lo
    tratta come relazione, l'eccezione viene presa dall'observer e finisce
    solo nel log: nessuna riga nell'Audit Log, senza alcun avviso. Da
    controllare i model del Gestionale APS (in particolare `PrimaNota`).
  - *Qui*: trovato dai test della v0.35.0; il metodo si chiama
    `PrimaNota::annoMovimento()`. Regola annotata nella scheda.
  - *Dove*: `prima-nota.md` §4. *(anche nel core)*

- [ ] **A12. Ricevute delle quote: numerazione senza lock e correzioni senza traccia.**
  - *Nel Gestionale APS*: `SociController::registraQuota` calcola il numero
    della ricevuta fuori da transazione e lock (due registrazioni insieme:
    doppione o errore 500); rinnovo e Prima Nota non sono nella stessa
    transazione; `RicevuteController::aggiorna` corregge numero, data e
    importo sovrascrivendo, senza audit e **senza aggiornare la Prima Nota**;
    `updateNumeroRicevuta` accetta vuoti e doppioni e non controlla che il
    rinnovo sia del socio; non esiste l'annullamento.
  - *Qui*: numero assegnato nella transazione con lock e vincolo unico;
    rinnovo, movimento e scadenza insieme; nessuna modifica: annullamento
    motivato (audit alto, timbro sulla ricevuta, movimento tolto) e nuova
    registrazione.
  - *Dove*: `rinnovi-quota.md` §4; `RinnoviQuota`.

- [ ] **A13. Ricevute: indirizzo di ritorno non controllato e numero dentro il JavaScript.**
  - *Nel Gestionale APS*: `RicevuteController::aggiorna` fa
    `redirect($request->input('_redirect'))` (redirect verso qualunque sito);
    il numero della ricevuta, testo libero, finisce dentro `onclick` e
    `x-data` (XSS memorizzato); `download()` con il solo permesso di lettura
    assegna numeri e scrive file (GET con effetti).
  - *Qui*: numeri generati dal gestionale e mostrati solo come testo;
    redirect solo verso rotte interne; download senza effetti.
  - *Dove*: `RinnoviController`.

## B. Dati personali (GDPR)

- [ ] **B1. Dati personali dentro la parte immutabile dell'audit.**
  - *Nel Gestionale APS*: il `dettaglio`, che entra nell'hash, contiene i
    valori modificati ("codice_fiscale: X -> Y"). Non si possono più
    cancellare senza rompere la catena, nemmeno alla pseudonimizzazione
    del socio.
  - *Qui*: il dettaglio elenca solo i **nomi** dei campi; i valori stanno
    in `old_values`/`new_values`, fuori dall'hash, e si possono oscurare.
  - *Dove*: `audit-log.md` §8, differenza 4. *(anche nel core)*

- [ ] **B2. Notifica HIGH al Presidente con i valori in chiaro.**
  - *Nel Gestionale APS*: l'email riporta i valori modificati.
  - *Qui*: solo modello, operazione, utente e numero di riga.
  - *Dove*: `audit-log.md` §8, differenza 4. *(anche nel core)*

- [ ] **B3. Tutti i dati delle domande nell'audit per sempre.**
  - *Nel Gestionale APS*: l'observer registra anagrafica, documento, IP e
    motivazioni delle domande in `old_values`/`new_values`, oltre i
    termini di conservazione promessi dall'informativa.
  - *Qui*: il model dichiara `$auditCampiConValore` (stato, date, consensi,
    versioni, collegamenti); degli altri campi resta solo il nome.
  - *Dove*: `AuditObserver`, `Domanda`; `domande-iscrizione.md`, "Secondo
    giro di revisione", punto 2.

- [ ] **B4. Registro delle email con i dati delle domande cancellate.**
  - *Nel Gestionale APS*: cancellando una domanda, le righe del registro
    degli invii restano con indirizzo, nome e oggetto (che contiene il
    nominativo); la tabella non si può cancellare ("prova legale").
  - *Qui*: le righe restano come prova dell'invio ma senza dati personali;
    si cancella anche la ricevuta PDF.
  - *Dove*: `Domanda::cancellaPerFineConservazione()`;
    `domande-iscrizione.md`, "Terzo giro di revisione".

- [ ] **B5. Creazione del socio da domanda senza privacy e tutore.**
  - *Nel Gestionale APS*: all'approvazione, il socio nasce senza i dati del
    consenso privacy e senza il genitore o tutore della domanda.
  - *Qui*: copiati metodo, data e ora, IP, versione dell'informativa, il
    tutore e (v0.33.0) il consenso a foto e video.
  - *Dove*: `IscrizioneSocio`; `domande-iscrizione.md`; `privacy-link.md`.

- [ ] **B6. Versione dell'informativa accettata non salvata.**
  - *Nel Gestionale APS*: `informativa_hash` non è un hash (stringa
    `"v." + data` prodotta dal browser), e la versione viene scritta su
    colonne inesistenti.
  - *Qui*: `informativa_versione` sulla domanda e SHA-256 del testo
    dell'informativa, calcolato dal server.
  - *Dove*: `domande-iscrizione.md` §2.

## C. Audit Log: consultazione e certificazione

Difetti trovati leggendo la sezione Audit Log del Gestionale APS
(05–06/10/2026). Dettaglio in `audit-log.md` §9, "Difetti del Dante da
non riportare".

- [ ] **C1. Pulsante "Ricalcola" che mostra il JSON grezzo**: invia il
  modulo all'indirizzo pensato per la chiamata in JSON. Qui: rimando alla
  pagina di verifica, che salva l'esito e lo mostra.
- [ ] **C2. Certificato dello stesso anno che sovrascrive il PDF
  precedente**: il file ha sempre lo stesso nome per anno, mentre la
  tabella conserva entrambe le righe; il codice univoco stampato nel PDF
  non viene salvato. Qui: codice nel nome del file e in `uuid_documento`;
  ogni riga dello storico scarica il suo PDF.
- [ ] **C3. Verifica mensile solo sull'anno precedente**: un'alterazione
  dell'anno in corso emerge solo l'anno dopo. Qui: ogni mese l'intera
  catena.
- [ ] **C4. Anomalie senza avviso**: il comando scrive solo nel log e nel
  registro. Qui: email al Presidente e agli eventuali Revisori, fascia
  rossa nell'elenco fino a una verifica pulita.
- [ ] **C5. "Hash" del PDF di esportazione che non è quello della
  catena**: è un'impronta di numero di righe, id e data. Qui: impronta
  reale dell'ultima riga del registro, letta prima di registrare
  l'esportazione stessa.
- [ ] **C6. Certificato e attestazione registrati con
  `operazione = created`**. Qui, come per gli altri eventi di business,
  operazione vuota.
- [ ] **C7. Ricerca con `LIKE` senza protezione di `%` e `_`**. Qui:
  caratteri protetti con `addcslashes`.
- [ ] **C8. Filtro degli eventi scritto a mano**, con eventi che non
  esistono più. Qui: eventi presenti nel registro dell'anno, con nomi in
  italiano da `config/audit.php`.
- [ ] **C9. Attestazione tecnica senza controllo della firma**: accetta
  qualunque PDF (`mimes:pdf`), anche la bozza non firmata. Qui: PDF con
  firma (PAdES) o `.p7m` (CAdES), controllata la presenza della firma.
  *Dove*: `App\Support\FirmaDigitale`; `audit-log.md` §9 (v0.34.1).
- [ ] **C10. Elenco a 50 righe fisse** (`paginate(50)`). Qui: righe per
  pagina 10 / 50 / 100 / Tutte, ricordate nella sessione, 10 dal telefono.
  *Dove*: `x-paginazione` (parametri `scelte`, `scelta`),
  `AuditLogController::index()`.

*Dove*: `AuditLogController`, `AuditConsultazione`,
`VerificaIntegritaAudit`, `AuditAnomalia`, viste `audit-log/*` e
`pdf/audit-*`.

## D. Funzioni nuove da portare

- [ ] **D1. Link personale per il consenso privacy** (v0.33.0). Nel
  Gestionale APS manca un modo per raccogliere o rinnovare il consenso di
  un socio già iscritto senza rifare la domanda. Qui: link monouso via
  email dalla scheda del socio o in blocco dall'elenco, senza scadenza,
  un nuovo invio annulla il precedente; anche per le nuove versioni
  dell'informativa; consenso a foto e video con revoca registrata dalla
  segreteria. *Dove*: `privacy-link.md`; `PrivacyLink`, `LinkPrivacy`,
  `PrivacyLinkController`, `PrivacyLinkInvito`, viste `privacy/*`.
  *(anche nel core)*
- [ ] **D2. Etichette leggibili nell'Audit Log**: livelli "Alto / Medio /
  Basso", operazioni e eventi in italiano ("Socio: modifica", "Link
  privacy inviato"). *Dove*: `config/audit.php`,
  `AuditConsultazione::evento()`.
- [ ] **D3. Esportazioni dell'Audit Log registrate** come `AUDIT_ESPORTATO`
  (MEDIUM). *Dove*: `AuditLogController::registraEsportazione()`.
- [ ] **D4. Anni contabili con saldi e chiusura controllata** (v0.35.0).
  Al posto della sola tabella degli anni chiusi: una riga per anno con
  saldi di apertura del primo anno, saldi di chiusura fotografati, data e
  riferimento dell'approvazione del rendiconto, lista di controllo prima
  della chiusura (anno finito, anno prima chiuso, niente da classificare,
  niente anticipi aperti), riapertura solo del Presidente con motivazione,
  correzioni motivate che aggiornano i saldi. Anche la lettura degli
  importi all'italiana («1.450» = 1450) in un campo di testo invece di
  `type="number"`. *Dove*: `prima-nota.md`; `AnniContabili`,
  `App\Support\Importo`.
- [ ] **D6. Ricevute delle quote corrette** (v0.36.0). Nel Gestionale APS:
  importo in lettere sbagliato (12,50 → «tredici», 31 → «trentauno»),
  dicitura «esenzione IVA ai sensi dell'art. 4» (l'art. 4 è il fuori campo;
  il passaggio all'esenzione è rinviato al 2036), ricevuta mai al genitore
  per i minori, indirizzo e codice fiscale scritti a mano nell'email,
  modalità «Assegno» e «Altro» nel modulo ma rifiutate dalla validazione.
  Qui: `ImportoInLettere`, dicitura «non soggetta a IVA (art. 4) e non
  imponibile (art. 148 TUIR)» da confermare con il commercialista, invio al
  genitore, dati dell'ente dal profilo. *Dove*: `rinnovi-quota.md` §3–§4.
- [ ] **D7. Solleciti come promemoria pianificati** (v0.36.0). Nel Gestionale
  APS `soci:sollecito-rinnovo` è pianificato con `yearlyOn(1, 4)` e
  `yearlyOn(1, 5)`, cioè il **4 e il 5 gennaio** (non aprile e maggio come
  dicono i commenti), senza registro né controllo dei doppioni, mai al
  genitore, con nomi ed email nel log del cron; se `quota_scadenza` è vuota
  scrive a tutti. Qui: servizio generale dei promemoria con date e testi
  modificabili, anteprima, prova, storico, una volta per anno. *Dove*:
  `promemoria.md`.
- [ ] **D5. Una riga di audit per operazione** (v0.35.1). Qui un
  giroconto creato, modificato o eliminato lasciava una riga per ciascuna
  delle due righe di Prima Nota (identiche a vista), e l'eliminazione di
  un movimento una riga in più per ogni giustificativo. Ora la riga
  compagna e i giustificativi si scrivono senza eventi e la riga del
  movimento porta il verso e i nomi dei file nei valori (metodo
  `auditContesto()` del model, letto dall'`AuditObserver`). Da verificare
  se il Gestionale APS ha lo stesso comportamento. *Dove*:
  `prima-nota.md` §4; `AuditObserver`, `PrimaNota::auditContesto()`.
  *(anche nel core)*
- [ ] **D8. Eliminazione della scheda del socio con motivazione e blocchi**
  (v0.37.1, collaudo K7). Qui «Elimina scheda» non chiedeva il motivo e
  lasciava eliminare un socio con quote, movimenti o tessere; il ripristino
  si trovava solo reinserendo il codice fiscale. Ora: motivazione
  obbligatoria nei valori dell'audit (non nel dettaglio che entra
  nell'hash), rifiuto con quote valide, movimenti o tessere, filtro «Schede
  eliminate» del Presidente con «Ripristina», niente esportazioni di quelle
  schede. Da verificare nel Gestionale APS. *Dove*: `soci.md` §7,
  `SociController::destroy`, `bloccaEliminazione`, `filtriEsportazione`.
  *(anche nel core)*
- [ ] **D9. Descrizione al passaggio del mouse sui pulsanti con la sola
  icona** (v0.37.1, richiesta di Paolo). `app.js` copia l'`aria-label` in
  `title`. *Dove*: `ui.md` (v0.37.1). *(anche nel core)*

## E. PDF ed email

- [ ] **E1. Larghezze delle colonne nei PDF messe su `<col>`**: DomPDF le
  ignora e le colonne escono tutte uguali (era così anche nel libro soci
  ripreso dal Gestionale APS). Qui: larghezze sulle celle d'intestazione
  (`th.c-…`). *Dove*: `pdf/libro-soci.blade.php`, `pdf/audit-log.blade.php`;
  `ui.md` (v0.34.0).
- [ ] **E2. Indirizzo del link in fondo alle email che esce dal
  riquadro**: non andava a capo. Qui: regola `p.indirizzo` nel tema delle
  email. *Dove*: `resources/views/vendor/mail/html/themes/sito.css`;
  `privacy-link.md` §7.

---

## Storia

- 06/10/2026: documento creato raccogliendo le voci sparse in
  `MANUALE-ASD.md` §7, `audit-log.md` §8–§9, `domande-iscrizione.md`,
  `privacy-link.md` e `auth.md`.
- 06/10/2026 (v0.35.0): aggiunte A9–A11 e D4 dalla Prima Nota.
- 06/10/2026 (v0.35.1): aggiunta D5 dal collaudo J.
- 07/10/2026 (v0.36.0): aggiunte A12, A13, D6, D7 da Rinnovi quota e Promemoria.
- 07/10/2026 (v0.37.1): aggiunte D8 e D9 dal collaudo K.
