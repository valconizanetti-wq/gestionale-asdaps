# Piano di lavoro 2026–2027

**Data:** 13/10/2026 · **Fonte:** decisioni di Paolo del 13/10/2026
(`analisi-impianto-asd-aps.md` §5, e messaggio delle 13:17)
**Copia nel progetto:** `claude/piano-2027.md`

## Obiettivi (Paolo)

1. **01/01/2027**: il nuovo gestionale parte per la Compagnia, ed è **a
   tutti gli effetti quello di un'A.S.D.** (solo la denominazione resta
   B.A.S.): va previsto tutto quello che serve.
2. **Entro fine 2027**: il nuovo gestionale diventa il **modello unico**,
   configurabile per A.S.D. e APS/RUNTS, e vi si **migra il Dante** con il
   contenuto integro, per superare alcune scadenze fiscali.

Il criterio per ordinare i lavori è **la data in cui ogni funzione serve
davvero**, nell'anno della Compagnia.

## 1. Calendario degli obblighi della Compagnia nel 2027

| Quando | Obbligo o evento | Serve nel gestionale |
|---|---|---|
| Da gennaio | Incassi delle quote (entro il 31/01), ricevute, Prima Nota, spese anche pagate di tasca propria | Rinnovi quota ✓, Prima Nota ✓, Scontrini ✓, **anticipi e note di rimborso** |
| Da gennaio | Domande online, voto del CD, tessere CSEN, privacy | ✓ |
| Gennaio | Saldi di apertura dal 31/12/2026 di `gestionale-acv` | Anni contabili ✓ |
| Gennaio–febbraio | Preventivo 2027 (approvato fuori, entro il 31/12/2026) da registrare | **Bilancio: «registra già approvato»** |
| Almeno 2 volte l'anno | Riunioni del CD con verbale (statuto art. 16) | **Verbali, Governance CD** |
| Entro il 30/04 | Assemblea: convocazione via email a ogni socio almeno 10 giorni prima (art. 12.3), rendiconto 2026 (prodotto da `gestionale-acv`), verbale | **Assemblee con convocazione, Verbali** |
| Dal 01/05 | Presa d'atto delle decadenze per quota non versata | Elenco ✓ + **verbale del CD** |
| Tutto l'anno | Affiliazione, polizza, rendiconto (Scadenzario), audit, promemoria | ✓ |
| Entro il 31/12 | Preventivo 2028 in Assemblea, confronto con l'andamento 2027 | **Bilancio completo (Modello D, preventivo, scostamenti)** |
| Entro il 30/04/2028 | Rendiconto 2027, approvazione e chiusura dell'anno | Bilancio completo |

Non richiesti alla Compagnia nel 2027 (verificati in MANUALE §2.2, §2.5):
- certificati medici: il tiro con l'arco non agonistico è esonerato;
- RASD, plafond 398, rimborsi forfettari ai volontari: solo dall'A.S.D.
  (2028);
- assicurazione dei volontari: compresa nella polizza CSEN.

**Da chiedere a CSEN**: gli adempimenti dell'art. 16 D.Lgs. 39/2021
(modello organizzativo, codice di condotta, responsabile), previsti dagli
artt. 17.1.8 e 28 dello statuto. La tessera dà la copertura assicurativa,
non questi adempimenti. Paolo ritiene che bastino le tessere: è un punto
da verificare, non una decisione presa.

## 2. Fasi

### Fase 0 — entro il 31/12/2026 (partenza della Compagnia)

**Rivista da Paolo (13/10/2026, 13:27)**: entro il 31/12 servono quattro
cose, tutte già costruite e da **attivare**:
1. iscrizioni online;
2. rinnovo delle quote;
3. link privacy inviato a tutti i soci;
4. migrazione sul dominio definitivo.

Nel database ci sono ancora solo dati di prova, che si possono
cancellare. Registro dei moduli e anticipi passano a gennaio (Fase 1).

Cosa serve per attivarle, in quest'ordine (dettaglio nel §2-bis):
1. collaudi residui chiusi e **procedura di azzeramento scritta come
   comando** (`php artisan …` con prova a vuoto), non a mano;
2. **dominio definitivo**, ancora con i dati di prova:
   - certificato SSL, `APP_URL`, cron;
   - posta: SPF, DKIM e DMARC per arciericastello.it (oggi sono su
     valconi.it);
   - indirizzo vecchio reindirizzato al nuovo;
3. **testi definitivi**:
   - informativa senza segnaposto (hosting SupportHost con DPA,
     consulente fiscale), **prima** di inviare i link privacy, altrimenti
     servirà una nuova versione;
   - statuto adottato dall'Assemblea della B.A.S. **prima** di aprire le
     iscrizioni, perché la domanda dichiara di averlo letto;
4. **azzeramento**, poi **utenti veri del CD** (oggi sono tutti
   `valconi@usa.net`) e **soci veri** (vedi domanda 4), poi tessere dal
   CSV CSEN;
5. **dicembre**:
   - invio del link privacy a tutti;
   - apertura delle iscrizioni;
   - quote 2027 (fino al 31/12 con ricevuta e senza movimento, già
     previsto);
   - promemoria del 15/12.

Gennaio 2027: saldi di apertura dal 31/12/2026 di `gestionale-acv`.

### Fase 1 — gennaio–marzo 2027 (prima Assemblea nel gestionale)

0. **Registro dei moduli** e **anticipi e note di rimborso** (spostati
   dalla Fase 0).
1. **Verbali e Assemblee dal Dante**, con convocazione email ai soci e
   risposte (RSVP); verbali del CD e dell'Assemblea; collegamento con le
   domande (verbale di ammissione) e con la chiusura dell'anno.
2. **Governance CD e Area Consiglieri** dal Dante (cariche, durata,
   incompatibilità).
3. **Bilancio, primo passo**: mappa delle categorie sul Modello D,
   preventivo con «registra già approvato» per il 2027, confronto dal
   vivo con la Prima Nota.

### Fase 2 — aprile–settembre 2027 (modello unico)

1. **Bilancio completo**: rendiconto Modello D, approvazione e chiusura
   dell'anno insieme, PDF con codice di verifica, copia firmata.
2. **Moduli del Dante** che la Compagnia non usa ma il modello deve avere,
   spenti per la Compagnia: RUNTS, 5 per mille ed erogazioni liberali,
   Comunicazioni e lettere, Corrispondenza, Fascicolo, Archivio,
   Collaborazioni, F24, Storno, Volontari e rimborsi.
3. **Conciliazione dei moduli doppi**: domande online (con quella
   cartacea), profilo dell'ente (campi RUNTS e del comitato), tessere
   (sociali ed EPS).
4. **Cassa mensile e riconciliazione bancaria** (decisione 5: restano),
   accese anche per la Compagnia.

### Fase 3 — ottobre–dicembre 2027 (Dante)

1. **Preventivo 2028** della Compagnia nel gestionale (entro il 31/12).
2. **Migrazione del Dante**:
   - script di passaggio `gs_` → modello unico;
   - catena dell'audit sigillata e proseguita;
   - numerazioni e documenti firmati intatti;
   - prova completa su una copia, con confronto automatico dei totali;
   - collaudo di Paolo, poi passaggio.

## 2-bis. Scadenze del Dante (Paolo, 13/10/2026) e finestra di migrazione

| Data | Adempimento del Dante | Cosa serve nel modello unico |
|---|---|---|
| 16/03 | Certificazione Unica dei collaboratori occasionali pagati l'anno prima | Collaborazioni con i compensi e le ritenute dell'anno |
| 30/04 | Approvazione del rendiconto per cassa in Assemblea | Bilancio Modello D, Verbali, Assemblee |
| 30/06 (29/06 indicato) | Deposito del rendiconto per cassa (Modello D) al RUNTS | Modello D in PDF, Compliance RUNTS |
| 30/06 | Aggiornamento RUNTS: numero di soci e volontari al 31/12 | Libro soci, **registro dei volontari** (obbligatorio per l'APS, art. 17 CTS) |
| 31/10 | Modello 770 (se ci sono ritenute con F24) | F24 e ritenute dell'anno prima |

Conseguenza: **migrare il Dante al passaggio d'anno** (dicembre 2027,
partenza 01/01/2028) è la scelta più pulita. Ogni scadenza del 2028
riguarda l'anno 2027 e si fa tutta con il gestionale nuovo, sui dati
migrati; nessun anno fiscale resta diviso tra due sistemi. Una migrazione
a metà 2027 (dopo il 30/06) dividerebbe il 2027 a metà: da evitare.

Cosa deve essere pronto e quando:
- **entro il 31/12/2027** (uso quotidiano dal 01/01/2028): soci, quote e
  ricevute, Prima Nota, Verbali e Assemblee, Governance CD, Collaborazioni
  e F24 per i pagamenti, Comunicazioni, il resto dei moduli in uso al
  Dante;
- **entro marzo 2028**: dati per la CU 2027 dalle Collaborazioni migrate;
- **entro aprile 2028**: rendiconto Modello D 2027 del Dante;
- **entro giugno 2028**: pacchetto per il RUNTS (Modello D, numero di soci
  e volontari al 31/12/2027);
- **entro ottobre 2028**: dati per il 770.

Il vecchio gestionale del Dante resta acceso in sola lettura per tutto il
2028, come `gestionale-acv` per la Compagnia nel 2027.

## 3. Rischi

1. **Carico del 2027** (attenuato dalla migrazione al passaggio d'anno, §2-bis). Fasi 2 e 3 portano nel modello circa 25
   controller del Dante e la migrazione, mentre la Compagnia usa il
   gestionale. Serve conoscere le **scadenze fiscali del Dante** che
   fissano la fine del 2027: se una cade prima (per esempio a metà
   anno), la Fase 2 va ordinata su quella.
2. **Fase 0 stretta**: restano circa 11 settimane per tre consegne con
   collaudo (moduli, anticipi, apertura), più l'Assemblea della B.A.S.
   Proposta: niente altro in Fase 0.
3. **Due codici in parallelo** fino alla migrazione: ogni correzione al
   Dante nel 2027 va riportata anche nel modello. Proposta: nel 2027, sul
   Dante solo correzioni urgenti.
4. **Verbali prima di aprile**: se slittano, l'Assemblea di aprile si
   verbalizza fuori e si registra dopo (stesso principio del preventivo
   2027).

## 4. Domande a Paolo

1. ~~Scadenze del Dante~~: risposto (§2-bis).
2. ~~Dominio definitivo~~: sì, entro il 31/12 (Fase 0).
3. ~~Contenuto della Fase 0~~: le quattro attivazioni di Paolo.
4. **Soci veri**: da dove si caricano (circa 30)? Il Gestionale ASD non
   ha un'importazione dei soci (il Dante sì, `SociImportController`).
   Possibilità:
   - (a) a mano dalla scheda;
   - (b) importazione da un foglio (CSV) con codice fiscale, dati
     anagrafici, residenza, email, telefono, data di ammissione,
     fondatore;
   - il CSV del portale CSEN non basta: mancano email e residenza.
5. **Data dell'Assemblea della B.A.S.** che adotta lo statuto: le
   iscrizioni online si aprono dopo.
6. **Utenti veri del CD**: nomi, ruoli ed email per sostituire il CD di
   prova.
