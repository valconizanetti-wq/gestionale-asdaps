# Manuale — Derivato ASD (Compagnia Arcieri Castelviscardo A.S.D.)

Repo previsto: `gestionale-asd` (da template `gestionale-core`)

Questo manuale segue la stessa struttura di `MANUALE-CORE.md` e raccoglie le
decisioni prese per derivare dal core un gestionale per una ASD pura. Va
aggiornato ad ogni sessione di lavoro sul derivato.

## 0. Stato del progetto

- **Fase attuale** (aggiornata a v0.16.0): **base dati del derivato
  completa**. Tutti i moduli ereditati dal core sono stati rivisti
  (adattati o confermati in riuso diretto, vedi §3), la verifica di
  completezza incrociata è stata eseguita (§6.11), i nuovi moduli sono
  progettati e consegnati (Tesseramento sportivo §4.1, Compliance RASD
  §4.2, Volontari §5, Scontrini §6) e il sistema permessi a sezione
  intera è seedato (§6.13). **Decisione architetturale importante**:
  Gestione gare **non** è un modulo di `gestionale-asd` — sarà un'app
  Laravel indipendente, nome provvisorio **ArcoGare** (vedi §4.3).
  **Forma giuridica attuale** (chiarito da Paolo): la Compagnia esiste già
  come B.A.S. (C.F. 90022610555) e diventerà ASD mantenendo lo stesso
  codice fiscale; la "costituzione 1/1/2028" citata nelle schede va letta
  come passaggio ad ASD, non come nascita di un nuovo ente.
  **Identità visiva e fondamenta UI definite** (v0.17.0, scheda
  `ui.md`): palette dal logo, token Tailwind 4, classi condivise, font e
  Alpine in locale. **Primo strato web** (v0.19.0, scheda `auth.md`):
  il core non ne aveva alcuno; ora ci sono login, middleware `attivo` e
  `permesso:`, layout con sidebar generata da `config/navigazione.php`.
  Prossimo passo: componenti Blade e modulo Soci.
- **Associazione di riferimento**: Compagnia Arcieri Castelviscardo A.S.D.
  (tiro con l'arco).
- **L'associazione non esiste ancora**: si costituirà il **1° gennaio
  2028** e opterà **da subito** per il regime L. 398/1991 (partirà quindi
  già sotto il regime IVA di esenzione in vigore dal 1/1/2026, senza dover
  gestire alcuna transizione da un regime precedente).
- **Ente affiliante: CSEN** (Ente di Promozione Sportiva riconosciuto dal
  CONI) — non FITARCO.

### 0.1 Setup ambiente: problema Composer risolto

Durante il primo `composer install` su questo PC si è ripresentato (in
forma più ostinata) il problema "Permission denied" già noto dal core,
ma questa volta **non era solo Avast**. Cronologia delle cause trovate e
risolte, nell'ordine:

1. **Ownership della cartella**: `C:\laragon\www\gestionale-asd` risultava
   di proprietà di `BUILTIN\Administrators` invece dell'utente Windows
   `Paolo` (probabile clone iniziale da terminale elevato). Risolto con:
   ```
   icacls C:\laragon\www\gestionale-asd /grant "DESKTOP-PAOLO\Paolo:(OI)(CI)F" /T /C
   ```
   Nota: senza i flag `(OI)(CI)` (ereditarietà su oggetti/contenitori), il
   permesso non si propaga alle nuove sottocartelle create da Composer
   durante l'installazione — un primo tentativo di `icacls` senza questi
   flag non aveva risolto nulla.
2. **SSL/TLS bloccato da Avast Web Shield**: tutte le connessioni HTTPS
   (Packagist, GitHub, getcomposer.org) fallivano con "unable to get
   local issuer certificate", mentre l'HTTP passava — sintomo tipico di
   un componente che intercetta il traffico HTTPS. Causa: la scansione
   HTTPS del Web Shield di Avast (modulo diverso dalle "Protezioni
   principali" e dalla "Protezione ransomware"). Confermato disattivando
   temporaneamente tutto Avast e verificando con `composer diagnose`.
3. **Causa finale, quella che serviva davvero**: anche con ownership e
   SSL sistemati, il download falliva ancora, sempre in blocco su tutti i
   pacchetti scaricati **in parallelo** nello stesso istante. La causa
   reale non era più l'antivirus, ma il **download parallelo di
   Composer** che genera conflitti di scrittura su Windows in questo
   ambiente. **Soluzione risolutiva**:
   ```
   set COMPOSER_MAX_PARALLEL_HTTP=1
   composer install
   ```
   (su PowerShell: `$env:COMPOSER_MAX_PARALLEL_HTTP=1` prima del comando)

**Per il futuro**: se il problema si ripresenta (su questo PC o su un
altro derivato), partire direttamente da `COMPOSER_MAX_PARALLEL_HTTP=1`
prima di rincorrere ownership/antivirus — è la causa che ha
effettivamente risolto, le altre due erano comunque problemi reali ma
non la causa scatenante del blocco finale. Da valutare se aggiungere
questa variabile in modo permanente (es. variabile d'ambiente di
sistema) invece di doverla impostare ad ogni sessione di terminale.

## 1. Inquadramento dell'associazione

Chiarito con Paolo (primi 3 punti indicati in `bootstrap-avvio-asd.md`):

1. **Natura giuridica**: ASD **pura**, non anche APS — non iscritta al
   RUNTS. Riferimento esclusivo: RASD (Registro Nazionale delle Attività
   Sportive Dilettantistiche), non RUNTS.
2. **Attività**: tiro con l'arco.
3. **Personale**: nessun dipendente, nessun collaboratore — **solo
   volontari**. Da tenere presente per lo scoping iniziale: il modulo
   Erogazioni/Collaboratori del core non serve oggi, ma lo schema va
   mantenuto per un'eventuale evoluzione futura (es. istruttori pagati).

## 2. Quadro normativo e fiscale (verificato a settembre 2026)

Punti di partenza per ogni decisione di schema. Da riverificare comunque ad
ogni ripresa della sessione, la materia è in evoluzione attiva (correttivi,
proroghe, circolari).

### 2.1 Registro e affiliazione

- Riferimento: RASD (D.Lgs. 39/2021), non RUNTS.
- L'iscrizione al RASD passa **tramite l'ente affiliante**: per questa
  compagnia è **CSEN**, Ente di Promozione Sportiva riconosciuto dal CONI
  (non FITARCO) — non è l'ASD a iscriversi direttamente, è CSEN a
  trasmettere i dati al RASD dopo l'affiliazione.
- Senza affiliazione valida, l'iscrizione al RASD decade: la scadenza di
  affiliazione va trattata come scadenza critica nel gestionale, non solo
  informativa.
- Dal 2026 Sport e Salute conduce verifiche sistematiche (incrocio con
  Anagrafe Tributaria) sulle ASD iscritte, con 90 giorni per regolarizzare
  eventuali anomalie riscontrate.
- **Opportunità della costituzione ex novo**: essendo l'associazione da
  costituire, l'atto costitutivo/statuto può essere redatto fin da subito
  conforme ai requisiti degli artt. 7-8 D.Lgs. 36/2021 (assenza di scopo
  di lucro, clausole obbligatorie), evitando il lavoro di adeguamento
  statutario "in corsa" che ha riguardato altre ASD/SSD già esistenti
  (vedi Circolare 7/E/2026, §2.2). Da verificare in una sessione dedicata
  se serve un modulo/checklist per il testo statutario, o se resta fuori
  dal perimetro del gestionale.

### 2.2 Volontari (unica forza lavoro dell'associazione)

Normativa: D.Lgs. 36/2021 art. 29, corretto da D.Lgs. 163/2022 e D.Lgs.
120/2023, riscritto da D.L. 71/2024 (L. 106/2024), chiarito dalla
**Circolare Agenzia Entrate 7/E del 7 agosto 2026**.

- Attività gratuita, spontanea, senza fini di lucro; incompatibile con
  qualunque rapporto di lavoro (subordinato o autonomo) con la stessa ASD.
- **Rimborsi ordinari**: spese documentate o autocertificate (art. 46 DPR
  445/2000) per vitto/alloggio/viaggio/trasporto, entro **150 €/mese**,
  su delibera dell'organo sociale competente (tipologie di spesa e
  attività ammesse).
- **Rimborsi forfettari**: fino a **400 €/mese**, solo per attività svolte
  durante manifestazioni/eventi sportivi riconosciuti (non per l'attività
  ordinaria). Vanno **comunicati al RASD** entro il mese successivo al
  trimestre di svolgimento.
- **Soglia di non imponibilità cumulativa**: il rimborso forfettario resta
  non tassabile solo se il volontario, sommando eventuali compensi da
  *lavoro* sportivo percepiti altrove (autonomo/co.co.co./subordinato),
  non supera **15.000 €/anno**. Oltre soglia, il rimborso forfettario
  diventa reddito imponibile. Implica una dichiarazione periodica del
  volontario sui compensi percepiti presso altri enti.
- **Assicurazione obbligatoria**: l'ASD deve assicurare i volontari per la
  responsabilità civile verso terzi (RCT) — art. 29 c.4. In pratica quasi
  sempre inclusa nella polizza collettiva del tesseramento federale.
- Il tesseramento del volontario non è obbligatorio per legge, ma è la via
  pratica più semplice per garantire la copertura assicurativa.

### 2.3 IVA

- **Correzione del 06/10/2026** (verifica per la ricevuta delle quote,
  `rinnovi-quota.md` §4): il passaggio da operazioni escluse IVA (art. 4
  DPR 633/72) a operazioni esenti (art. 10) per gli enti associativi,
  dato qui in vigore dal 1° gennaio 2026, è stato **rinviato al 1° gennaio
  2036** (provvedimento pubblicato in Gazzetta Ufficiale il 12/12/2025).
  Le quote associative restano fuori campo IVA e non imponibili (art. 148,
  c. 1, TUIR). Dicitura sulla ricevuta da far confermare al commercialista.
- Regime **L. 398/1991**: confermato, l'associazione opterà per questo
  regime fin dalla costituzione (1/1/2028) — partirà quindi già sotto il
  regime IVA di esenzione in vigore dal 1/1/2026, senza transizioni da
  gestire.
  Plafond 400.000 €/anno di soli proventi commerciali (sponsorizzazioni,
  bar sociale, gadget — non le quote associative); esonero da
  fatturazione/registrazione/dichiarazione IVA tranne che per
  sponsorizzazioni e pubblicità (sempre fatturabili); coefficiente
  forfettario 3% ai fini reddituali sui proventi commerciali.
- **Obbligo di tracciabilità**: pagamenti/versamenti da e verso l'ASD
  **≥ 1.000 €** devono transitare su conto corrente bancario/postale
  intestato all'associazione.

### 2.4 Bilancio

- Il **Bilancio DM39** (schema RUNTS) **non si applica** a un'ASD non
  iscritta al RUNTS.
- Resta comunque necessario un **rendiconto economico-finanziario
  annuale**, con riferimenti normativi precisi (verificati in sessione
  successiva, vedi §6.2):
  - **Art. 20 DPR 600/1973** — obbligo di tenuta contabile per gli enti
    non commerciali, richiamato per chi adotta la L. 398/1991.
  - **Art. 148 TUIR** — la mancata approvazione assembleare del
    rendiconto può far perdere le agevolazioni fiscali (requisito
    sostanziale, non solo formale).
  - **Art. 90 c.18 lett. d) L. 289/2002, richiamato dall'art. 7 D.Lgs.
    36/2021** — vincolo statutario: non solo redigere il rendiconto, ma
    predeterminare nello statuto le modalità di approvazione tramite un
    **iter deliberativo specifico**.
  - **Nessun modello ministeriale obbligatorio**: prospetto
    entrate/uscite, criterio di cassa, nessun deposito pubblico
    richiesto (a differenza del DM39 RUNTS).
  - Redatto dal **Consiglio Direttivo**, approvato dall'**Assemblea**
    entro **4 mesi** dalla chiusura dell'esercizio (estensibile a 6 con
    motivate esigenze).
  - Distinzione tra attività istituzionale e commerciale richiesta
    esplicitamente in regime 398/91 — coincide con `flag_non_istituzionale`
    già discusso in §6.1.
  - Conservazione **5 anni**; copia allegata al verbale di assemblea che
    lo approva.

### 2.5 Certificazione medica e tesseramento (specifico tiro con l'arco)

- Il tiro con l'arco è tra gli **"sport di tiro"** esonerati per legge
  (circolare CONI/Ministero Salute, DM 24/04/2013) dal certificato medico
  **non agonistico** obbligatorio.
- Per i tesserati classificati **agonisti** dall'ente affiliante, resta
  obbligatorio il **certificato medico agonistico**, rilasciato solo da
  un medico dello sport, con **validità biennale** per il tiro con l'arco
  (a differenza della validità annuale della maggior parte degli sport).
  Il certificato va conservato dall'ASD.
- Visite di idoneità gratuite per i minorenni (richieste dall'ASD).
- **Gare e certificato medico**: il discrimine non è il "livello amatoriale"
  della gara, ma la classificazione che l'ente affiliante (**CSEN**, o
  un'eventuale sub-affiliazione FITARCO se in futuro la compagnia
  volesse accedere a competizioni della federazione) attribuisce a quella
  specifica gara/tesseramento:
  - **gare sociali interne alla compagnia**, non iscritte al calendario
    ufficiale, non valide per classifiche/ranking → restano non agonistiche,
    nessun certificato richiesto (esonero come sopra);
  - **gare ufficiali iscritte al calendario CSEN (o FITARCO)** (anche se
    percepite come "amatoriali" o di basso livello, es. tornei
    giovanili/regionali) → richiedono tipicamente il tesseramento come
    agonista per quella disciplina/categoria e quindi il certificato medico
    agonistico, indipendentemente dal livello sportivo effettivo.
  - Da verificare puntualmente sul regolamento tesseramento CSEN in
    vigore, perché la classificazione può cambiare per disciplina/categoria.
- **Conclusione per la configurazione attuale della compagnia**: tutti gli
  associati sono tesserati come **non agonisti** e la compagnia non
  partecipa a gare di calendario ufficiale CSEN/FITARCO. Di conseguenza,
  **allo stato attuale non c'è alcun obbligo di raccogliere certificati
  medici**, né per l'attività ordinaria né per eventuali gare amichevoli
  (interne alla compagnia o tra compagnie), perché l'esenzione riguarda la
  disciplina in sé quando resta non agonistica, non il fatto che si tratti
  di allenamento o gara, né l'ente affiliante specifico (l'esenzione
  copre i tesserati di qualunque EPS/federazione riconosciuti dal CONI,
  quindi vale allo stesso modo per CSEN).
  - Riferimento normativo esenzione certificato: **DM Salute 24 aprile
    2013** (attuativo dell'art. 7, comma 11, D.L. 13 settembre 2012, n.
    158 — "Decreto Balduzzi", conv. L. 8 novembre 2012, n. 189), **art. 3**
    — attività sportive che non comportano impegno fisico, cui si aggiunge
    la **circolare CONI/Ministero della Salute** attuativa (linee guida
    Ministero Salute 08/08/2014) che elenca esplicitamente il tiro con
    l'arco tra gli "sport di tiro" esonerati.
  - Riferimento normativo esenzione DAE: stesso **DM Salute 24 aprile
    2013**, **art. 5, comma 3** — esclude dall'obbligo di dotazione e
    utilizzo del defibrillatore semiautomatico le società/associazioni
    che praticano esclusivamente attività a ridotto impegno
    cardiocircolatorio, tra cui gli sport di tiro.
  - Per questa ragione, il modulo Tesseramento sportivo/Certificati
    medici (§4.1) **non va attivato/reso obbligatorio ora**: resta come
    struttura pronta per un'eventuale futura scelta della compagnia di
    tesserare qualcuno come agonista o di partecipare a gare di calendario
    ufficiale.
- **Verifica specifica CSEN (fatta)**: nessuna fonte CSEN contraddice
  l'esenzione generale. Due punti da tenere presenti:
  - CSEN ha un **Settore Nazionale Tiro con l'Arco** con un proprio
    Regolamento Tecnico di Settore, distinto dal Regolamento Organico
    generale — da reperire in sede di affiliazione per verificare che non
    aggiunga condizioni prudenziali proprie sopra il minimo di legge.
  - Alcune ASD (di altri EPS) adottano comunque il certificato medico
    come **policy interna volontaria** (non per obbligo di legge), spesso
    per motivi assicurativi/di responsabilità sul campo di tiro. Non è un
    vincolo esterno: è una scelta da eventualmente discutere con il
    direttivo, indipendente dall'obbligo normativo.
- **Sequenza temporale affiliazione**: per affiliarsi a CSEN serve già
  una copia dell'**atto costitutivo e dello statuto registrato**, oltre al
  documento d'identità del Presidente — quindi l'affiliazione CSEN arriva
  necessariamente **dopo** la costituzione formale (1/1/2028), non prima.

### 2.6 GDPR

- Nessuna novità normativa specifica, ma probabile trattamento di dati di
  minori e dati sanitari (certificati medici): da censire come trattamenti
  specifici nel registro trattamenti ("certificati medico-sportivi",
  "tesseramento minori").
- **Verificato in sessione successiva** (vedi §6.4): **nessuna modifica
  allo schema necessaria**. `gl_gdpr_trattamenti` è già completamente
  generico (campi testuali liberi), aggiungere un trattamento significa
  solo inserire una riga. I dati minori sono già coperti a livello di
  `gl_soci` (campi tutore, consenso privacy). Il trattamento "certificati
  medico-sportivi" andrà censito **quando** (e se) il modulo Tesseramento
  sarà attivato, non ora (oggi non attivo, §2.5). L'invio dati a CSEN non
  richiede un accordo responsabile del trattamento (CSEN è titolare
  autonomo per le proprie finalità federative).

### 2.7 Governance CD — incompatibilità amministratori

- **Art. 11 D.Lgs. 36/2021, come modificato dal D.L. 96/2025 (conv. L.
  119/2025, in vigore dal 10 agosto 2025)**: il divieto di ricoprire
  qualsiasi carica in altre ASD/SSD affiliate alla stessa
  Federazione/Disciplina associata/Ente di Promozione Sportiva (qui:
  CSEN) — che nella versione originaria del 2021 riguardava **tutti**
  gli amministratori — oggi si applica **solo al Presidente/legale
  rappresentante**. Gli altri membri del CD (vicepresidente, segretario,
  tesoriere, consiglieri) non hanno questa restrizione.
- **Non è una clausola statutaria obbligatoria** (art. 7 c.1), solo
  consigliabile per evidenza di conformità.
- Norma già cambiata due volte in pochi anni (tutti gli amministratori →
  solo il presidente): da tenere presente come area normativa volatile,
  da riverificare periodicamente.

## 3. Checklist moduli (rispetto a `MANUALE-CORE.md` §5)

| Modulo core | Decisione | Note |
|---|---|---|
| Associazione | adattamento — **completato** | vedi §6.12: default `forma_giuridica` corretto, aggiunti campi RASD e scadenza affiliazione |
| Ruoli/Utenti | riuso diretto — **confermato** | vedi §6.11 |
| Soci | riuso diretto — **confermato** | nessuna modifica allo schema; le nuove tabelle del modulo Tesseramento si collegano con FK verso `gl_soci`, non viceversa (solo relazioni Eloquent inverse aggiunte su `Socio.php`). Verifica prima delle viste in scheda `soci.md`: regole di uscita del socio, minore età calcolata dalla data di nascita, correzione del default di `anno_iscrizione`; tessera interna ancora da decidere |
| Rinnovi quota | riuso diretto — **confermato** | vedi §6.8; tracciabilità già coperta via `gl_prima_nota`, dicitura esenzione IVA è dettaglio template PDF |
| Prima Nota | adattamento — **completato** | modulo Scontrini/OCR (§6) + monitoraggio plafond/tracciabilità 398/91 (§6.1), nessuna nuova tabella oltre a quelle di §6 |
| Progetti | riuso diretto — **confermato** | vedi §6.9; `codice_dm39`/`codice_documento` residui RUNTS non applicabili, lasciati inutilizzati |
| Governance CD | adattamento — **completato** | vedi §6.3: eliminata ridondanza anagrafica (FK `socio_id` obbligatoria), verbale nomina, incompatibilità art. 11 |
| Assemblee, Verbali | riuso diretto | |
| Domande/Iscrizione | adattamento — **completato** | vedi §6.10: allineato enum `tipo_socio` a `gl_soci.tipo` (rimossi `ordinario_rivista`, `vitalizio`) |
| GDPR | riuso diretto — **confermato** | vedi §6.4: schema già generico, censimento trattamenti rimandato all'attivazione effettiva dei moduli interessati |
| Compliance RUNTS | **non applicabile** | sostituito da nuovo modulo "Compliance RASD" |
| Bilancio DM39 | adattamento — **completato** | vedi §6.2: solo 1 FK aggiunta (`verbale_approvazione_id`), resto già adatto senza modifiche |
| Audit Log/Archivio | riuso diretto — **confermato** | vedi §6.6, §6.11; 1 soglia aggiunta in `gl_impostazioni` (conservazione 10 anni, art. 2220 c.c.); `gl_archivio_documenti`/`gl_attestazioni_tecniche`/`gl_backup_log` verificate, nessuna modifica |
| Comunicazioni | riuso diretto — **confermato** | vedi §6.5; unico punto operativo (non di schema): email tutore per minorenni senza email propria |
| Volontari | **adattamento — schema consegnato** | soglie 150€/400€, verbale autorizzazione, comunicazione RASD, autodichiarazione 15.000€, gap rimborsi annullati |
| Erogazioni/Collaboratori | non applicabile — **confermato** | nessun dipendente/collaboratore oggi; schema mantenuto invariato per evoluzione futura, nessuna verifica ulteriore necessaria finché resta inutilizzato |
| Scadenzario | riuso diretto — **confermato** | vedi §6.7; categorie mappate su affiliazione CSEN/bilancio, nessuna scadenza per-socio (certificati medici restano su report dedicato, §4.1) |
| — | **nuovo modulo (schema consegnato, non attivo ora)** | Tesseramento sportivo / Certificati medici |
| — | **nuovo modulo (leggero) — completato** | Compliance RASD / Affiliazione |
| — | **spostato in app separata (ArcoGare)** | Gestione gare |

## 4. Nuovi moduli — bozza di schema (da rifinire)

### 4.1 Tesseramento sportivo / Certificati medici

> **Aggiornamento v0.37.0**: le tessere CSEN sono in uso (pagina Tessere,
> scheda `tessere.md`): `gl_tesseramenti` ha tipo, pacchetto, scadenza a un
> anno dall'emissione e rinnovo; il vincolo (socio, stagione) non c'è più.
> Certificati, divisioni e specialità restano come descritto qui sotto.

**Non attivo nella configurazione attuale** (vedi §2.5 — nessun tesserato
agonista, nessuna gara di calendario ufficiale): schema **progettato e
consegnato** (migration + model + seeder, vedi archivio
`modulo-tesseramento-asd.zip` di questa sessione), pronto per un'eventuale
evoluzione futura. Da applicare al progetto (`php artisan migrate --seed`)
quando si deciderà di attivarlo, o comunque disponibile fin da subito.

**Anagrafica di base** (`gl_soci`, già esistente nel core): nessuna
modifica necessaria. La tessera associativa interna
(`numero_tessera`/`blocco_tessera_id`) resta un concetto separato dalla
tessera CSEN — non vanno confusi né uniti.

**Tabelle di lookup:**
- `gl_qualifiche`: `atleta_praticante`, `atleta_agonista`, `dirigente`,
  `tecnico` — combinabili liberamente (relazione molti-a-molti), a
  differenza del form CSEN che ne accetta una sola per tesseramento: verso
  CSEN si segue la loro codifica, internamente il gestionale resta più
  flessibile.
- `gl_divisioni_arco`: Olimpico/Ricurvo, Compound, Nudo/Barebow, Longbow,
  Istintivo (le "divisioni" FITARCO, cioè il tipo di arco).
- `gl_specialita_gara`: Targa, Campagna (Field), 3D (i formati di gara,
  asse indipendente dalla divisione).
- `gl_tecnici`: un tecnico può essere un socio già registrato (FK
  `socio_id`) oppure un nominativo esterno (`nome_esterno`) inserito a
  mano.

**Tabelle principali:**
- `gl_tesseramenti`: tesserato (FK → `gl_soci`), stagione sportiva, ente
  affiliante (default CSEN), numero tessera CSEN (cambia ogni stagione),
  date inserimento/rilascio, pacchetto tesseramento (stringa libera: es.
  "Base", "Silver Base", "Gold Base"), tecnico di riferimento (FK →
  `gl_tecnici`). Collegato a qualifiche/divisioni/specialità tramite 3
  pivot (`gl_tesseramenti_qualifiche`, `_divisioni`, `_specialita`).
- `gl_certificati_medici`: tesserato (FK → `gl_soci`), tesseramento
  collegato (nullable, non 1:1: un certificato biennale può coprire più
  stagioni), tipo (agonistico/non agonistico), data visita, scadenza,
  medico/struttura, stato idoneità, documento allegato.

**Scadenze**: niente iniezione in `gl_scadenzario` (pensato per scadenze
di livello associazione, non per-socio) — le scadenze di
tesseramento/certificato restano campi diretti sulle rispettive tabelle,
con report dedicato "in scadenza nei prossimi N giorni", sullo stesso
principio già usato per `gl_soci.quota_scadenza`.

### 4.2 Compliance RASD / Affiliazione — completato

**Schema progettato e consegnato** (archivio `modulo-rasd-asd.zip` di
questa sessione: 1 migration, 1 model).

- **Stato affiliazione (CSEN), numero/data iscrizione RASD, scadenza
  affiliazione**: già coperti da `gl_associazione` (campi `rasd_numero`,
  `rasd_data_iscrizione`, `rete_affiliazione_nome`/`_codice`/
  `_referente`/`_scadenza`), aggiunti nella revisione di completezza
  §6.12 — nessuna tabella separata necessaria, essendo un dato
  singleton.
- **Nuova tabella `gl_rasd_verifiche`**: traccia le verifiche
  sistematiche di Sport e Salute (dal 2026, incrocio Anagrafe
  Tributaria, 90 giorni per regolarizzare eventuali anomalie — §2.1).
  Evento ripetibile nel tempo, non uno stato — stesso pattern già usato
  per `gl_gdpr_violazioni`. Nessuna FK verso `gl_scadenzario`: la
  relativa riga di scadenza (categoria `rete_affiliazione`) andrà
  generata lato applicazione quando si aprirà una verifica, stesso
  principio già seguito per Bilancio e Tesseramento.

### 4.3 Gestione gare → spostato in app separata ("ArcoGare")

**Decisione architetturale**: Gestione gare **non sarà un modulo di
`gestionale-asd`**. Motivazioni, emerse discutendo l'ambito reale della
funzionalità:

1. **Atleti di altre compagnie**: le gare accolgono iscrizioni di
   arcieri che non sono soci della Compagnia Arcieri Castelviscardo — non
   possono vivere in `gl_soci`, che conosce solo i vostri soci.
2. **Multi-tenant fin da subito**: l'app dovrà supportare gare
   organizzate anche da altre compagnie, non solo la vostra.
3. **Uso offline il giorno di gara**: probabile necessità di
   funzionamento senza connettività affidabile sul campo di tiro (PWA con
   sync successivo) — incompatibile con l'architettura Laravel classica
   del gestionale.
4. **Utenti senza account**: giudici/segnapunti che inseriscono
   punteggi il giorno stesso, spesso senza essere utenti registrati nel
   gestionale (`gl_users`).
5. **"Anagrafe sportiva"** (storico partecipazioni/punteggi per
   atleta): per natura cross-club, va tenuta nell'app Gare stessa (unica
   fonte di verità), non duplicata in `gestionale-asd`.

**Nome provvisorio**: **ArcoGare** (repo/progetto Laravel indipendente,
da zero, non un fork del core — non serve Soci/Prima Nota/Governance).

**Ambito funzionale previsto** (gestione completa, non solo iscrizioni):
iscrizione → check-in il giorno di gara → definizione squadre →
registrazione punteggi per atleta/squadra → classifica finale.

**Collegamento con `gestionale-asd`**: nessuna FK diretta possibile
(database separati). Il raccordo tra un vostro socio e la sua eventuale
scheda atleta in ArcoGare avviene per **codice fiscale** (chiave
naturale presente su entrambe le anagrafiche), non per ID. Se in futuro
servirà mostrare nel gestionale i risultati sportivi dei propri soci,
sarà tramite chiamata API ad ArcoGare, non con una copia locale dei dati.

**Conseguenza su un placeholder già scritto**: il campo `evento_id →
gl_gare` previsto come "promemoria di follow-up" su
`gl_rimborsi_volontari` (§5, `MANUALE-ASD.md`) **non diventerà mai una
FK vera** dentro `gestionale-asd`, essendo `gl_gare` ora fuori da questo
database. `evento_nome`/`evento_data` restano quindi il collegamento
definitivo per ora; un eventuale `evento_id_esterno` (riferimento debole
all'ID in ArcoGare, senza vincolo FK) potrà essere aggiunto in futuro se
si costruirà un'integrazione via API tra le due app.

**Stato**: solo deciso l'ambito e l'architettura; schema dati di
ArcoGare non ancora progettato — sessione dedicata a parte, separata dal
lavoro su `gestionale-asd`.

## 5. Adattamenti al modulo Volontari

**Schema progettato e consegnato** (archivio `modulo-volontari-asd.zip`
di questa sessione: 4 migration, 2 model nuovi, 2 model aggiornati).

- **Assicurazione RCT (§2.2)**: già coperta dallo schema esistente
  (`gl_volontari.assicurazione_*`), nessuna modifica necessaria.
- **Tipologia di rimborso**: nuovo campo `tipo_rimborso`
  (`ordinario`/`forfettario`) su `gl_rimborsi_volontari`. L'ordinario
  (≤150€/mese) resta descritto dalle voci già esistenti in
  `gl_rimborsi_volontari_voci` (km/viaggio/vitto); il forfettario
  (≤400€/mese) aggiunge `evento_nome`/`evento_data` come **placeholder
  testuale**. Nota aggiornata: con la decisione di spostare Gestione
  gare in un'app separata (ArcoGare, vedi §4.3), questo campo **non
  diventerà una FK vera** — resta il collegamento definitivo, con
  eventuale `evento_id_esterno` in futuro per un'integrazione via API.
- **Autodichiarazione soglia 15.000€/anno**: nuova tabella
  `gl_volontari_autodichiarazioni` (volontario, anno, importo
  dichiarato, flag `sopra_soglia` calcolato in applicazione), unique
  per volontario/anno.
- **Comunicazione trimestrale al RASD**: nuova tabella
  `gl_rimborsi_rasd_invii`, stesso pattern già usato per
  `gl_volontari_sessioni` (vidimazione PEC); i rimborsi forfettari si
  agganciano con `rasd_invio_id`.
- **Delibera dell'organo sociale**: nuovo campo
  `verbale_autorizzazione_id` su `gl_volontari`, FK verso `gl_verbali`
  (tipo `cd_delibera`). Limite noto: si collega al verbale nel suo
  complesso, non alla singola delibera interna — il core non ha ancora
  `gl_delibere_cd` granulare (gap segnalato in `MANUALE-CORE.md` §6).
- **Gap `gs_rimborsi_annullati`**: risolto aggiungendo il valore
  `annullato` all'enum `stato` di `gl_rimborsi_volontari` (via
  `DB::statement`, MySQL non permette di estendere un enum con
  Blueprint), più `data_annullamento`/`motivo_annullamento` — non serve
  una tabella di log separata, dato che il record resta comunque nello
  storico (nessun soft delete su questa tabella).

## 6. Modulo Scontrini (registrazione da foto con riconoscimento OCR)

Funzionalità da preservare, individuata analizzando **`gestionale-acv`**,
il plugin WordPress con cui la **Compagnia (B.A.S.)** tiene oggi la
contabilità (tabelle `conta_*`/`doc_archivio`, non `gl_*`). Correzione del
06/10/2026: in precedenza era attribuito per errore all'«Area Riservata»
del Dante; il Dante usa il Gestionale APS Laravel, da cui vengono invece
giroconti, allegati multipli, anticipi e riconciliazione bancaria
(`prima-nota.md` §1, analisi nel progetto
`claude/analisi-contabilita-esistente.md`). Non è stato portato codice,
solo la logica di business, ridisegnata da zero per lo schema Laravel.
`gestionale-acv` va in pensione dal 01/01/2027 (migrati solo i saldi).

**Schema progettato e consegnato** (archivio `modulo-scontrini-asd.zip`
di questa sessione: 3 migration, 3 model — 2 nuovi + 1 aggiornato, 1
seeder).

- **Logica preservata dal sistema originale**: flusso mobile in due
  passaggi (foto → OCR best-effort di totale/data/ragione sociale →
  conferma sempre editabile), categoria segnaposto "da classificare" per
  la cattura rapida, quadratura bloccante quando lo split è abilitato,
  fornitore "trova o crea", motivazione obbligatoria + log per modifiche
  su anno chiuso.
- **`gl_impostazioni`** (nuova, chiave/valore): risolve anche il gap
  `gs_config` segnalato in `MANUALE-CORE.md` §6. Prima voce concreta:
  `scontrini_split_abilitato` (booleano, seed `true`) — se attivo, uno
  scontrino può avere più movimenti collegati con quadratura verificata;
  se disattivo, ogni scontrino genera sempre un solo movimento pari al
  totale (quadratura automatica per costruzione). Pensata per diventare
  una domanda del futuro installer/setup wizard (`MANUALE-CORE.md` §8).
- **`gl_prima_nota_scontrini`** (nuova): entità "scontrino" a sé, con
  `data_scontrino`, `importo_totale`, `soggetto_id` (FK → `gl_soggetti`,
  riuso diretto, nessuna modifica), dati OCR (`ocr_utilizzato`,
  `ocr_testo_grezzo`, `ragione_sociale_raw`), allegato, flag `quadrato`
  (ricalcolato da `PrimaNotaScontrino::ricalcolaQuadratura()` ad ogni
  variazione dei movimenti collegati).
- **`gl_prima_nota`**: nuova FK `scontrino_id` (nullable) → più
  movimenti possono condividere lo stesso scontrino.
  `giustificativo_path`/`giustificativo_nome` restano invariati per i
  movimenti inseriti a mano, senza passare dal flusso scontrino.
- **Anni sociali aperti in contemporanea**: dalla v0.35.0
  `gl_prima_nota_anni_chiusi` è sostituita da `gl_anni_contabili` (una riga
  per anno dal primo movimento, stato aperto/chiuso, saldi di apertura del
  primo anno, fotografia dei saldi alla chiusura, approvazione del
  rendiconto; `prima-nota.md`). Più anni aperti insieme restano possibili;
  si chiudono in ordine.
- **Audit trail su anno chiuso**: riuso diretto di `gl_audit_log`
  esistente (nessuna modifica al suo schema) — `entita_tipo`/`entita_id`
  per il movimento, `dettaglio` per la motivazione obbligatoria,
  `old_values`/`new_values` per il diff, hash chain già presente.
- **OCR**: confermato **OCR.space** (già funzionante in produzione nel
  sistema originale), da incapsulare in un service Laravel dedicato
  (`Http::` facade invece di cURL diretto) in fase di implementazione —
  decisione implementativa, non di schema, rimandata a quella fase.

### 6.1 Monitoraggio regime L. 398/1991 (plafond e tracciabilità)

**Nessuna nuova tabella necessaria** — lo schema esistente di
`gl_prima_nota`/`gl_prima_nota_categorie` copre già tutto il necessario,
solo non ancora sfruttato per questo scopo:

- **Distinzione proventi commerciali/istituzionali**: il campo
  `flag_non_istituzionale`, già presente su `gl_prima_nota_categorie`,
  è concettualmente identico alla distinzione richiesta dalla L.
  398/1991 — i proventi commerciali soggetti a plafond (sponsorizzazioni,
  bar sociale, gadget) sono le entrate da categorie non istituzionali; le
  quote associative e i contributi restano istituzionali, fuori plafond.
  Nessun campo nuovo: va solo valorizzato correttamente in fase di
  configurazione delle categorie reali.
- **Soglie configurabili** aggiunte a `gl_impostazioni` (seeder
  aggiornato): `regime_398_plafond_soglia` (400.000, integer),
  `regime_398_tracciabilita_soglia` (1.000, integer) — se la normativa
  cambia le soglie, si aggiorna un valore, non il codice.
- **Report plafond** (logica applicativa, fase UI): somma degli importi
  entrata con categoria `flag_non_istituzionale = true` per anno,
  confrontata con `regime_398_plafond_soglia`.
- **Alert tracciabilità** (validazione applicativa, fase UI): quando un
  movimento ha `conto = 'cassa'` e importo ≥
  `regime_398_tracciabilita_soglia`, segnalare l'anomalia — il campo
  `conto` esiste già su `gl_prima_nota`.
- **`codice_dm39`** su `gl_prima_nota_categorie` resta presente ma
  **non applicabile** per questa ASD (Bilancio DM39 non si applica,
  vedi §2.4) — non tolto dallo schema (riuso diretto della tabella),
  semplicemente ignorato in fase di configurazione categorie.

### 6.2 Adattamento modulo Bilancio

**Schema progettato e consegnato** (archivio `modulo-bilancio-asd.zip`
di questa sessione: 1 migration alter, 1 model aggiornato).

Buona notizia strutturale: `gl_bilancio`/`gl_bilancio_voci` era già
adatto a un rendiconto ASD senza modifiche sostanziali — voci libere
(non un modello ministeriale fisso, coerente col fatto che non ne esiste
uno obbligatorio), `tipo` preventivo/consuntivo già presente,
collegamento a `gl_prima_nota_categorie` che eredita gratuitamente la
distinzione istituzionale/commerciale (`flag_non_istituzionale`, vedi
§6.1). L'unica traccia di RUNTS è il commento `// codice DM 39/2020`
sul campo `codice` di `gl_bilancio_voci`: resta come campo generico non
tipizzato, semplicemente non popolato per questa ASD — stesso
trattamento già dato a `codice_dm39` su `gl_prima_nota_categorie`.

**Unico adattamento apportato**: l'art. 90 c.18 lett. d) L. 289/2002
(richiamato dall'art. 7 D.Lgs. 36/2021) richiede un "iter deliberativo
specifico" per l'approvazione del rendiconto. `gl_bilancio` aveva solo
`data_assemblea`/`numero_verbale` come **testo libero**, senza
collegamento reale a un verbale. Aggiunta la FK vera:

- `gl_bilancio` + `verbale_approvazione_id` (nullable → `gl_verbali`),
  stesso pattern già usato per `gl_volontari.verbale_autorizzazione_id`.

**Decisione esplicita presa con Paolo**: `data_assemblea`/
`numero_verbale` **non vengono rimossi** — restano come campi di
comodo/fallback in testo libero accanto alla nuova FK, che diventa però
la fonte di verità per i collegamenti reali (scelta di prudenza/minima
invasività su campi già esistenti, non di pulizia dello schema).

**Verificato, nessuna modifica necessaria**: il raccordo cassa/banca a
inizio/fine esercizio (altro requisito del rendiconto) **non richiede**
modifiche a `gl_cassa_chiusure`, che serve a uno scopo diverso (verifica
mensile cassa fisica vs Prima Nota, tipo controllo antifrode). Il
raccordo annuale è un semplice calcolo (somma entrate meno uscite per
`conto`, fino a una data) su `gl_prima_nota`, non salvato da nessuna
parte — stesso principio già applicato a plafond e quadratura (§6, §6.1).

**Termine di approvazione (4-6 mesi)**: nessuna nuova tabella — si
presta bene a un utilizzo di `gl_scadenzario` esistente (categoria
`contabile`, già prevista nell'enum), generabile lato applicazione ad
ogni apertura di un nuovo bilancio consuntivo. Non ancora implementato
(fase applicativa, non schema).

### 6.3 Adattamento modulo Governance CD

**Schema progettato e consegnato** (archivio `modulo-governance-asd.zip`
di questa sessione: 1 migration alter, 1 model aggiornato, aggiornata
anche `Socio.php`).

- **Eliminata la ridondanza anagrafica** (decisione esplicita): `gl_governance_cd`
  aveva una propria anagrafica (cognome, nome, data di nascita, codice
  fiscale, email, telefono), separata da `gl_soci` — per una compagnia
  dove i membri del CD sono sempre anche soci, dati duplicati con rischio
  di disallineamento. Aggiunta `socio_id` (FK **obbligatoria** →
  `gl_soci`), rimossi i 7 campi anagrafici duplicati.
  Nota tecnica: obbligatoria anziché nullable perché la tabella è oggi
  vuota (associazione non ancora costituita) — se in futuro emergesse
  un'eccezione (es. un revisore esterno non socio, da chiarire in sede di
  stesura statuto), servirebbe una migration separata per renderla
  nullable.
- **Verbale di nomina**: aggiunta `verbale_nomina_id` (FK nullable →
  `gl_verbali`), stessa convenzione già usata per
  `gl_bilancio.verbale_approvazione_id` e
  `gl_volontari.verbale_autorizzazione_id`. `delibera_assemblea`/
  `delibera_data` (esistenti) restano come fallback testuale, stessa
  decisione presa per Bilancio.
- **Incompatibilità art. 11** (vedi §2.7): aggiunti
  `dichiarazione_incompatibilita` (testo libero) e
  `dichiarazione_incompatibilita_data`, generici per ogni carica (non
  vincolati al solo Presidente nello schema) — la norma è già cambiata
  due volte, la logica applicativa decide chi deve dichiararlo in base a
  `carica` e alla normativa vigente al momento, senza richiedere
  modifiche allo schema se cambierà ancora.
- **Nuova relazione su `Socio.php`**: `caricheCd()` (hasMany, non hasOne
  — un socio può ricoprire cariche CD in mandati diversi nel tempo).

### 6.4 GDPR — confermato: nessuna modifica necessaria

Analizzate tutte e 9 le tabelle GDPR del core
(`gl_gdpr_trattamenti`, `_documenti`, `_incaricati`, `_responsabili`,
`_responsabili_docs`, `_richieste`, `_violazioni`, `_log_accessi`,
`_registro_firmato`). Conclusione: **riuso diretto**, nessuna modifica
allo schema.

- `gl_gdpr_trattamenti` (registro trattamenti ex art. 30) è già
  completamente generico — campi testuali liberi (`categorie_speciali`,
  `categorie_interessati`, `categorie_dati`). Censire un nuovo
  trattamento significa inserire una riga, non modificare lo schema.
- **Dati minori**: già coperti a livello di `gl_soci` (campi tutore,
  consenso privacy con data/IP/versione/metodo) — non un gap GDPR a sé,
  verificato quando l'anagrafica soci è stata confermata "riuso diretto".
- **Dati sanitari (certificati medici)**: il trattamento andrà censito
  in `gl_gdpr_trattamenti` **solo quando** il modulo Tesseramento verrà
  attivato (oggi non attivo, §2.5) — un registro trattamenti deve
  riflettere l'attività reale, non quella potenziale. Promemoria già
  incluso nella scheda tecnica `tesseramento-sportivo.md` §5.
- **CSEN**: l'invio dati per l'affiliazione **non richiede** un accordo
  responsabile del trattamento (`gl_gdpr_responsabili`) — CSEN riceve i
  dati come titolare autonomo per le proprie finalità federative, non
  come soggetto che tratta per conto dell'associazione.
- **ArcoGare** (futuro): se si costruirà un'integrazione via API, andrà
  valutato se ArcoGare agisce da titolare autonomo o da responsabile del
  trattamento — prematuro deciderlo ora, il progetto non è ancora
  disegnato.

### 6.5 Comunicazioni — confermato: nessuna modifica necessaria

Analizzati entrambi i sottosistemi: protocollo lettere formale
(`gl_lettere`, `gl_lettere_invii`) e circolari a gruppi di soci
(`gl_comunicazioni`, `gl_comunicazioni_allegati`,
`gl_comunicazioni_destinatari`). Conclusione: **riuso diretto**, nessuna
modifica allo schema.

- `gl_lettere` è già abbastanza generico per la corrispondenza formale
  con CSEN (tipo `uscita`, snapshot immutabile del destinatario) — non
  serve un tipo dedicato.
- Nessuna sovrapposizione con `gl_rimborsi_rasd_invii` (comunicazione
  trimestrale RASD, modulo Volontari §5): concettualmente diverse
  (compliance esterna con vincolo anno/trimestre vs comunicazione
  interna ai soci), correttamente tenute separate.
- **Unico punto operativo, non di schema**: `gl_comunicazioni_destinatari.email`
  è un campo libero — per un socio minorenne senza email propria, la
  logica applicativa (fase UI, non ancora scritta) dovrà usare
  `gl_soci.tutore_email` invece dell'email del socio. La colonna è già
  abbastanza generica da contenere l'uno o l'altro indirizzo, nessuna
  migration necessaria.

### 6.6 Audit Log — confermato: nessuna modifica allo schema, 1 soglia configurabile

Analizzate tutte e 3 le tabelle (`gl_audit_log`, `gl_audit_log_integrity`,
`gl_audit_archivi`), già riusate con successo in due moduli precedenti
di questa sessione (Scontrini §6, Volontari §5) per la motivazione
obbligatoria su modifiche ad anno chiuso. Conclusione: **schema
invariato**, generico e adeguato.

- **Punto verificato specificamente**: `gl_audit_archivi` esiste per
  archiviare/eliminare righe di `gl_audit_log` più vecchie di una
  soglia, non ancora codificata. Rilevante perché l'audit log traccia
  anche modifiche a Prima Nota/Bilancio, soggette all'**art. 2220 c.c.**
  (conservazione scritture contabili **10 anni**) — una soglia più
  breve (es. i 5 anni della conservazione rendiconto, §2.4) rischierebbe
  di eliminare tracciabilità ancora obbligatoria.
- **Unica aggiunta**: nuova voce in `gl_impostazioni` (seeder
  aggiornato), `audit_log_conservazione_anni` = 10 (integer) — nessuna
  modifica alle tabelle di audit stesse, solo una soglia configurabile
  per quando la logica di archiviazione verrà effettivamente
  implementata (non ancora scritta).

### 6.7 Scadenzario — confermato: nessuna modifica necessaria

Analizzata la tabella `gl_scadenzario`, incluso un aspetto non ancora
controllato in altri moduli: la ricorrenza. Conclusione: **riuso
diretto**, nessuna modifica allo schema.

- **Mappatura categorie esistenti sui bisogni ASD**: `rete_affiliazione`
  → rinnovo affiliazione CSEN (§2.1, già segnalata come scadenza
  critica); `contabile` → termine di approvazione bilancio 4-6 mesi
  (§6.2). `runts` resta nell'enum ma non applicabile a questa ASD —
  innocuo se semplicemente non usato, stesso trattamento già riservato
  a `codice_dm39` altrove.
- **Limite di ricorrenza verificato**: `giorno_ricorrenza`/
  `mese_ricorrenza` sono campi singoli, quindi lo schema supporta solo
  ricorrenze **annuali**, non infrannuali (es. trimestrali). Verificato
  che questo non è un problema pratico: l'unica scadenza
  potenzialmente trimestrale (comunicazione RASD rimborsi volontari) ha
  già una tabella dedicata (`gl_rimborsi_rasd_invii`, §5), costruita
  apposta con vincolo `anno`+`trimestre` — non deve passare da
  `gl_scadenzario`.
- **Nessuna riga di scadenza inserita ora**: coerentemente con
  l'approccio già seguito per il registro trattamenti GDPR, i dati reali
  (es. "Rinnovo affiliazione CSEN") andranno inseriti quando
  l'associazione esisterà realmente (1/1/2028), non ora.

- **Aggiornamento v0.38.0 (11/10/2026)**: lo Scadenzario diventa una
  pagina (`scadenzario.md`, tavole SC-1…SC-3, PR-3). Le scadenze
  dell'associazione nascono subito, perché la B.A.S. è già affiliata e
  assicurata: Affiliazione CSEN (31/12), Polizza RC (luglio), Approvazione
  del rendiconto (30/04, statuto art. 22.2). Schema esteso: `chiave`,
  categoria di Prima Nota per i pagamenti («Rinnovata» registra l'uscita e
  sposta la data all'anno dopo), storico `gl_scadenzario_rinnovi`, categoria
  `assicurazione`; dati della polizza RC e contatto del referente EPS in
  `gl_associazione`. La scadenza dell'affiliazione in
  `gl_associazione.rete_affiliazione_scadenza` è una copia tenuta dallo
  Scadenzario. Promemoria «N giorni prima» a Presidente e Tesoriere.

### 6.8 Rinnovi quota — confermato: nessuna modifica necessaria

Analizzata `gl_rinnovi`. Conclusione: **riuso diretto**, nessuna
modifica allo schema.

- **Tracciabilità 398/91 (§6.1)**: già coperta automaticamente tramite
  la relazione esistente `Rinnovo::movimentoPrimaNota()` (FK
  `rinnovo_id` su `gl_prima_nota`) — un rinnovo incassato in contanti
  sopra soglia viene intercettato dall'alert tracciabilità già
  progettato per Prima Nota in generale, senza duplicare la logica qui.
- **Esenzione IVA quote associative** (dal 1/1/2026, §2.3): nessun campo
  nuovo necessario — riguarda tutte le quote indistintamente, non serve
  tracciarla riga per riga. Unico impatto: la dicitura stampata in
  ricevuta (esenzione IVA invece di "operazione esclusa") è un dettaglio
  di template PDF, non di schema — nota per la fase di implementazione
  UI.

### 6.9 Progetti — confermato: nessuna modifica necessaria

Analizzate `gl_progetti`/`gl_progetti_preventivo`. Conclusione: **riuso
diretto**, nessuna modifica allo schema.

- Modulo utile per qualunque iniziativa finanziata a progetto (es. un
  bando sportivo, un progetto scuola-sport), a prescindere dalla natura
  APS/ASD dell'associazione — nessuna specificità del contesto ASD
  richiede un adattamento.
- **`codice_dm39`** (su `gl_progetti_preventivo`) e **`codice_documento`**
  (su `gl_progetti`): residui RUNTS, non applicabili a questa ASD.
  Restano nello schema senza essere popolati, stesso trattamento già
  riservato a campi analoghi in Bilancio e categorie Prima Nota.

### 6.10 Domande/Iscrizione — adattamento (1 migration)

**Schema progettato e consegnato** (archivio `modulo-domande-asd.zip`
di questa sessione: 1 migration alter, nessun model da aggiornare).

- **Problema reale individuato**: `gl_domande.tipo_socio` aveva un enum
  a 5 valori (`ordinario`, `ordinario_rivista`, `giovane`, `minorenne`,
  `vitalizio`), disallineato dall'enum a 3 valori di `gl_soci.tipo`
  (`ordinario`, `giovane`, `minorenne`, già confermato riuso diretto).
  Una domanda approvata con `ordinario_rivista` o `vitalizio` non
  avrebbe avuto un valore corrispondente al momento della
  trasformazione in socio.
- `ordinario_rivista` è un residuo specifico del Dante (abbonamento alla
  rivista sociale), mai generalizzato durante l'estrazione del core.
  `vitalizio` è stato valutato e **escluso esplicitamente** per questa
  ASD (decisione presa con Paolo).
- **Correzione**: enum `tipo_socio` allineato a `gl_soci.tipo`, via
  `DB::statement` (come già fatto per l'enum `stato` di
  `gl_rimborsi_volontari` — MySQL non permette di modificare un enum
  tramite Blueprint).
- **Stato della domanda e voto CD** (`gl_domande.stato`, `gl_voti_cd`):
  machinery per un iter di ammissione con voto online per singolo
  consigliere (token/link), pensata per un CD più numeroso — valutata e
  **lasciata invariata** su richiesta esplicita: per una compagnia
  piccola resta utilizzabile anche solo percorrendo un sottoinsieme
  degli stati (es. direttamente da `in_attesa` ad `approvata`, senza
  usare il voto online).

### 6.11 Verifica di completezza — tabelle non ancora controllate

Su richiesta esplicita ("è rimasto fuori qualcosa? come possiamo
verificare?"), incrociato l'elenco tabelle per modulo di
`MANUALE-CORE.md` §5 con i file effettivamente esaminati in questa
sessione. Trovate 11 tabelle mai verificate direttamente, ora tutte
controllate:

- **Confermate riuso diretto, nessuna modifica**: `gl_destinatari`,
  `gl_ruoli`, `gl_users` (concetto distinto dalle cariche di
  `gl_governance_cd` — autenticazione software vs cariche associative,
  nessuna sovrapposizione), `gl_tessere_blocchi`, `gl_assemblee`,
  `gl_assemblee_rsvp`, `gl_archivio_documenti`, `gl_attestazioni_tecniche`,
  `gl_backup_log`.
- **`gl_email_log`**: verificato `domanda_id` nullable — la tabella non
  è limitata al flusso domande, può già loggare qualunque email.
  Confermata adeguata.
- **`gl_associazione`**: **problema reale trovato**, vedi §6.12.

### 6.12 Adattamento `gl_associazione`

> Dalla v0.32.0 forma giuridica, denominazione, sede, regime fiscale, RASD
> e testi in vigore stanno nei periodi dell'ente (`gl_associazione_periodi`,
> `docs/profilo-ente.md`); in `gl_associazione` restano i dati fissi
> (codice fiscale, recapiti, banca). Le colonne `forma_giuridica` e
> `rasd_*` di `gl_associazione` non si leggono più.

**Schema progettato e consegnato** (archivio
`modulo-associazione-asd.zip` di questa sessione: 1 migration alter,
1 model aggiornato).

- **`forma_giuridica`** aveva default `'Associazione di Promozione
  Sociale'` — residuo diretto del Dante mai generalizzato, sbagliato
  per un'ASD. Default colonna corretto in `'Associazione Sportiva
  Dilettantistica'` (via `DB::statement`, MySQL non permette di
  modificare un default enum/stringa tramite Blueprint in questo caso
  specifico di `ALTER ... SET DEFAULT`).
- **Mancavano i campi RASD**: presenti solo `runts_numero`/
  `runts_data_iscrizione`/`runts_sezione` (non applicabili a questa
  ASD, lasciati invariati), ma nessun equivalente per il RASD — l'unico
  registro rilevante (§2.1). Aggiunti `rasd_numero`,
  `rasd_data_iscrizione`.
- **Mancava la scadenza affiliazione**: `rete_affiliazione_nome`/
  `_codice`/`_referente` già generalizzati e riusabili invariati per
  CSEN, ma nessun campo data. Aggiunto
  `rete_affiliazione_scadenza` — rilevante perché l'affiliazione è una
  scadenza critica (§2.1: senza affiliazione valida, l'iscrizione RASD
  decade).
- **Nota per la fase di configurazione dati reali**: `AssociazioneSeeder.php`
  (non esaminato in questa sessione) probabilmente imposta
  `forma_giuridica` con un valore segnasposto esplicito — da correggere
  manualmente quando si inseriranno i dati reali dell'associazione,
  indipendentemente dal nuovo default a livello di colonna (che copre
  solo il caso in cui il campo non venga specificato esplicitamente).

### 6.13 Sistema permessi (per sezione intera)

**Schema progettato e consegnato** (archivio `modulo-permessi-asd.zip`
di questa sessione: 2 migration, 2 model — 1 nuovo + 1 aggiornato, 1
seeder). Colma il gap più importante segnalato in `MANUALE-CORE.md`
§6/§8: `gl_ruoli` era solo un'etichetta collegata a `gl_users.ruolo`,
senza alcun meccanismo che stabilisse cosa un ruolo potesse
effettivamente vedere o fare.

**Granularità scelta**: a **sezione intera** (non per singola funzione
dentro ogni sezione) — decisione esplicita, adeguata alle dimensioni
della compagnia e ai 4 ruoli già seedati (presidente, segretario,
tesoriere, revisore).

- `gl_sezioni`: 20 sezioni, una per ogni modulo applicativo mappato in
  questa sessione (Associazione, Soci, Domande, Rinnovi quota, Prima
  Nota, Bilancio, Progetti, Governance CD, Assemblee, Verbali,
  Volontari, Tesseramento sportivo, Compliance RASD, Comunicazioni,
  Archivio documenti, GDPR, Scadenzario, Audit Log, Impostazioni,
  Erogazioni/Collaboratori — quest'ultima dormiente). Associazione e
  Archivio aggiunte in una revisione successiva della matrice; la
  gestione Utenti/Ruoli ricade in Impostazioni.
- `gl_ruoli_sezioni`: pivot ruolo↔sezione (FK `ruolo_codice` →
  `gl_ruoli.codice`, coerente con la convenzione già usata da
  `gl_users.ruolo`), con un unico flag aggiuntivo `sola_lettura` — non
  una granularità per-funzione, solo l'eccezione del Revisore sulle
  sezioni contabili.
- **Matrice permessi concordata e seedata**:
  - Sezioni amministrative (Associazione, Soci, Domande, Governance CD,
    Assemblee, Verbali, Comunicazioni, Archivio documenti, GDPR,
    Compliance RASD, Volontari, Tesseramento, Scadenzario): Presidente +
    Segretario, accesso pieno. Su Volontari anche il Tesoriere, in sola
    lettura (rimborsi da pagare/registrare).
  - Sezioni contabili (Prima Nota, Rinnovi quota, Bilancio, Progetti):
    Presidente + Tesoriere accesso pieno, Revisore in sola lettura.
  - Audit Log: Presidente accesso pieno, Revisore in sola lettura.
  - Impostazioni (inclusa gestione Utenti/Ruoli): solo Presidente.
  - Erogazioni/Collaboratori: nessun ruolo assegnato (sezione
    dormiente).
- `$sezione->accessibileDaRuolo()` (metodo d'istanza): helper per il
  controllo di accesso, da richiamare nei controller quando verranno
  scritti (fase UI, non ancora implementata). Non distingue la
  modalità: per viste e componenti si usano gli helper su `User`
  (`puoAccedere()`, `puoModificare()`, `soloLettura()`, v0.18.0), che
  leggono anche il flag `sola_lettura` con una sola query per richiesta.

## 7. Correzioni da riportare su `MANUALE-CORE.md`

Le modifiche da riportare sul **Gestionale APS** sono tutte in un
documento a parte, con una casella da spuntare per ciascuna:
`docs/da-riportare-gestionale-aps.md`. Qui restano quelle per il core.

- La data di entrata in vigore dell'esenzione IVA per quote associative e
  corrispettivi specifici è il **1° gennaio 2026**, non il 2025 — questa
  correzione vale per qualunque derivato APS/ASD, non solo per questo, e
  andrebbe quindi propagata anche alla documentazione del core.
- Il problema "Permission denied" su `composer install`/`update`
  documentato nel core come causato da Avast **non è la spiegazione
  completa**: su questo derivato la causa scatenante finale era il
  download parallelo di Composer, risolto con
  `COMPOSER_MAX_PARALLEL_HTTP=1` (vedi §0.1). Andrebbe aggiornata la nota
  nel manuale del core, che oggi indica solo la disattivazione di Avast.
- Il gap `gs_config` (§6 di `MANUALE-CORE.md`) **va colmato**: qui
  reintrodotto come `gl_impostazioni`, con la prima esigenza concreta
  (`scontrini_split_abilitato`) che ne dimostra l'utilità anche in
  Laravel — da valutare se riportare la stessa tabella nel core stesso,
  non solo in questo derivato.
- **Il core non ha uno strato web**: `routes/web.php` contiene solo la
  rotta di benvenuto, senza controller, login né viste. Va detto
  esplicitamente nel manuale del core, perché chi ne deriva un progetto
  deve costruirlo da zero (qui: scheda `auth.md`). Da valutare se
  riportare nel core il login, i middleware `attivo`/`permesso:` e il
  layout di base, che sono indipendenti dal tipo di associazione.
- **Il core non scrive nell'audit log**: ha model e tabelle, ma non
  observer né servizi (`app/Services`, `app/Observers` non portati dal
  Gestionale APS). Qui ripresi e corretti (scheda `audit-log.md` §8): da
  riportare nel core, perché indipendenti dal tipo di associazione.
- **Consenso privacy senza rifare la domanda di iscrizione**: nel core
  e nel Gestionale APS manca una pagina per raccogliere (o rinnovare) il
  consenso di un socio già iscritto. Qui è realizzata nella v0.33.0
  (`privacy-link.md`, link personale via email, con foto e video): da
  riportare nel core (per il Gestionale APS: `da-riportare-gestionale-aps.md`).
- **`gl_audit_log.created_at` in TIMESTAMP** (v0.34.0): la catena
  dipende dal fuso orario di MySQL; qui portata a DATETIME (migration
  `2026_10_06_000093`, `audit-log.md` §9). Da correggere anche nel core,
  prima che un derivato vada in produzione.
- **`AssociazioneSeeder` del core**: dati segnaposto di un'APS
  (`runts_sezione = 'APS'`, forma giuridica APS) e soprattutto
  `updateOrCreate`, che a ogni `db:seed` sovrascriverebbe i dati reali
  dell'associazione. Qui corretto con `firstOrCreate` e i dati noti della
  Compagnia; da correggere anche nel core.
- **Default di `gl_soci.anno_iscrizione`**: la migration del core usa
  `->default(date('Y'))`, valutato una volta sola quando la migration
  gira, quindi il default resta fisso a quell'anno. Qui corretto con un
  hook `creating` in `Socio.php` (vedi scheda `soci.md` §4); la stessa
  correzione va riportata nel core. Vale la pena cercare lo stesso schema
  (`date(...)` o `now()` come default in una migration) anche nelle altre
  tabelle del core.

## 8. Prossimi passi

1. ~~Verificare sul regolamento tesseramento CSEN in vigore le regole
   specifiche per il tiro con l'arco~~ — **fatto**, vedi §2.5: nessuna
   contraddizione con l'esenzione generale, ma da reperire il Regolamento
   Tecnico di Settore CSEN Tiro con l'Arco in sede di affiliazione.
2. ~~Valutare se serve una checklist/modulo per la redazione dell'atto
   costitutivo/statuto conforme agli artt. 7-8 D.Lgs. 36/2021~~ —
   **deciso**: resta **fuori dal perimetro del gestionale**, la redazione
   dello statuto sarà affrontata separatamente (notaio/commercialista).
   Il gestionale interverrà solo a valle, archiviando il documento finale
   firmato nel modulo Archivio (`gl_archivio_documenti`, collegato a
   `gl_associazione`).
   **Rivisto il 27/09/2026** (punto 16): lo statuto è stato redatto in
   sessione prima di proseguire con il modulo Domande, perché diverse
   regole del gestionale ne dipendono. Notaio o commercialista restano per
   la revisione finale e l'adozione.
3. ~~Progettare nel dettaglio lo schema di `gl_tesseramenti` e
   `gl_certificati_medici`~~ — **fatto**, vedi §4.1: schema completo
   (9 migration, 6 model, 3 seeder) consegnato e integrato in
   `DatabaseSeeder.php`/`Socio.php`. Resta comunque "non attivo" nella
   configurazione attuale (§2.5).
4. ~~Progettare l'adattamento del modulo Volontari~~ — **fatto**, vedi §5:
   schema completo (4 migration, 4 model) consegnato.
5. ~~Progettare il modulo Gestione gare~~ — **deciso**, vedi §4.3:
   spostato in un'app Laravel separata e indipendente, nome provvisorio
   **ArcoGare** (multi-tenant, propria anagrafica atleti, nessuna FK con
   `gestionale-asd`). Schema dati di ArcoGare non ancora progettato —
   sessione a parte.
6. ~~Aprire il repo `gestionale-asd` da template~~ — **fatto**, vedi §0.

**Piano concordato per proseguire** (ordine scelto: prima tutta la base
dati del derivato, poi permessi, poi UI — vedi discussione in sessione):

7. ~~Rivedere/adattare Prima Nota/contabilità~~ — **fatto**, sia per
   Scontrini (§6) sia per il monitoraggio plafond/tracciabilità 398/91
   (§6.1, nessuna nuova tabella, solo 2 voci in `gl_impostazioni`).
   ~~Bilancio~~ — **fatto** (§6.2: solo 1 FK aggiunta).
   ~~Governance CD~~ — **fatto** (§6.3: eliminata ridondanza anagrafica,
   verbale nomina, incompatibilità art. 11). Assemblee/Verbali confermati
   riuso diretto, nessuna modifica necessaria.
   ~~GDPR~~ — **fatto** (§6.4: confermato riuso diretto, nessuna
   modifica, censimento trattamenti rimandato all'attivazione effettiva).
   ~~Comunicazioni~~ — **fatto** (§6.5: confermato riuso diretto,
   nessuna modifica, unico punto operativo per email tutore minorenni).
   ~~Audit Log~~ — **fatto** (§6.6: confermato riuso diretto, aggiunta
   1 soglia di conservazione in `gl_impostazioni`).
   ~~Scadenzario~~ — **fatto** (§6.7: confermato riuso diretto, categorie
   già adatte, limite ricorrenza annuale verificato non problematico).
   ~~Rinnovi quota~~ — **fatto** (§6.8: confermato riuso diretto,
   tracciabilità già coperta via `gl_prima_nota`).
   ~~Progetti~~ — **fatto** (§6.9: confermato riuso diretto).
   ~~Domande/Iscrizione~~ — **fatto** (§6.10: allineato enum
   `tipo_socio`, 1 migration). ~~Erogazioni/Collaboratori~~ —
   **confermato non applicabile**, nessuna ulteriore verifica
   necessaria finché resta inutilizzato.
   **Revisione della base dati core completata su tutti i moduli.**
   ~~Verifica di completezza (tabelle mai controllate direttamente)~~ —
   **fatto** (§6.11): trovate e verificate 11 tabelle rimaste scoperte
   dalle revisioni modulo per modulo. Un problema reale trovato e
   risolto: **`gl_associazione`** (§6.12 — default `forma_giuridica`
   sbagliato, mancavano campi RASD e scadenza affiliazione).
8. ~~Progettare il modulo leggero Compliance RASD/Affiliazione~~ —
   **fatto** (§4.2: nuova tabella `gl_rasd_verifiche`, resto già coperto
   da `gl_associazione`, §6.12).
   **Base dati del derivato ASD completa su tutta la linea**: tutti i
   moduli core rivisti, tutti i moduli nuovi progettati, verifica di
   completezza incrociata eseguita.
9. ~~Sistema permessi reale~~ — **fatto** (§6.13: `gl_sezioni` +
   `gl_ruoli_sezioni`, granularità a sezione intera come concordato, 20
   sezioni mappate, matrice permessi seedata per i 4 ruoli). Prossimo
   passo naturale: controller e viste (UI), che dovranno effettivamente
   usare `$sezione->accessibileDaRuolo()` per applicare questi permessi.
10. In parallelo, quando si vorrà: avviare la progettazione di ArcoGare
    come progetto a sé.
11. ~~Definire identità visiva e fondamenta UI~~ — **fatto** (scheda
    `ui.md`, v0.17.0): linguaggio visivo ripreso dal Gestionale APS ma
    ricostruito su token Tailwind 4 e classi condivise, con la regola
    "niente `style=` e niente `<style>` nelle viste"; palette derivata
    dal logo della Compagnia; font ospitati in locale; Alpine via npm.
    Riferimento visivo sulla tela di design "Gestionale ASD — Riferimento
    UI".
12. ~~Primo strato web~~ — **fatto** (scheda `auth.md`, v0.19.0): login
    ripreso dal Gestionale APS con blocco dei tentativi e rifiuto degli
    utenti disattivati, middleware `attivo` e `permesso:`, layout con
    sidebar che mostra solo le voci con rotta esistente e sezione
    accessibile.
13. ~~Componenti Blade e modulo Soci~~ — **fatto** (v0.20.0, schede
    `ui.md` e `soci.md`): componenti condivisi, mappa stati → badge in
    `config/stati.php`, elenco/scheda/inserimento/modifica di Soci con
    cessazione, riattivazione, eliminazione e ripristino. Aperti:
    documenti e privacy del socio.
    **v0.21.0**: audit log attivo (observer, servizio unico di scrittura,
    matrice di rischio, verifica della catena), scheda `audit-log.md` §8.
    **v0.22.0**: documenti per l'iscrizione e consenso privacy (in
    presenza o cartaceo); certificato medico previsto ma spento
    (impostazione `certificato_medico_richiesto`).
    **v0.23.0**: libro soci in PDF (DomPDF), colonna Documenti in elenco,
    esportazioni registrate nell'audit log. Sezione Soci completa:
    prossimo passo il primo deploy in produzione.
    **v0.25.0**: anteprima dei documenti in una nuova scheda.
15. ~~Primo deploy~~ — **fatto** (27/09/2026, scheda `deploy.md`): ambiente
    di collaudo su `arco.valconi.it` (SupportHost, account `uiynixlg`),
    in attesa di dominio e provider definitivi. Solo dati di prova.
    Emersi e risolti durante il rilascio: gestore PHP del cPanel
    nell'`.htaccess`, due PHP distinti per sito e Terminale (sito portato
    su `alt-php83`), estensioni `fileinfo` e `intl` mancanti nel PHP del
    sito.
14. **Consenso privacy online** (deciso, da realizzare con il modulo
    Domande): canale principale del consenso. Per i nuovi soci arriva con la
    domanda di iscrizione online; per i soci senza domanda, con un link
    personale via email. Prerequisiti: SMTP configurato e testo
    dell'informativa della Compagnia (dettagli in `soci.md`).
    Dopo il collaudo: primo deploy in produzione (deciso: alla sezione
    Soci completa).
    **v0.26.0** (27/09/2026): analisi della domanda online sui moduli CSEN
    e bozza dell'informativa privacy (`informativa-privacy-v1.0.md`);
    decisioni nella scheda `domande-iscrizione.md`.
    **v0.27.0** (28/09/2026): modulo pubblico della domanda online in due
    passi (dati, controlla e invia), conferma via email, ricevuta PDF,
    avviso alla segreteria. Da fare: consultazione dei consiglieri alla
    conferma, comando giornaliero (esito a 5 giorni, sollecito, decadenza,
    cancellazioni), area riservata delle domande, link personale per il
    consenso dei soci già iscritti. Prima di aprire le iscrizioni vere:
    segnaposto dell'informativa completati, statuto pubblicato in
    `resources/content/statuto/1.0.md`, dominio e provider definitivi con
    DPA.
    **v0.30.0** (29/09/2026): voto del CD e area riservata delle domande
    (`domande-iscrizione.md` §3). Processo semplificato su decisione di
    Paolo: domanda valida con l'invio (niente conferma via email), voto
    dei consiglieri dal link personale, esito a 5 giorni (accolta d'ufficio
    senza quorum, maggioranza dei voti espressi, parità al Presidente),
    chiusura anticipata solo quando hanno votato tutti (il Presidente
    interviene solo sulla parità e sul diniego),
    diniego scritto dal Presidente, email di esito ai consiglieri, bozza
    di verbale, comando orario `domande:scadenze` (serve il cron),
    iscrizione nel libro soci con rinnovo e Prima Nota, promozione di
    ottobre–dicembre, email nello stile del sito. Da fare: adottare le
    modifiche allo statuto (`statuto/statuto-modifiche-v1.1.md`), link personale
    per il consenso privacy dei soci senza domanda online.
    **v0.30.3** (30/09/2026): collaudo A–F concluso.
    **v0.30.4** (04/10/2026): il testo pubblicato dello statuto (pagina
    `/statuto`, numerato 1.0 fino all'apertura) è ora la bozza 1.1
    completa, con le modifiche della v0.30.0 e l'art. 6.6 a 5 giorni
    (versamento disposto); bozza 1.1 aggiornata. Prossimi: assenso del
    minore dai 14 anni nel modulo (art. 6.3), link per il consenso privacy.
    **v0.30.5** (04/10/2026): gestionale in ora italiana (`config/app.php`
    aveva `'UTC'` fisso e ignorava `APP_TIMEZONE`).
    **v0.31.0** (04/10/2026): assenso del minore dai 14 anni al
    tesseramento (art. 16 D.Lgs. 36/2021, statuto art. 6.3) nel modulo,
    con ricevuta, scheda e verbale; riferimento normativo sempre in
    piccolo. Analisi: la soglia di legge è 14 (non 12); la privacy non
    richiede un consenso del minore (art. 2-quinquies solo per i servizi
    online). Da fare: assenso anche ai rinnovi; link per il consenso
    privacy dei soci senza domanda.
    **05/10/2026**: avvio con la B.A.S. dal 01/01/2027, A.S.D. dal
    01/01/2028; passaggio come profilo dell'ente per periodi
    (`profilo-ente.md`); la B.A.S. adotta lo statuto rivisto (testo B.A.S.
    in `statuto/statuto-bas-bozza.md`), pubblicato come 1.0 (v0.31.1); è
    l'unico testo dello statuto da mantenere (documenti in `docs/statuto/`).
    **v0.33.0** (05/10/2026): link personale per l'informativa privacy
    (`privacy-link.md`): invio dalla scheda e in blocco dall'elenco Soci,
    al genitore per i minori, monouso e senza scadenza, valido anche per le
    nuove versioni dell'informativa; consenso a foto e video nella scheda
    del socio (copiato dalla domanda, revoca registrata dalla segreteria).
    **v0.34.0** (06/10/2026): sezione Audit Log ripresa dal Gestionale
    APS (`audit-log.md` §9): elenco con filtri e valori prima/dopo,
    esportazioni, verifica della catena, certificato annuale, attestazioni
    tecniche, verifica automatica mensile con avviso al Presidente; colonna
    `created_at` del registro in DATETIME (indipendente dal fuso di MySQL).
    **v0.34.1** (06/10/2026): correzioni dal collaudo (numero di riga,
    righe per pagina, una riga aperta alla volta, storico dei certificati
    senza scorrimento, finestre che stanno nello schermo, controllo della
    firma digitale sull'attestazione). **v0.34.2**: esportazioni spente
    senza righe, oggetto dell'attestazione già compilato.
    **v0.35.0** (06/10/2026): **Prima Nota** (`prima-nota.md`), da
    `gestionale-acv` della Compagnia e dal Gestionale APS: movimenti di
    cassa e banca con giustificativi, giroconti, fornitori e tag «trova o
    crea», le 33 categorie di `gestionale-acv` (con «uso» e natura da
    verificare), anni contabili (`gl_anni_contabili` al posto di
    `gl_prima_nota_anni_chiusi`) con saldi di apertura del primo anno,
    chiusura dopo l'approvazione del rendiconto e correzioni motivate,
    esportazioni CSV e PDF. `gestionale-acv` va in pensione dal
    01/01/2027 (migrati solo i saldi). Da fare: scontrini dal telefono con
    OCR, anticipi e note di rimborso (slittati dopo Rinnovi quota, decisione
    del 06/10/2026); poi riconciliazione bancaria e chiusura mensile della
    cassa.
    **v0.35.1** (06/10/2026): correzioni dal collaudo della Prima Nota
    (blocco J): errori nelle finestre senza chiuderle, giustificativi che
    restano dopo un errore, campo dei file come la tavola, nomi dei file
    codificati, anteprime con finestra, riga intera cliccabile, una riga di
    audit per operazione con hash per intero, verbale di chiusura scelto
    dall'elenco.
    **v0.36.0** (07/10/2026): **Rinnovi quota** (`rinnovi-quota.md`) e
    **Promemoria** (`promemoria.md`). Quote con ricevuta numerata per anno
    di pagamento (anche la prima quota dalla domanda online), movimento in
    Prima Nota, ricevuta PDF al socio o al genitore, annullamento motivato,
    situazione dell'anno (31 gennaio, voto sospeso, decadenza dal 1° maggio
    come elenco), detrazione per lo sport dei ragazzi solo con l'A.S.D.
    iscritta al RASD. Promemoria come servizio generale per tutte le
    funzioni: date, testi e destinatari, invio automatico senza doppioni,
    al genitore per i minori. Scontrini e anticipi slittano alle versioni
    dopo.
    **v0.37.0** (07/10/2026): **Tessere CSEN** (`tessere.md`): tipi di
    tessera (Silver Base 4 €), pacchetti con numeri stampati e uscita in
    Prima Nota, emissione e rinnovo a un anno dall'emissione (numero
    proposto, «Rinnova lo stesso» per chi è in ritardo con la quota), carico
    delle tessere già emesse dall'elenco del portale CSEN, badge «Tessera
    scaduta» in soci, scheda e rinnovi, quota netta; Tesoriere sulla sezione
    Tesseramento. Scadenzario, affiliazione, polizza RC e promemoria delle
    tessere nella v0.38.0.
    **v0.37.1** (07/10/2026): correzioni dal collaudo K: conto della quota
    nella scheda e nei rinnovi, «Elimina scheda» con motivazione e blocchi,
    schede eliminate ripristinabili dal filtro dei soci, avviso sulle
    domande di prova, ordine dei promemoria, descrizione al passaggio del
    mouse per i pulsanti con la sola icona.
16. **Statuto v1.0** — **chiuso da Paolo il 27/09/2026**. Documento
    "Statuto Compagnia Arcieri Castel Viscardo A.S.D. — v1.0" (Claude
    Docs, esportabile in Word per il notaio), con le note di revisione
    rispetto allo statuto 2021. Lo statuto 2021 non è più il riferimento:
    il gestionale si adegua alla v1.0. Denominazione "Compagnia Arcieri
    Castel Viscardo A.S.D.", sede al Campo di tiro "Franco Cortignani",
    località Macchietto, Viceno, Castel Viscardo (TR).
    Scelte di Paolo:
    - soci fondatori, ordinari e giovani (i minorenni);
    - niente deleghe in assemblea;
    - consiglio direttivo di 3–5 membri per 4 anni;
    - organo di controllo facoltativo, su decisione dell'assemblea;
    - quota entro il 31 gennaio e decadenza tre mesi dopo (30 aprile);
    - ammissione anche online, con decisione del CD entro 5 giorni dalla
      conferma e accoglimento d'ufficio se non vota la maggioranza o se i
      voti sono pari senza il voto del Presidente; diniego comunicato entro
      10 giorni, appello entro 30, quota entro 5;
    - CD anche in videoconferenza e per consultazione telematica con link
      personale;
    - assemblea anche a distanza, elettiva compresa; scrutinio segreto
      sempre per le elezioni, anche con strumenti telematici;
    - arbitrato rituale, terzo arbitro nominato dal Presidente del
      Tribunale di Terni.

    Resta da chiarire con commercialista o notaio se l'atto è una
    costituzione o la trasformazione della B.A.S. esistente. Restano da
    fare anche le verifiche esterne: requisiti CSEN, testo vigente del
    D.Lgs. 36/2021 (artt. 7–11), registrazione all'Agenzia delle Entrate.

    **Effetti sul gestionale, ancora da applicare:**
    - categoria "giovane" = minorenne, e qualifica di socio fondatore
      (`soci.md`);
    - scadenza della quota al 31 gennaio e decadenza al 30 aprile
      (Rinnovi quota);
    - regole della consultazione telematica: quorum, parità del
      Presidente, chiusura anticipata, richiesta di riunione da un terzo,
      accoglimento d'ufficio a 5 giorni (Domande) — **applicate in v0.30.0**,
      con le modifiche decise il 29/09/2026 (domanda valida con l'invio,
      parità senza il Presidente decisa da lui, chiusura anticipata solo
      con tutti i voti): testo per la v1.1 in
      `statuto/statuto-modifiche-v1.1.md`; la richiesta di riunione da un terzo
      dei consiglieri non è ancora nel gestionale;
    - voto segreto telematico con chi vota e cosa vota separati, e
      registro delle presenze (Assemblee).
