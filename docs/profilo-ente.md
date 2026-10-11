# Profilo dell'ente (forma giuridica per periodi) e passaggio B.A.S. → A.S.D.

**Stato:** implementato (v0.32.0, 05/10/2026); statuto B.A.S. scelto (strada a)
**Introdotto in:** v0.32.0
**File coinvolti:**
- Migration: `2026_10_05_000090_create_gl_associazione_periodi_table`
- Model: `AssociazionePeriodo` (forme, regimi, voci a mano della lista di
  controllo)
- Servizio: `App\Services\ProfiloEnte` (periodo a una data, denominazione,
  firma, sede, versioni dei testi, moduli, lista di controllo)
- Controller: `ImpostazioniEnteController`; gate in `IscrizioneController`
- Viste: `impostazioni/ente`, `periodo`, `ente-dati`, `passaggio`;
  `iscrizione/chiuse`, `iscrizione/_prova`; carta intestata, layout
  dell'area riservata, layout pubblico, layout PDF, email
- Seeder: `ProfiloEnteSeeder` (i due periodi della Compagnia),
  `ImpostazioneSeeder` (`iscrizioni_aperte`)
- `config/navigazione.php` (condizione `'se'` per voce)
- Test: `tests/Feature/ProfiloEnteTest.php`
- Documenti analizzati: atto costitutivo e statuto della B.A.S. del
  24/07/2024 (firmati, forniti da Paolo il 05/10/2026); statuto ASD v1.0
  pubblicato (`resources/content/statuto/1.0.md`, testo della bozza 1.1)

## 1. Logica

Decisione di Paolo (05/10/2026):
- il gestionale entra in uso per la **B.A.S.** dal **1° gennaio 2027**;
  dal **1° gennaio 2028** l'ente diventa **A.S.D.** con lo stesso codice
  fiscale (90022610555);
- il passaggio all'ASD si progetta come una **personalizzazione valida per
  qualunque ente** che adotta il gestionale, non come una modifica fatta
  per questo caso;
- per la B.A.S. si assume il regime fiscale di un'**associazione sportiva
  non commerciale** (per le B.A.S. non c'è un regime specifico);
- fino all'avvio `arco.valconi.it` si tratta come produzione effettiva ma
  chiusa al pubblico; poi migrazione al nuovo dominio, già disponibile.

Oggi il gestionale dà per scontato di essere un'ASD in tre punti:
denominazione (`config/sito.php`, carta intestata, email, piè di pagina:
"Compagnia Arcieri Castel Viscardo A.S.D."), testi (statuto e informativa
dell'ASD) e moduli legati al RASD e alla L. 398/1991 (Compliance RASD,
rimborsi ai volontari comunicati al RASD, plafond 398). Tutto il resto è
neutro.

**Soluzione proposta**: un **profilo dell'ente per periodi**. Ogni periodo
ha una data di inizio e i dati che definiscono l'ente in quel tempo:
denominazione, forma giuridica, sede legale, iscrizione al RASD, regime
fiscale, ente di affiliazione, statuto in vigore. Da qui:
- ogni documento usa il periodo della **sua** data (una ricevuta del 2027
  ristampata nel 2028 dice ancora "B.A.S.");
- i moduli si accendono e si spengono in base al periodo, non al codice;
- il passaggio a un'altra forma è l'inserimento di un nuovo periodo, con
  una lista di controllo; alla data di inizio il gestionale cambia da solo.

## 2. Struttura dati

**Nuova tabella `gl_associazione_periodi`** (una riga per periodo; la
scheda `gl_associazione` resta per i dati che non cambiano con la forma:
codice fiscale, recapiti, banca, logo, firme):

| Campo | Tipo | Note |
|---|---|---|
| `dal` | date, unique | Inizio del periodo; il periodo finisce il giorno prima del successivo |
| `denominazione` | string | Completa, come negli atti: "Compagnia Arcieri Castel Viscardo B.A.S." |
| `denominazione_breve` | string, null | Per piè di pagina e "rispondi a", se diversa |
| `forma_giuridica` | enum | `associazione` (non riconosciuta, art. 36 c.c.: anche la B.A.S.), `asd`, `ssd`, `aps` |
| `sigla_forma` | string, null | Come compare dopo il nome: "B.A.S.", "A.S.D." |
| `sede_legale` | string | Forma ufficiale (atti, Agenzia delle Entrate) |
| `rasd_numero`, `rasd_data_iscrizione` | string/date, null | Solo `asd` e `ssd` |
| `regime_fiscale` | enum | `non_commerciale` (art. 148 TUIR), `l398` (opzione L. 398/1991), `ordinario` |
| `ente_affiliazione` | string, null | "CSEN" |
| `statuto_versione` | string | Versione in `resources/content/statuto/` accettata dalle domande del periodo |
| `note` | text, null | Atto che ha deciso il passaggio (assemblea, data) |

Periodi previsti per la Compagnia:

| Dal | Denominazione | Forma | Sede legale | Regime | Statuto |
|---|---|---|---|---|---|
| 01/01/2027 | Compagnia Arcieri Castel Viscardo B.A.S. | `associazione` (sigla "B.A.S.") | Campo di tiro "Franco Cortignani", località Macchietto, Viceno (statuto B.A.S. rivisto, art. 1; oggi via Carlo Zanetti 12) | `non_commerciale` | statuto B.A.S. rivisto (pubblicato come 1.0) |
| 01/01/2028 | Compagnia Arcieri Castel Viscardo A.S.D. | `asd` | Campo di tiro "Franco Cortignani", località Macchietto, Viceno, 05014 Castel Viscardo (TR) | `l398` (scelta di Paolo) | statuto ASD (bozza 1.1), o lo stesso testo con la nuova denominazione (art. 2.4 del testo B.A.S.) |

Correzioni collegate:
- `gl_associazione.forma_giuridica` e `AssociazioneSeeder` dicono ancora
  "Associazione di Promozione Sociale" (residuo del core): la colonna resta
  per compatibilità, ma la forma si legge dal periodo.
- `config/sito.php` oggi contiene denominazione, sede ufficiale e codice
  fiscale: passano al profilo. Restano in `config/sito.php` solo indirizzo
  e menu del sito web, immagini, email e dominio pubblici.

## 3. Comportamento / flusso

**Lettura del profilo.** `ProfiloEnte::al($data)` restituisce il periodo in
vigore a quella data (default oggi). Regole:
- documenti e email: la data del fatto (domanda presentata, versamento,
  verbale), non quella della stampa;
- pagine pubbliche e navigazione: il periodo di oggi;
- nessun periodo per la data (prima del primo): si usa il primo, con un
  avviso nella pagina Impostazioni.

**Moduli che dipendono dal periodo:**

| Elemento | `associazione` (B.A.S.) | `asd` / `ssd` | `aps` (core) |
|---|---|---|---|
| Dicitura "sportiva dilettantistica" | no | sì, solo con iscrizione RASD | no |
| Compliance RASD, rimborsi comunicati al RASD | nascosti | visibili | nascosti |
| Plafond e tracciabilità L. 398/1991 | solo con `regime_fiscale = l398` | idem | idem |
| Compliance RUNTS | nascosta | nascosta | visibile |
| Tesseramento all'ente di affiliazione | sì se c'è `ente_affiliazione` | sì | sì se c'è |

**Passaggio a un'altra forma** (procedura guidata, uguale per ogni ente):
Impostazioni › Ente › "Prepara un passaggio": nuovo periodo con data
futura, poi una lista di controllo che il gestionale verifica da solo dove
può:
1. statuto della nuova forma pubblicato in `resources/content/statuto/`
   (se si usano le domande online);
2. informativa con il nuovo titolare (denominazione e sede);
3. RASD: numero e data (per `asd`/`ssd`);
4. regime fiscale e, per `l398`, soglie del plafond nelle impostazioni;
5. affiliazione rinnovata con la nuova denominazione;
6. intestazione del conto e IBAN (bonifici delle quote);
7. dati per il sito web (piè di pagina) da aggiornare a mano.
Alla data di inizio non serve nessuna azione: il periodo nuovo vale da
sé. Le domande presentate prima conservano statuto e informativa che hanno
accettato.

**Statuto della B.A.S. a confronto con il gestionale** (letto il
05/10/2026):

| Tema | Statuto B.A.S. (24/07/2024) | Gestionale oggi (statuto ASD) |
|---|---|---|
| Ammissione | Domanda al Presidente, che decide; il Presidente o il Vicepresidente accettano subito, il CD ratifica periodicamente (artt. 4.a, 4.d, 8.d) | Voto telematico di ciascun consigliere entro 5 giorni, accoglimento d'ufficio, parità al Presidente |
| Diniego | Solo per gravi motivi, "che non devono essere verbalizzati né comunicati" (art. 4.c) | Motivato, comunicato entro 10 giorni, appello all'Assemblea |
| Quando si diventa socio | Delibera del Presidente, iscrizione nel libro e consegna della tessera (art. 4.d) | Versamento della quota entro 5 giorni dall'accoglimento e iscrizione nel libro |
| Durata dell'iscrizione | 12 mesi dall'ammissione, rinnovo automatico con la quota (art. 4.e) | Anno sociale (gennaio–dicembre), quota entro il 31 gennaio |
| Minori | "Assenso di un genitore" (art. 4.b) | Domanda del genitore; assenso del minore dai 14 anni (art. 16 D.Lgs. 36/2021, vale comunque per legge) |
| Consiglio Direttivo | 3 membri: Presidente, Vicepresidente, Segretario; delibera con 2/3 presenti (art. 7) | 3–5 membri, anche telematico |
| Modifiche dello statuto | Assemblea, maggioranza semplice (artt. 6.f, 10) | — |
| Sede legale | via Carlo Zanetti 12 (art. 1) | Campo di tiro "Franco Cortignani" |
| Arbitrato | Collegio di tre soci; terzo dal Giudice di Pace (art. 11) | Collegio; terzo dal Presidente del Tribunale di Terni |

Conseguenza: **con lo statuto B.A.S. la procedura online di ammissione del
gestionale non ha base statutaria** (decide il Presidente, non il CD con
voto telematico; il diniego non è motivato né comunicato, mentre il
gestionale lo comunica con appello). Strade:
- **(a)** l'Assemblea della B.A.S. adotta nel 2026 lo statuto rivisto
  (testo ASD 1.1 adattato alla B.A.S.: denominazione, nessun riferimento al
  RASD), a maggioranza semplice (statuto B.A.S. art. 10). Il gestionale
  resta con una sola procedura; nel 2028 basta la parte ASD.
  **Raccomandata.**
- **(b)** il gestionale supporta anche la procedura B.A.S. (decisione del
  Presidente, ratifica del CD, nessun voto telematico): una seconda
  procedura da sviluppare e mantenere per un anno.
- **(c)** nel 2027 niente domande online: soci inseriti a mano (con il
  link per il consenso privacy), domande online dal 2028.

**Chiusura al pubblico** (indipendente dal profilo): impostazione
"Iscrizioni aperte" (`iscrizioni_aperte`), spenta di default. Spenta:
`/iscrizione` mostra "Iscrizioni online non ancora aperte" con l'email della
segreteria; chi è collegato all'area riservata vede il modulo con l'avviso
"Iscrizioni chiuse al pubblico" e le sue domande nascono di prova. La apre
o la chiude il Presidente da Impostazioni › Ente.

## 4. Implementazione tecnica

- **Tabella `gl_associazione_periodi`**: `dal` (unico), `denominazione`,
  `sigla`, `forma_giuridica` (associazione, asd, ssd, aps),
  `forma_descrizione` (sotto il nome nella carta intestata), `sede_legale`,
  `rasd_numero`, `rasd_data_iscrizione`, `regime_fiscale` (non_commerciale,
  l398, ordinario), `ente_affiliazione`, `statuto_versione`,
  `informativa_versione`, `atto`, `spunte` (json: voci a mano della lista
  di controllo, con la data). Un periodo finisce il giorno prima del
  successivo. I dati fissi (codice fiscale, recapiti, banca) restano in
  `gl_associazione`.
- **`ProfiloEnte`**: `al($data)` sceglie il periodo con `dal` più recente
  non successivo alla data; prima del primo periodo vale il primo; senza
  periodi un profilo provvisorio da `config/sito.php` e dalle impostazioni
  delle versioni. I periodi si leggono una volta per richiesta (istanza nel
  container, azzerata dal model a ogni salvataggio: un processo lungo, come
  un worker delle code, non vedrebbe le modifiche; oggi non ce ne sono).
  Metodi: `denominazione`, `firma` ("… · Affiliata CSEN"),
  `descrizioneForma`, `sede`, `versione('statuto'|'informativa')`,
  `haModulo('rasd'|'l398'|'runts')`, `inVigoreOggi`, `fine`,
  `listaPassaggio`.
- **Chi legge il profilo**: email (intestazione e piè), mittente,
  layout pubblico (piè), layout dell'area riservata (barra in alto e piè),
  layout PDF e carta intestata (con la data del documento: la ricevuta
  usa la data della domanda), modulo di iscrizione (testi in vigore),
  schede dei soci (informativa in vigore). `config('sito.piede.intestazione')`
  e `config('app.name')` non si usano più nelle viste.
- **Testi in vigore**: le versioni di statuto e informativa vengono dal
  periodo (prima dalle impostazioni `statuto_versione_corrente` e
  `privacy_versione_corrente`, che restano solo per il profilo provvisorio).
  Il riepilogo manda con l'invio un'impronta (periodo e versioni): se tra
  il riepilogo e l'invio cambia periodo (mezzanotte del 31/12), la domanda
  non parte e la persona rilegge i testi.
- **Navigazione**: voce con `'se' => 'rasd'` (Compliance RASD), valutata
  con il periodo di oggi; i permessi restano quelli di `gl_ruoli_sezioni`.
- **Pagina Impostazioni › Ente** (sezione `impostazioni`, solo il
  Presidente modifica): ente in vigore oggi (o il primo periodo, con un
  avviso), dati fissi, tabella dei periodi con lo stato del passaggio,
  apertura delle iscrizioni online. Un nuovo periodo comincia da domani in
  poi (tranne il primo dell'ente); un periodo già cominciato cambia solo
  RASD e atto e non si elimina.
- **Lista di controllo del passaggio** (per i periodi futuri): voci
  verificate dal gestionale (statuto pubblicato; informativa pubblicata e
  con la denominazione del periodo; RASD per A.S.D./S.S.D.; soglie del
  plafond per la 398; ente di affiliazione) e voci a mano (affiliazione
  rinnovata, conto bancario, sito web). Alla data di inizio non serve
  nessuna azione.
- **Seeder**: `ProfiloEnteSeeder` crea i due periodi della Compagnia
  (B.A.S. dal 01/01/2027, A.S.D. dal 01/01/2028 con informativa 2.0 da
  pubblicare) se mancano.
- **Da fare**: il monitoraggio del plafond 398 deve contare solo i giorni
  dei periodi `l398` (quando si scrive il modulo Prima Nota/plafond).

**Differenze rispetto alle tavole** (05/10/2026): l'apertura delle
iscrizioni è un pulsante e non un interruttore; il riquadro "prova il
modulo" della pagina chiusa è sostituito dall'avviso sul modulo per chi è
collegato; le versioni dei testi sono nei periodi e non più nelle
impostazioni.

## 5. Conformità e sicurezza

- **Denominazione**: la dicitura "associazione sportiva dilettantistica"
  (e la sigla A.S.D.) è quella degli enti costituiti con i requisiti del
  D.Lgs. 36/2021 (art. 7) e iscritti al RASD (D.Lgs. 39/2021): prima del
  passaggio nessun documento deve riportarla. Riferimenti da far
  confermare al commercialista.
- **Titolare del trattamento**: l'informativa deve nominare l'ente del
  periodo (B.A.S. nel 2027, con la sua sede legale); al passaggio serve
  un'informativa nuova, versionata.
- **Regime fiscale**: per la B.A.S. nel 2027 si assume ente non commerciale
  (quote e corrispettivi specifici dei soci non imponibili, art. 148 TUIR,
  se rispettate le clausole statutarie); l'opzione 398 dal 2028, con
  l'ASD. Da confermare con il commercialista.
- **Audit**: inserimento e modifica dei periodi registrati (evento
  `PROFILO_ENTE_MODIFICATO`), senza dati personali.

## 6. Interazioni con altri moduli

- **Domande di iscrizione**: statuto e informativa accettati dipendono dal
  periodo; la procedura di ammissione dipende dalla strada scelta (§3).
- **Soci e Rinnovi**: con lo statuto B.A.S. l'iscrizione dura 12 mesi
  dall'ammissione; con lo statuto ASD l'anno sociale. Da allineare se si
  sceglie (b) o (c).
- **Tesseramento**: ente di affiliazione dal periodo.
- **Scadenzario** (v0.38.0, `specifiche/scadenzario.md`): riga «Affiliazione
  e assicurazione» della pagina con i riquadri «Affiliazione all'EPS» (ente
  dal periodo, codice, referente e contatto, scadenza e ultimo rinnovo dallo
  Scadenzario) e «Polizza RC» (compagnia, numero, copertura); avviso rosso
  con l'affiliazione scaduta. Tesoriere e Segretario vedono la pagina in sola
  lettura.
- **Compliance RASD, Volontari, Prima Nota (plafond)**: visibili o attivi
  secondo il periodo.
- **Gestionale Core**: il profilo per periodi vale per qualunque derivato;
  da portare nel core e registrare in `SYNC-LOG.md`.

## 7. Storia ed evoluzione

- 05/10/2026: analisi. Decisioni di Paolo: avvio con la B.A.S. il
  01/01/2027, passaggio all'A.S.D. il 01/01/2028 come personalizzazione
  generale; nome completo "Compagnia Arcieri Castel Viscardo B.A.S." (Base
  Associativa Sportiva); regime fiscale della B.A.S. come ASD non
  commerciale. Atto costitutivo e statuto della B.A.S. (24/07/2024) letti:
  sede legale in via Carlo Zanetti 12; primo Consiglio Direttivo Massimo
  Papini (Presidente), Andrea Papini (Vicepresidente), Paolo Zanetti
  (Segretario); oggi il Presidente è Andrea Papini (Paolo, 05/10/2026);
  ammissione decisa dal Presidente con ratifica del CD.
- 05/10/2026, decisione di Paolo: "bisogna adeguare la B.A.S. alle scelte
  che servono per l'ASD" (strada **a**). Preparato il testo B.A.S.
  (`docs/statuto/statuto-bas-bozza.md`, `.docx`, `ACV-Statuto-BAS-bozza.pdf`): il
  testo ASD 1.1 con queste differenze:
  - art. 1.1: la B.A.S. costituita il 24/07/2024 adotta il nuovo statuto
    in sostituzione del precedente; nessuna dicitura "sportiva
    dilettantistica";
  - art. 2.4: chiede l'iscrizione al RASD quando ne ha i requisiti, e con
    l'iscrizione l'Assemblea delibera la denominazione A.S.D. (il passaggio
    del 2028 non richiede un nuovo statuto, salvo diverse indicazioni del
    commercialista);
  - art. 3.4: attività diverse nei limiti per gli enti non commerciali e,
    dall'iscrizione al RASD, secondo l'art. 9 del D.Lgs. 36/2021;
  - art. 31: norme transitorie (sostituisce lo statuto del 2024; soci e
    fondatori; CD eletto il 24/07/2024, con le cariche in essere
    all'adozione — Presidente oggi Andrea Papini, Paolo 05/10/2026 —, in carica per quattro
    anni dalla nomina, cioè fino al 24/07/2028: l'indicazione "per il primo
    anno" dell'atto costitutivo era una norma transitoria legata alla
    costituzione, superata dallo statuto, art. 7.b — Paolo, 05/10/2026;
    iscrizioni in corso valide fino al 31/12/2026, poi quota annuale);
  - sede legale al campo di tiro (art. 1.2, invariato rispetto al testo
    ASD): con l'adozione la sede passa da via Carlo Zanetti 12.
  Adozione: Assemblea della B.A.S. a maggioranza semplice (statuto 2024,
  artt. 6.f e 10), entro il 2026.
- Il testo B.A.S. è pubblicato sulla pagina `/statuto` come **1.0** (regola
  dei testi prima dell'apertura): è quello che accetterà chi presenta
  domanda nel 2027. Informativa: titolare "Compagnia Arcieri Castel Viscardo
  B.A.S. (Base Associativa Sportiva)"; comunicazione al RASD solo
  dall'iscrizione. Provvisoriamente, finché non c'è il profilo per periodi:
  denominazione "… B.A.S." in `config/sito.php` (email, piè di pagina,
  carta intestata) e forma "Base Associativa Sportiva" sotto il nome nella
  carta intestata.
- v0.32.0 (05/10/2026): profilo per periodi implementato, pagina
  Impostazioni › Ente, lista di controllo del passaggio, chiusura delle
  iscrizioni al pubblico (spenta di default). Decisioni di Paolo: primo
  periodo dal 01/01/2027; il periodo A.S.D. tiene lo statuto 1.0 se il
  commercialista conferma che basta l'art. 2.4; informativa 2.0 con il
  nuovo titolare da pubblicare prima del 01/01/2028. Denominazione
  provvisoria in `config/sito.php` usata solo senza periodi.
- 05/10/2026, decisione di Paolo: la B.A.S. adotta lo statuto che sarà
  dell'A.S.D. con i soli adeguamenti per la denominazione. Il testo B.A.S.
  è l'**unico testo dello statuto da mantenere** (uguale a
  `resources/content/statuto/1.0.md`). I documenti dello statuto stanno in
  `docs/statuto/`: `statuto-bas-bozza.md`, `.docx`,
  `ACV-Statuto-BAS-bozza.pdf` e, come storia, `statuto-modifiche-v1.1.md`.
  Tolti la bozza A.S.D. 1.1 (`statuto-1.1-bozza.*`, `ACV-Statuto-1.1.pdf`)
  e la bozza del 2024 (`Statuto ASD - Bozza.*`).
- v0.32.1 (05/10/2026), dal collaudo (prova G3, Paolo): nella pagina dei
  dati fissi i campi vanno in due righe, la prima con codice fiscale,
  email, PEC e telefono, la seconda con banca, IBAN e BIC. Il riquadro
  "Dati fissi dell'ente" della pagina Impostazioni › Ente mostra anche il
  BIC, che prima mancava. Gli altri controlli del blocco G e le prove
  A11–A13 sono stati superati.
- v0.38.0 (11/10/2026, tavole SC-2, SC-3): riga «Affiliazione e
  assicurazione» (`impostazioni/_affiliazione-polizza`), finestre «Modifica»
  del Presidente (`updateAffiliazione`, `updatePolizza`, errori nei bag
  `affiliazione` e `polizza`), campi `rete_affiliazione_contatto` e
  `polizza_*` in `gl_associazione`. La scadenza non si scrive qui: la tiene
  lo Scadenzario. Sezione `impostazioni` in sola lettura per Tesoriere e
  Segretario (`SezioneSeeder`): vedono il profilo, «Nuovo periodo»,
  «Modifica» e i pulsanti delle iscrizioni restano al Presidente.
