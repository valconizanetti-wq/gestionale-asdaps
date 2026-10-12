# Bootstrap — Gestionale ASD, dopo la v0.38.0 (Scadenze dell'associazione, 11/10/2026)

## Prossima sessione
1. **Collaudo M** della v0.38.0 (installata sul server da Paolo
   l'11/10/2026). Correzioni in una v0.38.1, se servono.
2. **Scontrini con OCR**: analisi fatta l'11/10/2026 sera
   (`docs/analisi-scontrini.md`, anche nel progetto); risposte di Paolo in
   §7 (CD registra dal telefono: ruolo «Consigliere»; anticipi dopo; nota e
   fornitore obbligatori; partita IVA sì; una foto), divisione per righe e
   categorie in §8; CD con Prima Nota in lettura e fornitore riconosciuto,
   scelto o creato in §9. Prossimo passo: tavole SN-1…SN-5 sulla tela (aspettando
   le foto di scontrini veri di Paolo), poi codice.
3. Poi, in ordine da concordare: anticipi e note di
   rimborso, Verbali (servono per le decadenze e la chiusura dell'anno),
   Rendiconto, procedura di azzeramento; tecnici e tesserino tecnico in
   standby finché Paolo non ha approfondito (`analisi-tessere-scadenze.md`
   §5).

Decisioni di Paolo (11/10/2026, 22:48): affiliazione scaduta = **solo
avviso** (nessun blocco delle tessere); polizza RC al **31/07**.

Codice di partenza: `gestionale-asd-<commit>.bundle` (l'intero repository
git, storia compresa, in un file solo; il commit è nel nome). Si
ricostruisce con `git clone gestionale-asd-<commit>.bundle
/home/claude/gestionale-asd` e si verifica con `git log -1`; poi
`cp .env.example .env`, `composer install`, `npm ci`, `php artisan
key:generate`, MariaDB (`service mariadb start`, se manca `apt-get install
mariadb-server`, database `asd_test`) e `php artisan test` (104 test verdi).

## Stato all'11/10/2026
- Sul server: **v0.38.0 installata** (Paolo, 11/10/2026); collaudi K e L
  superati il 07/10/2026, **collaudo M da fare**.
- Restano l'azzeramento (J12, K11, L12, M12) e `APP_DEBUG=false` prima
  dell'apertura.

## Fatto l'11/10/2026: v0.38.0 (Scadenze dell'associazione)
Tavole SC-1, SC-2, SC-3, PR-3 (approvate il 07/10/2026). Scheda
`docs/specifiche/scadenzario.md` (riscritta), `promemoria.md`.
- Scadenzario (menu Controllo): ricorrenti e una tantum con stato e
  preavviso; «Rinnovata» registra l'uscita in Prima Nota con il
  giustificativo e sposta la data all'anno dopo; «Fatta» per gli
  adempimenti; storico `gl_scadenzario_rinnovi` e «Annulla l'ultimo
  rinnovo» motivato. Pronte: Affiliazione CSEN 31/12, Polizza RC 31/07,
  Approvazione del rendiconto 30/04.
- Impostazioni › Ente: riquadri «Affiliazione all'EPS» e «Polizza RC»
  (scadenza letta dallo Scadenzario, un solo dato), «Modifica» del
  Presidente; Tesoriere e Segretario vedono la pagina in sola lettura.
- Avviso rosso dell'affiliazione scaduta in riquadro, Dashboard,
  Scadenzario, pagina Tessere (solo avviso, non blocco: confermato da Paolo).
- Promemoria «N giorni prima o dopo» nel motore; funzioni «Scadenze
  dell'associazione» (30 e 7 giorni, Presidente e Tesoriere) e «Tessere in
  scadenza» (15 giorni, Tesoriere, una email per giorno di scadenza).
- Permessi: Scadenzario anche al Tesoriere, Revisore in lettura.
- 104 test (nuovo `ScadenzarioTest`); tre giri di revisione (10 difetti
  corretti, in `scadenzario.md` §7). `da-riportare-gestionale-aps.md` D10,
  D11.

## Fatto il 07/10/2026: v0.37.2 (collaudo, pomeriggio)
- Errore 500 sul server dopo l'installazione: «Route [tesseramento.index]
  not defined» = rotte in cache vecchie (o `routes/web.php` non copiato):
  `php artisan optimize:clear`. Sul server `APP_DEBUG` è attivo: rimetterlo a
  `false` prima dell'apertura.
- L7: «L'emissione può essere al massimo tra 60 giorni» per un socio con la
  tessera valida a lungo: ora la finestra lo spiega prima e il pulsante
  resta spento (`tessere.md` §7).
- Su telefono menu a destra dopo «Esci», barra laterale da destra (`ui.md`).
- 91 test.

## Fatto il 07/10/2026: v0.37.1 (correzioni dal collaudo K)
- K3 conto (Cassa/Banca) accanto alla modalità della quota, nella scheda e
  nei Rinnovi quota.
- K7 «Elimina scheda»: motivazione obbligatoria (Audit Log, nei valori, con
  l'email al Presidente), rifiutata per un socio con quote valide, movimenti
  o tessere. Ripristino: Soci › Filtri › Stato «Schede eliminate» (solo
  Presidente), con ricerca per codice fiscale; niente CSV né PDF di quelle.
- K8 la domanda di Paolo era di prova: non si iscrive (scelta del 29/09);
  ora un avviso lo dice nel riquadro della quota, e K8 spiega come provarla.
- K9 promemoria nell'ordine dell'anno sociale, nome come link, freccia.
- Descrizione al passaggio del mouse per tutti i pulsanti con la sola
  icona (`app.js`, dall'`aria-label`).
- Bordo delle righe con i pulsanti (celle `td-azioni` non più flex).
- Test instabile corretto: in `RinnoviQuotaTest` (e nei test nuovi) gli
  utenti avevano un nome casuale di Faker, che poteva essere «Rossi» e far
  fallire un `assertDontSee('Rossi')` (successo una volta su quattro giri):
  ora nomi fissi.
- 90 test (nuovo `CollaudoKTest`); tre giri di revisione (5 difetti
  trovati dal revisore e corretti: link alla scheda eliminata, esportazioni
  con il filtro «Schede eliminate», motivazione nell'hash, transazione ed
  email, quote senza numero).

## Fatto il 07/10/2026: v0.37.0 (Tessere CSEN)
- Collaudo K della v0.36.0: osservazioni di Paolo corrette nella v0.37.1 e
  v0.37.2; **superato il 07/10/2026**. Tavole
  TS-1…TS-5, SC-1…SC-3, PR-3 approvate; tipo **Silver Base a 4 €**.
- Paolo ha mandato l'elenco delle tessere esportato dal portale CSEN (30
  tessere): diventa la funzione «Carica tessere già emesse» (anteprima,
  abbinamento per codice fiscale, conferma; il file si cancella). Dati
  personali: non nel codice né nel progetto.
- Fatto: tipi di tessera; pacchetti con numeri stampati (prefisso di
  stagione + progressivo), uscita in Prima Nota «Tesseramento Soci EPS» con
  giustificativi; emissione e rinnovo a un anno dall'emissione con numero
  proposto e «Rinnova lo stesso» per chi è in ritardo con la quota (dal
  2027; prima «quota nel vecchio gestionale»); badge «Tessera scaduta» in
  soci, scheda e rinnovi; quota netta; invito a emettere la tessera dopo la
  quota; Tesoriere sulla sezione Tesseramento, Revisore in lettura; comando
  giornaliero `tessere:pulisci`.
- Scheda `docs/specifiche/tessere.md` (§7: tre giri di revisione, 10 + 4
  difetti corretti); `analisi-tessere-scadenze.md` §4 (decisioni);
  `permessi.md`, `tesseramento-sportivo.md` §7, MANUALE, `ui.md`,
  `deploy.md` §6; lista di collaudo blocco L.
- Tratto `Concerns\Giustificativi` estratto da `PrimaNotaController` (stesso
  comportamento, test della Prima Nota verdi).
- Azzeramento prima dell'apertura: anche tessere e pacchetti di prova (L12).

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

## Prossimo obiettivo (punto della situazione, 07/10/2026)
- **In produzione** (deploy di Paolo, 06/10/2026): v0.35.1, con Prima Nota,
  Audit Log e link privacy. IBAN non ancora disponibile: resta il
  segnaposto.
- **v0.35.0 consegnata** (Prima Nota, sotto) e collaudata da Paolo il
  06/10/2026 (blocco J): le correzioni sono nella **v0.35.1** (sotto),
  **installata sul server da Paolo il 06/10/2026** e ricontrollata (voci
  «v0.35.1» del blocco J: nessun problema).
- **Domanda di Paolo (J10)**: la quota di un socio inserito a mano non ha
  dove registrarsi; oggi la registra solo la domanda online accolta
  (scheda della domanda, «quota versata»: socio, rinnovo e movimento
  insieme). Serve il modulo **Rinnovi quota** (ricevuta numerata +
  movimento), da usare dal 01/01/2027. **Deciso da Paolo (06/10/2026): si
  fa Rinnovi quota adesso, prima degli scontrini** (che slittano). Fatto
  nella v0.36.0.
- **v0.36.0** (Rinnovi quota e Promemoria, sotto): installata e collaudata
  (collaudo K in corso). **v0.37.0** (Tessere CSEN) e **v0.37.1** (correzioni
  dal collaudo K), sopra: da installare insieme e collaudare (K, poi L). Poi: scontrini con OCR, anticipi e note di
  rimborso, Verbali (servono per le decadenze e la chiusura dell'anno),
  Rendiconto, procedura di azzeramento.
- **Tessere CSEN e scadenze** (Paolo, 07/10/2026): 4 € di ogni quota al
  CSEN, tessere comprate a pacchetti con numeri stampati e assegnate a
  tutti i soci, valide un anno dall'emissione (non dall'anno solare),
  prezzo secondo il tipo; affiliazione con scadenza; polizza RC Unipol
  tramite CSEN in scadenza a luglio (fatto nella v0.38.0). Analisi
  `docs/analisi-tessere-scadenze.md` (anche nel progetto); tavole TS-1…TS-5,
  SC-1…SC-3 (affiliazione e polizza in Impostazioni › Ente), PR-3 sulla tela e in `docs/mockup/Tavole-tessere-scadenze.pdf`,
  approvate il 07/10/2026; tessere fatte nella v0.37.0, il resto nella
  v0.38.0 (manca il mese di scadenza dell'affiliazione).
- Da chiedere al commercialista: dicitura IVA della ricevuta, bollo sopra
  77,47 €, quota del socio giovane come «iscrizione annuale» per la
  detrazione sportiva (dal 2028).

## Fatto il 07/10/2026: v0.36.0 (Rinnovi quota e Promemoria)
Analisi `docs/analisi-rinnovi-quota.md` (anche nel progetto), tavole
RQ-1…RQ-6, PR-1, PR-2 approvate. Schede `rinnovi-quota.md`, `promemoria.md`.
- Quote con ricevuta numerata per anno di pagamento (anche la prima quota
  dalla domanda online, allegata all'email di benvenuto), movimento in
  Prima Nota, ricevuta PDF al socio o al genitore, annullamento motivato,
  situazione dell'anno (31/01, voto sospeso, decaduti dal 1° maggio solo
  come elenco), quote nella scheda del socio e annullamento anche
  dall'elenco (il Tesoriere non vede la scheda).
- Detrazione per lo sport dei ragazzi solo con l'ente iscritto al RASD alla
  data del pagamento: la B.A.S. non lo è (Paolo, 06/10/2026).
- **Contabilità dal 2027** (impostazione `contabilita_anno_avvio`): una
  quota incassata prima (es. la 2027 versata a dicembre 2026) ha ricevuta
  ma niente movimento, che resta in `gestionale-acv`.
- Promemoria come servizio generale (funzione «Quote non versate» con tre
  promemoria pronti: 15/12, 1/2, 1/4), comando `promemoria:invia` ogni 15
  minuti con lo stesso cron.
- Correzione del MANUALE §2.3: esenzione IVA rinviata al 2036.
- 75 test; tre giri di revisione in `rinnovi-quota.md` §7.

## Fatto il 06/10/2026: v0.35.1 (collaudo J della Prima Nota)
- J2 errore nella finestra della categoria (e del tag) senza chiuderla
  (`x-modale :aperta`, bag per finestra).
- J4 «Nuovo movimento» nella scheda; giustificativi che restano dopo un
  errore (messi da parte prima della validazione, `tmp/prima-nota/`);
  campo dei file come la tavola (`x-campo-giustificativi`); nome del file
  codificato `PN<anno>-<n.>-<progr.>`; riga dell'elenco cliccabile;
  anteprime con finestra (su telefono il PDF si apre con i pulsanti).
- J6 «Cassa → Banca» su una riga. Il saldo negativo della banca è
  corretto (confermato da Paolo).
- J9 una riga di audit per operazione (giroconto, eliminazione con
  giustificativi: `auditContesto()` del model), etichette leggibili, hash
  per intero nel dettaglio.
- J11 verbale di chiusura scelto dall'elenco delle Assemblee dell'anno in
  corso (`approvazione_verbale_id`); senza modulo Verbali l'elenco è vuoto.
- 64 test; tre giri di revisione in `prima-nota.md` §7.

## Fatto il 06/10/2026: v0.35.0 (Prima Nota)
Materiale letto: Gestionale APS del Dante e plugin `gestionale-acv` della
Compagnia (`claude/analisi-contabilita-esistente.md`, `acv-contabilita*.md`,
`acv-categorie-master.sql`). Tavole PN-1…PN-9 approvate da Paolo
(`docs/mockup/Tavole-prima-nota.pdf`, tela «Gestionale ASD — Riferimento
UI»). Scheda: `docs/specifiche/prima-nota.md`.
- Movimenti di cassa e banca con giustificativi multipli, giroconti,
  fornitori e tag «trova o crea», esportazioni CSV e PDF, importi letti
  all'italiana (`App\Support\Importo`).
- `gl_anni_contabili` al posto di `gl_prima_nota_anni_chiusi`: saldi di
  apertura scritti a mano **solo per il primo anno** (per la Compagnia il
  2027, dai saldi al 31/12/2026 di `gestionale-acv`), poi ereditati;
  chiusura dopo l'approvazione del rendiconto con lista di controllo;
  correzioni su anno chiuso motivate e registrate; riapertura solo del
  Presidente.
- 33 categorie di `gestionale-acv` (`PrimaNotaCategoriaSeeder`, 23 attive)
  con `uso` (movimenti, solo bilancio, sistema) e natura da verificare;
  pagina Categorie e tag. Codice `QUOTE` ora presente: l'iscrizione da
  domanda scrive la quota con la sua categoria.
- Difetto trovato dai test: un metodo `anno()` nel model bloccava in
  silenzio l'audit (ora `annoMovimento()`; regola in `prima-nota.md` §4).
- 61 test su MySQL; tre giri di revisione critica (in `prima-nota.md` §7).
- Correzioni di documentazione: provenienza degli scontrini
  (`gestionale-acv`, non il Dante) in `scontrini.md` e MANUALE §6.
- **Azzeramento**: prima di scrivere i saldi 2027 vanno svuotati i
  movimenti di prova (anche quelli creati dalle domande di prova),
  `gl_anni_contabili`, `gl_tag`, i fornitori di prova e
  `storage/app/private/prima-nota/` (lista di collaudo, J12): altrimenti
  il 2026 risulta primo anno.

Decisioni di Paolo (06/10/2026), dettaglio in
`claude/analisi-contabilita-esistente.md`:
- `gestionale-acv` va in pensione dal 01/01/2027: si migrano **solo i
  saldi** di cassa e banca al 31/12/2026 (query pronta nell'analisi §6);
- categorie: **si tengono tutte e 33**; «Disavanzo/Attivo anno
  precedente» solo in preventivo e rendiconto; «Uscite da classificare»
  di sistema per gli scontrini;
- OCR.space: stessa chiave di `conta_impostazioni.ocr_space_api_key`,
  in `.env` o Impostazioni, mai nel codice;
- consuntivo 2026 chiuso in `gestionale-acv`; preventivo 2027 nel
  Gestionale ASD se pronto in tempo;
- **si tiene tutto**: anticipi con nota di rimborso, riconciliazione
  bancaria, chiusura mensile della cassa (dall'APS) e tag (`gl_tag`,
  separato da Progetti).

Regole di lavoro che restano: tavole sulla tela prima del codice (PDF
delle tavole nuove se la tela è troppo grande); niente `style=` nelle
viste; verifica a 1440 e 375 px; tre giri di revisione critica prima di
ogni consegna, dichiarandone i risultati; ZIP con la struttura del
progetto e LEGGIMI; documentazione sempre aggiornata (scheda, ui.md,
MANUALE, lista di collaudo, bootstrap); ogni analisi o decisione
documentata; le modifiche da riportare al Gestionale APS in
`da-riportare-gestionale-aps.md`.

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
  (prima versione pubblica) o 1.1. L'assenso del minore dai 14 anni
  (art. 6.3) è nel modulo di domanda dalla v0.31.0.
- Azzeramento finale prima dell'apertura al pubblico (Paolo, 29/09/2026:
  oggi è tutto in prova): domande, voti, email, verbali, soci, rinnovi e
  movimenti di prova (con anni contabili, tag, fornitori e giustificativi:
  J12), rinnovi, ricevute e invii dei promemoria (K11), tessere e pacchetti
  (L12), rinnovi dello Scadenzario e scadenze di prova (M12), registro di
  audit;
  modalità test spenta. Procedura
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
- Modulo Verbali: serve anche per scegliere il verbale di chiusura
  dell'anno contabile (prima chiusura: il 2027, nel 2028).
- Campo del documento dei saldi di apertura (Anni contabili): è ancora il
  campo file del browser; da allineare allo stile di
  `x-campo-giustificativi` se Paolo lo chiede.
- Numero di ricevuta della prima quota (modulo Rinnovi quota).
- Richiesta di riunione da un terzo dei consiglieri (art. 16.4 lett. f).
