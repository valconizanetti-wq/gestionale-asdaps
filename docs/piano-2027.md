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

1. **Registro dei moduli** con gli interruttori (`analisi-impianto` §7),
   piccolo, perché tutto ciò che arriva dopo vi si innesti senza rami.
2. **Anticipi e note di rimborso** (dal Dante, con le correzioni).
3. **Pronti per l'apertura**:
   - informativa privacy senza segnaposto: hosting SupportHost con
     datacenter UE e DPA (punto 4), consulente fiscale (punto 5);
   - IBAN vero, casella info@arciericastello.it attiva;
   - `APP_DEBUG=false`, cron verificato;
   - **dominio definitivo**: conviene passarci **prima** dell'apertura,
     perché i link già inviati per email (privacy, voto del CD, ricevute)
     contengono l'indirizzo.
4. **Azzeramento** (J12, K11, L12, M12, N12, registro di audit) e saldi di
   apertura 2027.
5. Fuori dal gestionale: Assemblea della B.A.S. che adotta lo statuto e
   approva il preventivo 2027.

### Fase 1 — gennaio–marzo 2027 (prima Assemblea nel gestionale)

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

## 3. Rischi

1. **Carico del 2027.** Fasi 2 e 3 portano nel modello circa 25
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

1. Quali sono le **scadenze fiscali del Dante** che fissano la migrazione
   entro fine 2027, e cadono a fine anno o prima?
2. **Dominio definitivo** prima dell'apertura del 01/01/2027: sì?
3. Fase 0 limitata a registro dei moduli, anticipi, apertura e
   azzeramento: va bene?
