# Bootstrap — Gestionale ASD, dopo la v0.34.0 (Audit Log, 06/10/2026)

## Contesto
Come in `bootstrap-voto-cd.md` (progetto, ambienti, regole di lavoro,
deploy §5). In più: dalla v0.30.0 serve il cron di cPanel
`php artisan schedule:run` ogni minuto (`deploy.md` §6).

## Stato alla chiusura (29/09/2026)
- v0.30.0 consegnata: domanda valida con l'invio, voto del CD dal link
  personale, esiti, email nello stile del sito, area riservata
  `/domande`, bozza di verbale, iscrizione nel libro soci con rinnovo e
  Prima Nota, promozione di ottobre–dicembre, comando orario
  `domande:scadenze`. Scheda: `domande-iscrizione.md` (§3 e §7).
- CD di prova: Andrea Papini (Presidente), Massimo Papini, Paolo Zanetti
  (Segretario e Tesoriere), Luigi Tascio, tutti `valconi@usa.net`.
- IBAN: ancora segnaposto `IT XX …` (scheda Associazione vuota).
- Posta: DKIM e DMARC di `valconi.it` sistemati il 29/09/2026.
- Statuto (v0.30.4, 04/10/2026): il testo pubblicato
  (`resources/content/statuto/1.0.md`, pagina `/statuto`) è la bozza 1.1
  completa: artt. 6.1, 6.4, 6.6 (quota disposta entro 5 giorni) e 16.4
  lett. d come in `statuto/statuto-modifiche-v1.1.md`, più le clausole CSEN (artt.
  6.3, 11.3, 14, 16.7, 22.5, 24-bis, 31). Numerato 1.0 fino all'apertura.
  Bozza 1.1 (md, docx, `ACV-Statuto-1.1.pdf`) aggiornata all'art. 6.6. Da
  chiedere al CSEN: esclusione in Assemblea vincolante? accoglimento
  d'ufficio e voto telematico accettati?

## Fatto il 04/10/2026
- v0.30.4 e v0.30.5: statuto unito, ora italiana (sopra e sotto).
- v0.31.0: assenso del minore dai 14 anni al tesseramento (art. 16, c. 2,
  D.Lgs. 36/2021; statuto art. 6.3), campo `gl_domande.assenso_minore_il`,
  dichiarazione obbligatoria nel passo 2 (comprende foto e video se il
  genitore li ha consentiti), ricevuta (minore e genitore affiancati, in
  una pagina), scheda, verbale; riferimento normativo sempre in piccolo
  (regola di Paolo). Analisi: soglia di legge 14, non 12; nessun consenso
  privacy del minore da raccogliere. Da collaudare: prove A11–A13.
  Dettaglio: `domande-iscrizione.md` §7, `LEGGIMI-v0.31.0.txt`.

## Decisione del 05/10/2026: tempi di adozione (Paolo)
- Il gestionale entra in uso per la **B.A.S. dal 1° gennaio 2027**; dal
  **1° gennaio 2028** passaggio all'A.S.D. (stesso codice fiscale).
- Fino ad allora `arco.valconi.it` si tratta come **produzione effettiva ma
  chiusa al pubblico**; poi migrazione al **nuovo dominio, già
  disponibile**.
- Passaggio B.A.S. → A.S.D. come personalizzazione valida per qualunque
  ente: **profilo dell'ente per periodi** (analisi in `docs/profilo-ente.md`,
  05/10/2026). Nome: "Compagnia Arcieri Castel Viscardo B.A.S." (Base
  Associativa Sportiva); regime della B.A.S. come ASD non commerciale.
  Statuto B.A.S. del 24/07/2024 letto: ammissione decisa dal Presidente
  con ratifica del CD, sede in via Carlo Zanetti 12. **Deciso (strada a)**:
  la B.A.S. adotta lo statuto rivisto (`statuto/statuto-bas-bozza.md`, .docx, PDF),
  da approvare in Assemblea entro il 2026; pubblicato come 1.0 (v0.31.1).
  Da decidere: data della migrazione e dell'azzeramento.
- **Testo unico dello statuto (Paolo, 05/10/2026)**: la B.A.S. adotta lo
  statuto dell'A.S.D. con i soli adeguamenti per la denominazione, cioè
  `docs/statuto/statuto-bas-bozza.md` (.docx, `ACV-Statuto-BAS-bozza.pdf`),
  uguale a `resources/content/statuto/1.0.md`. Documenti dello statuto in
  `docs/statuto/` (spostati da Paolo); tolti la bozza 1.1
  (`statuto-1.1-bozza.*`, `ACV-Statuto-1.1.pdf`) e `Statuto ASD - Bozza.*`;
  `statuto/statuto-modifiche-v1.1.md` resta solo come storia.

## Fatto il 05/10/2026: v0.32.0 (profilo dell'ente)
- Tabella `gl_associazione_periodi` e servizio `ProfiloEnte`: denominazione,
  forma, sede, regime, RASD, affiliazione e testi in vigore per periodo.
  Periodi della Compagnia (`ProfiloEnteSeeder`): B.A.S. dal 01/01/2027
  (non commerciale, statuto 1.0, informativa 1.0); A.S.D. dal 01/01/2028
  (398, statuto 1.0 se il commercialista conferma che basta l'art. 2.4,
  informativa 2.0 **da scrivere e pubblicare** prima del 2028).
- Pagina Impostazioni › Ente (solo il Presidente modifica): dati fissi
  (IBAN), periodi, lista di controllo del passaggio, pulsante per aprire o
  chiudere le iscrizioni online.
- `/iscrizione` **chiusa al pubblico** per impostazione predefinita; da
  collegati si prova il modulo e le domande nascono di prova. Per il
  collaudo con finestre anonime aprirla e richiuderla alla fine (lista di
  collaudo, Preparazione e F4; nuovo blocco G).
- Dettaglio: `profilo-ente.md` §4 e §7, `LEGGIMI-v0.32.0.txt`.
- **Installata sul server il 05/10/2026** (Paolo), compresi i due seeder
  (`ProfiloEnteSeeder`, `ImpostazioneSeeder`). Collaudo del 05/10/2026
  (blocco G, A11–A13): tutto superato; unica richiesta in G3, dati fissi in
  due righe (codice fiscale, email, PEC, telefono / banca, IBAN, BIC) e BIC
  nel riquadro di Impostazioni › Ente: fatto nella **v0.32.1**, da
  installare e ricontrollare (G3).

## Prossimo obiettivo
1. (Fatto il 05/10/2026: v0.32.1 installata, `docs/statuto` sistemata.)
   IBAN non ancora disponibile: resta il segnaposto.
2. **v0.33.0 consegnata (05/10/2026)**: link personale per l'informativa
   privacy e consenso a foto e video (`privacy-link.md`, tavole
   `Privacy-1…5`). Da installare (2 migration, `npm run build`) e
   collaudare: blocco H della lista di collaudo. Poi riportarlo nel core e
   nel Gestionale APS (elenco unico: `da-riportare-gestionale-aps.md`).

## Collaudo (29–30/09/2026)
- v0.30.3 consegnata (ZIP cumulativo con la v0.30.2; dettaglio in
  `domande-iscrizione.md` §7 e in `LEGGIMI-v0.30.3.txt`). Collaudo della lista
  `test-domande-iscrizione.md` concluso il 30/09/2026: blocchi A–F superati,
  pulizia fatta (allora con un giorno in più nelle simulazioni, per il
  gestionale in UTC: corretto nella v0.30.5). Cron verificato: il comando
  orario gira al minuto 00. Sul server, se non già fatto: riga del cron
  di cPanel riportata a `>> /dev/null 2>&1` e `storage/logs/cron.log`
  cancellato (`deploy.md` §6).
- Decisioni del 30/09: quota con bonifico disposto entro 5 giorni
  dall'accoglimento, decadenza dopo altri 5 di margine; ritiro di una
  domanda accolta solo fino al termine per la quota; dichiarazioni su
  statuto e informativa senza obbligo di aprirli (l'ora del clic su "Invia"
  vale per le dichiarazioni); denominazione "Compagnia Arcieri Castel
  Viscardo A.S.D."; indirizzo pubblico e ufficiale, email
  info@arciericastello.it e sito arciericastello.it in `config/sito.php`;
  informativa e statuto corretti sul posto alla versione 1.0 fino
  all'apertura.
- Modulo del socio rifatto: righe compatte, dati di nascita dal codice
  fiscale, residenza/email/telefono obbligatori, telefono unico (tolto il
  cellulare), genitore completo per i minorenni (`soci.md`).
- Sul server: CD di prova inserito via SQL; a ogni deploy
  `php artisan optimize:clear` prima delle cache (`deploy.md` §5); scheda
  Associazione da aggiornare con info@arciericastello.it quando la casella
  riceve posta.
- `docs/specifiche/domande-iscrizione.md` è una copia identica di
  `docs/domande-iscrizione.md`: si aggiornano insieme.
- Lista di collaudo: `docs/test/test-domande-iscrizione.md` e
  `claude/test-domande-iscrizione.md` nel progetto, uguali (prove A9 sullo
  statuto pubblicato e A10 sulle ore, da fare dopo il deploy).
- Fuso (v0.30.5): il gestionale è in ora italiana (`config/app.php`, prima
  `'UTC'` fisso), come MySQL; le simulazioni usano i giorni esatti. Dopo il
  deploy: reset dei dati di prova (scritti in UTC).

## Fatto il 05/10/2026: v0.33.0 (link privacy)
- Tabella `gl_privacy_link` (token come impronta, monouso, senza scadenza,
  un nuovo invio annulla il precedente); invio dalla scheda e in blocco
  dall'elenco Soci, al genitore per i minori; stati della privacy nella
  scheda: mancante, in attesa, presente, da aggiornare (versione
  precedente dell'informativa).
- Consenso a foto e video in `gl_soci` (`foto_video_*`): dal link, dalla
  domanda online (copiato all'iscrizione e, con la migration, per i soci
  già iscritti) e revoca registrata dalla segreteria.
- Informativa 1.0, punto 2: dati della conferma con il link (correzione sul
  posto; l'impronta dell'informativa 1.0 cambia di nuovo).
- 41 test su MySQL; tre giri di revisione (in `privacy-link.md` §7).

## Fatto il 06/10/2026: v0.34.0 (Audit Log)
- Sezione Audit Log ripresa dal Gestionale APS (`audit-log.md` §9):
  elenco, filtri, prima/dopo, CSV e PDF, verifica, certificato annuale,
  attestazioni, verifica mensile automatica con email a Presidente e
  Revisori. Archiviazione rimandata (decisione di Paolo). Organo di
  controllo facoltativo: senza Revisore fa tutto il Presidente.
- Corretti due difetti trovati strada facendo: `created_at` del registro
  in DATETIME (la catena dipendeva dal fuso di MySQL) e larghezze delle
  colonne dei PDF (DomPDF ignora `<col>`; anche il libro soci).
- Da installare (1 migration, `npm run build`) e collaudare: blocco I.
- **v0.34.1** (06/10/2026): correzioni dal primo collaudo del blocco I
  (`audit-log.md` §9, "v0.34.1"). **v0.34.2**: I5 chiarito (file estratti
  con la ricerca «%» attiva), pulsante più piccolo su telefono, oggetto
  dell'attestazione già compilato. Resta da concludere il blocco I.
- Azzeramento prima dell'apertura: svuotare anche registro, certificati,
  `audit_ultima_verifica` e `storage/app/private/audit/`.

## Modifiche per il Gestionale APS
- Tutte in `docs/da-riportare-gestionale-aps.md` (creato il 06/10/2026),
  una casella per voce, in ordine di importanza: sicurezza e integrità,
  dati personali, Audit Log, funzioni nuove, PDF ed email. Ogni nuova
  correzione che riguarda anche il Gestionale APS va aggiunta lì, non nel
  manuale (che in §7 tiene solo quelle per il core).

## Punti aperti
- Modulo Verbali (libro dei verbali): oggi esiste solo la bozza
  automatica del verbale di ammissione per ogni domanda (`VerbaleAmmissione`,
  "Apri il verbale" nella scheda); mancano approvazione, numerazione, firma,
  PDF ed elenco dei verbali. Finché non c'è, i verbali restano in bozza.
- Statuto: al momento dell'adozione decidere se pubblicarlo come 1.0
  (prima versione pubblica) o 1.1. Lo statuto pubblicato prevede già
  l'assenso del minore dai 14 anni (art. 6.3), che il modulo non chiede
  ancora (prossimo obiettivo, punto 1).
- Azzeramento finale prima dell'apertura al pubblico (Paolo, 29/09/2026:
  oggi è tutto in prova): domande, voti, email, verbali, soci, rinnovi e
  movimenti di prova, registro di audit; modalità test spenta. Procedura
  da scrivere quando ci si arriva. Fino ad allora informativa e statuto si
  correggono sul posto con il numero di versione dell'avvio (1.0), senza
  versioni intermedie (`domande-iscrizione.md` §7, v0.30.3).
- Informativa: segnaposto di hosting (punto 4, SupportHost: datacenter UE
  e DPA) e del consulente fiscale (punto 5) da sostituire.
- Assenso del minore ai rinnovi: l'art. 16 vale per ogni tesseramento,
  quindi anche al rinnovo di un socio giovane che ha compiuto 14 anni
  (moduli Rinnovi quota e Tesseramento).
- Informativa: valutare una frase in linguaggio semplice per il minore dai
  14 anni, che ora dichiara di averla letta con il genitore.
- Recesso dal link della ricevuta dopo l'iscrizione, al posto di "Domanda
  già decisa" (`domande-iscrizione.md` §7, v0.30.3): dimissioni al CD per
  email (statuto art. 9), dati della domanda conservati.
- Pagina Impostazioni (quote, promozione, versioni dei testi).
- Numero di ricevuta della prima quota (modulo Rinnovi quota).
- Richiesta di riunione da un terzo dei consiglieri (art. 16.4 lett. f).
