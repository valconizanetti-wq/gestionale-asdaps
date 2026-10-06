# Domande di iscrizione online

**Stato:** collaudo della v0.30.3 concluso il 30/09/2026 (modulo pubblico,
voto del CD, comando orario, area riservata); assenso del minore dai 14
anni (v0.31.0) da collaudare; da fare il link personale per il consenso
privacy dei soci senza domanda online
**Introdotto in:** v0.13.0 (2026-09-22) — allineamento `tipo_socio`;
v0.26.0 (2026-09-27) — analisi del flusso online, migration e model;
v0.27.0 (2026-09-28) — modulo pubblico: rotte, controller, viste, email,
ricevuta PDF; v0.30.0 (2026-09-29) — domanda valida con l'invio, voto
del CD, esiti, area riservata, iscrizione nel libro soci;
v0.30.2 (2026-09-29) — correzioni dal collaudo A1 (errore del codice
fiscale, limiti per IP); v0.30.3 (2026-09-29) — collaudo A1, A6, A8:
ricevuta su carta intestata, statuto e informativa da aprire prima
dell'invio, votazione senza consiglieri in carica; v0.30.4 (2026-10-04) —
statuto pubblicato con le modifiche unite, lista di collaudo allineata;
v0.30.5 (2026-10-04) — ora italiana (`config/app.php`); v0.31.0
(2026-10-04) — assenso del minore dai 14 anni
**File coinvolti:**
- Migration: `2026_09_22_000077` (alter `gl_domande`);
  `2026_09_27_000083` (campi della domanda online e stato `decaduta`),
  `2026_09_27_000084` (`gl_voti_cd`: membro CD e flag Presidente),
  `2026_09_27_000085` (`gl_soci`: `tutore_codice_fiscale`, `fondatore`)
- Model: `Domanda.php` (riscritto, con la regola dell'esito), `VotoCd.php`
  (completato)
- Modulo pubblico (v0.27.0):
  - rotte: `routes/iscrizione.php` (incluso da `web.php`);
  - controller: `IscrizioneController`;
  - request: `IscrizioneDatiRequest`, `IscrizioneInvioRequest`;
  - regola: `Rules/CodiceFiscaleValido`;
  - servizi: `Services/IscrizioneOnline`, `Services/CodiceFiscale`,
    `Services/Documenti`, `Services/RicevutaDomanda`,
    `Support/InvioEmail`;
  - mail: `IscrizioneConfermaEmail`, `IscrizioneConfermata`,
    `IscrizioneNuovaDomandaSegreteria` (viste in `emails/iscrizione/`);
  - viste: `layouts/pubblico`, `components/passi`, `iscrizione/*`,
    `pdf/ricevuta-domanda`;
  - dati: `resources/data/codici-catastali.php` (comuni e Stati esteri,
    anche soppressi, dal pacchetto python-codicefiscale, licenza MIT);
  - testi versionati: `resources/content/informativa/1.0.md`,
    `resources/content/statuto/1.0.md` (da esportare, vedi §4);
  - `config/stati.php` (dominio `domanda`), `ImpostazioneSeeder`,
    `resources/css/app.css`, `.env.example`
- Riferimenti analizzati: moduli CSEN "Domanda di adesione" e "Domanda di
  tesseramento" (adulti e minorenni); statuto 2021 e statuto v1.0;
  Gestionale APS (`IscrizioneController`, `DomandeController`,
  `VotoCDController`, `DomandaCartaceaController`, viste `iscrizione/`,
  `domande/`, email)
- Voto del CD e area riservata (v0.30.0):
  - migration `2026_09_29_000086` (campi di votazione, quota e promemoria
    su `gl_domande`; `consigliere_email` nullable, `link_inviato_il`,
    `token_cifrato` su `gl_voti_cd`; impostazione della promozione e
    categoria di Prima Nota "Quote associative");
  - servizi: `VotazioneCd` (apertura, voto, chiusura, esiti, decisioni del
    Presidente, solleciti, decadenza), `VerbaleAmmissione`,
    `IscrizioneSocio`, `Quota`;
  - controller: `VotoCdController` (pubblico), `DomandeController`
    (area riservata); rotte in `routes/iscrizione.php` (`voto-cd.*`) e
    `routes/web.php` (`domande.*`);
  - comando `domande:scadenze` (ogni ora, `routes/console.php`);
  - mail: `IscrizioneRicevuta`, `VotoCdInvito`, `VotoCdEsito`,
    `VotoCdConsiglieriSenzaEmail`, `DomandaRitirataConsiglieri`,
    `PromemoriaDiniego`, `DomandaAccolta`, `DomandaDiniego`,
    `SocioIscritto`, tratto `Mail/Concerns/MittenteCompagnia`; tema
    `sito` delle email (`resources/views/vendor/mail`);
  - viste: `voto-cd/*`, `domande/*`, `verbali/ammissione-telematica`,
    `iscrizione/inviata`, `iscrizione/gia-decisa`, `emails/voto-cd/*`,
    `emails/iscrizione/{ricevuta,accolta,diniego,iscritto}`;
  - tolti: `IscrizioneConfermaEmail`, `IscrizioneConfermata` e le loro
    viste, `iscrizione/email-inviata`;
  - test: `tests/Feature/VotoCdTest.php` (19 test, su MySQL);
  - `app.css` sezione 8, `config/stati.php`, `config/mail.php`,
    `config/sito.php` (riga della sede), `UserFactory` (allineata a
    `gl_users`).
- Documento collegato: `informativa-privacy-v1.0.md` (bozza);
  `statuto/statuto-modifiche-v1.1.md` (modifiche agli artt. 6 e 16 chieste dal
  nuovo flusso)

## 1. Logica

La domanda online è il **canale principale di adesione e di raccolta del
consenso privacy** (decisione di Paolo). Deve essere interamente online,
la più semplice possibile e formalmente corretta. Sostituisce il modulo
cartaceo CSEN "Domanda di adesione": ne raccoglie gli stessi contenuti e
produce una ricevuta PDF che ne fa le veci.

**Validità della domanda online.** La legge non impone una forma per la
domanda di adesione: la stabilisce lo statuto. Dalla v0.30.0 (decisione
di Paolo del 29/09/2026, "semplificare il processo") la "firma" si
ottiene con:
- l'invio del modulo con la dichiarazione obbligatoria, dopo il
  riepilogo: è la conferma sulla pagina, al posto del link via email;
- la ricevuta PDF della domanda, inviata all'indirizzo indicato e
  archiviata, con data, ora e IP dell'invio;
- il link nella stessa email per ritirare la domanda: chi non l'ha
  presentata può annullarla.

Insieme valgono come firma elettronica semplice. Fino alla v0.29 c'era
anche la conferma dal link entro 48 ore, che dimostrava che l'indirizzo
apparteneva al richiedente: tolta per semplificare, sapendo che ora una
domanda può arrivare al CD con un indirizzo sbagliato o a nome d'altri
(se ne accorge il titolare dell'indirizzo, che riceve la ricevuta e può
annullarla). Lo statuto pubblicato è allineato dal 04/10/2026 (art. 6.1:
domanda valida con l'invio; v0.30.4, `statuto/statuto-modifiche-v1.1.md`).

**Iter di ammissione** (statuto v1.0, art. 6, con le modifiche decise il
29/09/2026): il CD decide entro 5 giorni dalla presentazione e mette a
verbale il voto di ciascun consigliere; se alla scadenza non ha votato la
maggioranza dei componenti, la domanda è accolta d'ufficio; altrimenti
decide la maggioranza dei voti espressi; in parità prevale il voto del
Presidente e, se non ha votato, decide il Presidente. Il diniego è
motivato, comunicato entro 10 giorni, con appello all'Assemblea entro 30
giorni. L'ammissione si perfeziona con la quota versata entro 5 giorni (prima 30; Paolo, 30/09/2026) e
l'iscrizione nel libro soci.

## 2. Struttura dati

**Invariato** (v0.13.0): enum `tipo_socio` a 3 valori, allineato a
`gl_soci.tipo`.

**Uso dei campi esistenti:**
- `tipo_socio` si ricava **dall'età alla data della domanda**: `ordinario`
  o `minorenne`. Nel modulo non c'è scelta. `giovane` resta nell'enum ma
  non si usa, perché le quote sono solo due (§3).
- `consenso_privacy` registra la **presa visione dell'informativa**, non
  un consenso (vedi l'informativa, appendice).
- `consenso_arbitrato`: si tiene (decisione di Paolo), perché lo statuto
  ha una clausola compromissoria (art. 30 dello statuto v1.0). Lo imposta
  la dichiarazione unica del passo 2.
- `consenso_extra_ue`: si toglie dal modulo; la colonna resta e vale
  sempre `false`. Non ci sono trasferimenti fuori dall'UE.
- `token_conferma`: contiene l'**impronta SHA-256** del token del link,
  mai il token in chiaro. Resta anche dopo la conferma, così chi riapre il
  link vede "domanda già confermata" (v0.27.0, revisione).
- `documento_rilasciato_da`, `documento_data_rilascio`: restano vuoti
  ("per il momento lasciale vuote", Paolo). La scadenza del documento non
  si chiede.
- `data_nascita`, `sesso`, `luogo_nascita` (e gli stessi del genitore):
  **non si chiedono**, si ricavano dal codice fiscale sul server.
- `consenso_foto_video`: resta facoltativo.
- `informativa_hash`: nel Dante era una stringa `"v." + data` prodotta
  dal browser. Qui diventa un vero SHA-256 del testo dell'informativa,
  calcolato dal server.
- `scadenza_cd`: termine per il voto dei consiglieri (§3).
- `silenzio_assenso`: segna la domanda accolta d'ufficio allo scadere dei
  5 giorni (§3).
- `ricevuta_path`/`ricevuta_at`: la ricevuta PDF della domanda.
- `statuto_aperto_il` (v0.30.3, migration `2026_09_29_000087`): quando il
  richiedente ha aperto lo statuto, come `informativa_aperta_il`.

**Aggiunti in v0.26.0** (migration `000083`–`000085`):

| Tabella | Campo | Motivo |
|---|---|---|
| `gl_domande` | `esito_il`, `esito_comunicato_il` | Data dell'esito del CD (anche d'ufficio) e della comunicazione al richiedente: il diniego va comunicato entro 10 giorni (art. 6.5) |
| `gl_domande` | `quota_scadenza`, `quota_versata_il` | Il socio si crea solo al versamento della quota, entro 5 giorni dalla comunicazione dell'accoglimento (art. 6.6, confermato da Paolo) |
| `gl_domande` | stato `decaduta` | Accolta ma quota non versata nel termine. Distinto da `scaduta`, che resta per il link di conferma non usato entro 48 ore |
| `gl_voti_cd` | `governance_cd_id`, `presidente` | Serve sapere quale voto è del Presidente: in parità prevale il suo, e se non ha votato la domanda è accolta d'ufficio (artt. 6.4 e 16.4). Il flag si fissa all'apertura della consultazione |
| `gl_soci` | `fondatore` | Qualifica di socio fondatore (statuto art. 5.2). Non è una categoria di quota, quindi non va in `tipo` |
| `gl_domande` | `informativa_versione` (string 10) | La versione accettata va fissata alla domanda: l'informativa può cambiare prima dell'approvazione. Nel Dante veniva scritta su colonne inesistenti |
| `gl_domande` | `statuto_versione` (string 20) | Il modulo CSEN fa dichiarare di aver letto e accettato lo statuto |
| `gl_domande` | `documento_tipo`, `documento_numero`, `documento_rilasciato_da`, `documento_data_rilascio` | Sezione "documento" del modulo CSEN (decisione 8a). È il documento di chi firma: il richiedente, o il genitore se minorenne |
| `gl_domande` | `genitore_data_nascita`, `genitore_luogo_nascita`, `genitore_telefono` | Richiesti dal modulo CSEN per minorenni. Il telefono serve a `gl_soci.tutore_telefono`, obbligatorio per i minori (`soci.md`) |
| `gl_soci` | `tutore_codice_fiscale` (string 16) | Il CF del genitore è richiesto da CSEN per il tesseramento del minore, ma `gl_soci` non ha dove tenerlo |

**Aggiunti in v0.30.0** (migration `000086`):

| Tabella | Campo | Motivo |
|---|---|---|
| `gl_domande` | `votazione_chiusa_il`, `votazione_chiusura` | Quando e come si è chiusa la votazione (`scadenza`, `tutti`): serve al verbale e all'email dell'esito |
| `gl_domande` | `sollecito_inviato_il`, `promemoria_diniego_il` | Il comando orario non ripete sollecito e promemoria |
| `gl_domande` | `decisione_presidente_il` | Voti pari senza il voto del Presidente: data della sua decisione |
| `gl_domande` | `quota_importo`, `quota_anno`, `quota_promo` | Quota fissata all'accoglimento: importo, anno sociale e promozione di ottobre–dicembre. La quota del CD può cambiare dopo l'email |
| `gl_voti_cd` | `consigliere_email` nullable | Un consigliere senza email ha la sua riga e conta nel quorum come non votante (decisione di Paolo) |
| `gl_voti_cd` | `link_inviato_il` | Data dell'invio del link ("Link del …", "Invia di nuovo") |
| `gl_voti_cd` | `token_cifrato` | Token del link cifrato con `APP_KEY`, per rimandare lo stesso link (sollecito, "Invia di nuovo") senza invalidare quello già ricevuto |

Dati inseriti dalla migration se mancano: impostazione
`quota_promo_ultimo_trimestre` (attiva) e categoria di Prima Nota
"Quote associative" (codice `QUOTE`, DM39 `A1`).

Uso degli stati di `gl_domande.stato` (enum già esistente):
`in_attesa` = votazione in corso; `approvata` = accolta, quota da versare;
`da_rifiutare` = maggioranza contraria, il Presidente scrive il diniego;
`in_parita` = voti pari senza il Presidente, decide lui; `rifiutata` =
diniego comunicato; `decaduta` = quota non versata nel termine;
`in_verifica` e `scaduta` restano per le domande del vecchio flusso con
conferma via email. "Iscritta" non è uno stato: è `socio_id` valorizzato.

**Aggiunto in v0.31.0** (migration `2026_10_04_000089`):

| Tabella | Campo | Motivo |
|---|---|---|
| `gl_domande` | `assenso_minore_il` (datetime, nullable) | Quando il minore che ha compiuto 14 anni ha prestato l'assenso al tesseramento (art. 16, c. 2, D.Lgs. 36/2021; statuto art. 6.3). Coincide con l'invio (`created_at`). Vuoto per adulti e sotto i 14 anni. Il testo dichiarato comprende foto e video se `consenso_foto_video` è vero: nessun campo in più |

Non servono colonne per la polizza né per la richiesta di tesseramento
CSEN. La polizza base è compresa nella quota, e la dichiarazione è
obbligatoria, quindi è implicita nella domanda inviata.

## 3. Comportamento / flusso

**Modulo pubblico** (`/iscrizione`, senza login), in **due passi**
(semplificazione approvata da Paolo il 28/09/2026: "accorpa pure"):

1. **Dati** (`iscrizione.dati`). Prima scelta: "Per me" o "Per mio figlio
   o mia figlia". Per un minore compila il genitore (nome, cognome, codice
   fiscale, email, telefono), poi i dati del minore; l'email del minore è
   facoltativa e, se manca, in `gl_domande.email` va quella del genitore.
   Poi residenza e documento di chi firma (tipo e numero). Sotto il codice
   fiscale compare l'anteprima "nato il … a …" ricavata dal server
   (`iscrizione.codice-fiscale`). Niente "ripeti l'email": l'indirizzo si
   rilegge nel riepilogo del passo 2 (dalla v0.30.0 non c'è più la
   conferma dal link, vedi §1).

   Controlli sul server (`IscrizioneDatiRequest`):
   - codice fiscale valido (carattere di controllo, omocodia, comune
     esistente alla data di nascita);
   - un minore non può fare domanda per sé, un maggiorenne non può essere
     iscritto come minore, il genitore deve essere maggiorenne;
   - se nome e cognome non corrispondono al codice fiscale compare solo un
     avviso nel riepilogo (errori di battitura, doppi nomi, omocodie);
   - **doppioni sul codice fiscale**, non sull'email (due fratelli hanno
     la stessa email del genitore): blocca un socio non cancellato o una
     domanda attiva non di prova; una domanda mai confermata non blocca
     oltre la scadenza del suo link.

   I dati restano in sessione: nel database entra solo una domanda
   completa.
2. **Controlla e invia** (`iscrizione.riepilogo`). Riepilogo dei dati,
   quota in base all'età (70 € ordinario, 30 € socio giovane, tesseramento
   CSEN e polizza base compresi), poi:
   - **una sola dichiarazione obbligatoria**: domanda di ammissione,
     accettazione dello statuto (versione registrata) compresa la clausola
     arbitrale dell'art. 30, richiesta di tesseramento CSEN, presa visione
     della polizza base, per i minori l'autorizzazione alle attività,
     informazione sull'Assemblea annuale entro il 30 aprile;
   - **presa visione dell'informativa** (obbligatoria);
   - accanto alle due dichiarazioni il link a statuto e informativa (dalla
     v0.30.3). Aprirli **non è obbligatorio**: basta la dichiarazione
     "ho letto" (decisione di Paolo, 29/09/2026, dopo una prima versione
     con l'apertura obbligatoria: l'art. 13 GDPR chiede di fornire
     l'informativa, non di provarne la lettura). Su desktop si aprono in
     una finestra sulla pagina; su telefono nella loro pagina, che mostra
     "Torna alla domanda" e registra l'apertura in sessione
     (`iscrizione.letti`). Le spunte restano nel browser (sessionStorage)
     mentre si va e si torna. Se aperti si registrano `statuto_aperto_il`
     e `informativa_aperta_il` (ora della pagina o, per la finestra,
     dell'invio), altrimenti restano vuoti. L'ora delle dichiarazioni è
     quella del clic su "Invia la domanda" (`created_at`, orologio del
     server), riportata nella ricevuta;
   - **foto e video**: consenso facoltativo;
   - **dichiarazione del minore** (v0.31.0), solo se il minore ha compiuto
     14 anni al giorno dell'invio, obbligatoria: «Io, Nome Cognome, ho
     letto con il mio genitore lo statuto e l'informativa e sono d'accordo
     con la mia iscrizione alla Compagnia, con il tesseramento al CSEN [e,
     con il consenso del mio genitore, alla pubblicazione di foto e video
     che mi ritraggono].» La parte tra quadre compare (Alpine) solo se il
     genitore spunta foto e video. Badge con l'età ("Giulia ha 15 anni"),
     nota "La spunta la mette Giulia, o il genitore dopo averle chiesto se
     è d'accordo" e, in piccolo, i riferimenti normativi. Senza la spunta:
     «Serve la dichiarazione di Giulia: dai 14 anni il tesseramento
     richiede il suo assenso.» e la domanda non parte;
   - campo trappola nascosto contro i bot (`sito_web`).

**Invio** (v0.30.0): la domanda vale con il pulsante "Invia la domanda"
del passo 2. Nasce `in_attesa` con `scadenza_cd` = ora + 5 giorni, e
subito:
- ricevuta PDF (disco privato) allegata all'email "Abbiamo ricevuto la tua
  domanda di adesione" al richiedente (o al genitore), con il link per
  annullarla; se la ricevuta non si genera, la domanda resta valida e
  l'email lo dice;
- avviso alla segreteria (email dell'associazione) con il pulsante per
  aprire la domanda;
- apertura della votazione (sotto).
La pagina successiva è "Domanda inviata" (`iscrizione.inviata`), con data,
termine del Consiglio e indirizzo mascherato della ricevuta.

**Domande del vecchio flusso** (ancora `in_verifica`): il loro link di
conferma funziona ancora; alla conferma parte lo stesso "dopo l'invio"
(ricevuta con la riga "Confermata dal link", email, votazione). Oltre le
48 ore diventano `scaduta` e le cancella il comando orario dopo 30 giorni.

**Ritiro** dal link dell'email, finché la domanda non è iscritta nel
libro soci e, se accolta, fino al termine per versare la quota (dalla
v0.30.3, Paolo 30/09/2026; `Domanda::annullabile()`). Passato il termine
il link porta a "Iscrizione non annullabile" (se la quota non arriva la
domanda decade; dopo l'iscrizione si esce con il recesso, art. 9): pagina con pulsante (GET mostra,
POST agisce). La domanda e la ricevuta si cancellano subito, nel registro
degli invii l'indirizzo è anonimizzato; se la votazione era in corso, i
consiglieri ricevono "Domanda ritirata" e il loro link non vale più. Dopo
l'esito comunicato (rifiutata, decaduta) o l'iscrizione la pagina dice
"Domanda già decisa".

**Iscrizioni chiuse**: se il testo dell'informativa o dello statuto della
versione in vigore non è pubblicato in `resources/content/`, il modulo
risponde 503 "Le iscrizioni online non sono ancora aperte". Chi firma deve
poter leggere quello che accetta, e l'impronta va salvata.

**Voto del CD: telematico, per consigliere** (statuto artt. 6.4 e 16.4;
tavole "Voto 1–3" ed "Email").
- **Apertura** (`VotazioneCd::apri`): una riga di `gl_voti_cd` per ogni
  membro di `gl_governance_cd` in carica, con nome ed email presi dal
  socio collegato (istantanea), flag `presidente` fissato ora (carica che
  comincia con "Presidente": sì "Presidente e Tesoriere", no
  "Vicepresidente"), token personale di 64 caratteri. Ogni consigliere
  con email riceve "Consiglio Direttivo: domanda di adesione di …" con i
  dati essenziali del richiedente (nome, categoria, nascita, comune di
  residenza, termine) e il pulsante "Apri la pagina di voto". Stessa
  email per più consiglieri (come nel collaudo): un link per ciascuno,
  nessun raggruppamento per indirizzo. Consiglieri senza email: riga
  senza link, contano nel quorum come non votanti; il Presidente riceve
  l'elenco. Nessun consigliere in carica: nessun voto, alla scadenza
  accolta d'ufficio.
- **Pagina di voto** (`/voto-cd/{token}`, pubblica): dati essenziali del
  richiedente, termine, scelta Favorevole / Contrario; con "Contrario" la
  motivazione è obbligatoria (almeno 20 caratteri, al massimo 1000).
  Nessuna spiegazione della procedura (Paolo: "i consiglieri devono
  conoscerla"). Dopo il voto la pagina mostra il voto registrato (anche
  riaprendo il link); il voto non si cambia. Votazione chiusa prima del
  voto: "Votazione chiusa" con l'esito; link sconosciuto: "Link non
  valido". Il consigliere non vede i voti degli altri.
- **Registrazione**: domanda e voto bloccati (`lockForUpdate`) e
  ricontrollati (votazione aperta, termine non passato, voto non ancora
  espresso); salvati data, ora, IP e browser (art. 16.4 lett. e); evento
  `VOTO_CD_ESPRESSO` con solo id e voto, dopo il controllo di `is_test`.
  `VotoCd` resta senza observer.
- **Chiusura** (`VotazioneCd::chiudi`): alla scadenza (comando orario),
  o prima quando hanno votato tutti (controllo letto dal database dopo il
  salvataggio del voto). Nessuna chiusura a mano: con il quorum raggiunto
  si aspetta comunque la scadenza, perché tutti possano votare, e il
  Presidente interviene solo sulla parità e per scrivere il diniego
  (Paolo, 29/09/2026). L'esito è `Domanda::calcolaEsito()`:
  - voti espressi sotto la maggioranza dei componenti → **accolta
    d'ufficio** (`silenzio_assenso`);
  - maggioranza dei voti espressi → **accolta** o **da rifiutare**;
  - parità: prevale il voto del Presidente; se non ha votato →
    **decide il Presidente** (`in_parita`). Con 4 consiglieri il caso non
    si presenta (un pari con il quorum comprende per forza il suo voto):
    serve un Consiglio di 5.
  Alla chiusura: bozza di verbale, email dell'esito a ogni consigliere con
  il voto di ciascuno (tavola "Email — esito"); al Presidente, se serve il
  suo intervento, la richiesta con il pulsante "Apri la domanda" (se il
  Presidente non ha email, va alla segreteria).
- **Sollecito** al terzo giorno dalla presentazione, una volta sola, a
  chi non ha votato: stessa email con titolo "La votazione è ancora
  aperta" e lo stesso link.
- **Invia di nuovo** (area riservata): rimanda lo stesso link a un
  consigliere che non ha votato.

**Esiti** (tavole "Email — accoglimento", "— diniego", "Domanda — …"):
- **Accoglimento** (anche d'ufficio o per decisione del Presidente):
  subito l'email "La tua domanda di adesione è stata accolta" con importo,
  termine (30 giorni), intestatario, IBAN (dalla scheda Associazione; se
  manca, il segnaposto `IT XX …` e un avviso nella scheda), causale
  "Quota associativa {anno} – {Cognome Nome}". Importo, anno e promozione
  si fissano in quel momento sulla domanda.
- **Promozione di ottobre–dicembre** (decisione di Paolo del 29/09/2026):
  chi è accolto o versa da ottobre a dicembre diventa socio subito e la
  quota vale fino al 31 dicembre dell'anno dopo. Impostazione generale
  `quota_promo_ultimo_trimestre` (interruttore in fondo all'elenco delle
  domande) e casella nella scheda al momento dell'iscrizione.
- **Diniego** (maggioranza contraria): la domanda resta "Diniego da
  inviare"; il Presidente scrive la motivazione nella scheda e la invia.
  Le motivazioni dei singoli consiglieri restano solo nel verbale.
  L'email indica l'appello all'Assemblea entro 30 giorni e la
  conservazione per 12 mesi. Se dopo 7 giorni non è partito, il
  Presidente riceve un promemoria (il termine è di 10 giorni).
- **Parità senza il Presidente**: nella scheda il Presidente sceglie
  "Accogli la domanda" o "Non accogliere" (con motivazione); parte la
  relativa email.
- **Quota versata: iscrivi nel libro soci** (scheda, chi può modificare
  la sezione): data del versamento (non prima dell'esito, non nel
  futuro), modalità (bonifico, contanti, altro), eventuale promozione. In
  una transazione: socio (anagrafica, residenza, contatti, genitore come
  tutore per un minore, privacy `online` con data e IP della domanda e
  versione dell'informativa, scadenza della quota al 31/12 dell'anno
  coperto), rinnovo dell'anno coperto, movimento di Prima Nota (entrata,
  categoria "Quote associative", conto `banca` o `cassa` per i contanti,
  collegato a socio e rinnovo), domanda collegata al socio. Poi l'email
  "La tua iscrizione alla Compagnia Arcieri è completa". Bloccata per le
  domande di prova, per un codice fiscale già nel libro soci e per un
  anno di Prima Nota chiuso.
- **Decadenza**: accolta e quota non versata entro il termine → `decaduta`
  (comando orario).

**Area riservata** (`/domande`, sezione "domande"; tavole "Domande —
elenco" e "Domanda — …"):
- elenco con riquadri (Da fare, In votazione, Quota da versare, Nuovi
  soci dell'anno), filtri per stato, anno e testo, colonne presentata,
  richiedente (con il genitore per un minore), categoria, stato, voto del
  CD, prossima scadenza (in rosso quando serve un intervento);
- scheda: in alto l'azione del momento (decisione, diniego, quota), poi
  voto del CD (termine, quorum, voti, esito con i voti di oggi, voti di
  ciascuno con motivazione, "Invia di nuovo", bozza di verbale) e
  cronologia; a destra richiedente, genitore e dati della domanda;
  ricevuta PDF; domande di prova eliminabili;
- permessi: vedere con la sezione "domande"; reinviare link, iscrivere
  nel libro soci e cambiare la promozione con la modifica della sezione;
  decidere la parità e inviare il diniego solo con
  il ruolo `presidente`.

**Bozza di verbale** (`VerbaleAmmissione`, tipo `cd_ammissione`): oggetto,
modalità (link personali, termine, chiusura), tabella dei voti con data,
ora e numero del collegamento, motivazioni dei voti contrari, esito
(d'ufficio, maggioranza, decisione del Presidente), motivazione del
diniego, righe per le firme di Presidente e Segretario (art. 16.6). Si
aggiorna finché è in bozza; si legge dalla scheda della domanda.

**Comando orario** `domande:scadenze` (riscrive il `SilenzioAssensoCommand`
del Dante, che approvava anche con la maggioranza contraria, scriveva
nell'audit senza hash e aggiungeva un finto voto "Sistema"): chiusura
delle votazioni scadute, sollecito al terzo giorno, promemoria del
diniego, decadenza, cancellazioni per fine conservazione. Ripetibile:
ogni passo si segna e non si ripete. Opzione `--prova` per vedere cosa
farebbe.

**Minorenni**: presenta la domanda un solo genitore. L'adesione a
un'associazione sportiva per l'attività non agonistica è atto di ordinaria
amministrazione, che ciascun genitore può compiere da solo (art. 320
c.c.): niente dichiarazione "con il consenso dell'altro genitore" e niente
firma del minore (decisione di Paolo). Etichetta "socio giovane", valore
`minorenne`.

**Conservazione** (informativa §6): domande mai confermate (vecchio
flusso) cancellate dopo 30 giorni, non accolte o decadute dopo 12 mesi
dall'esito; nel verbale del CD restano nominativo, esito e motivazione.
Le cancella il comando orario (`cancellaPerFineConservazione()`), con
l'evento `DOMANDA_CANCELLATA_FINE_CONSERVAZIONE`.

**Domande di prova**: con `domande_modalita_test` attivo le domande nascono
`is_test`, non bloccano altre domande e non entrano nell'audit.

## 4. Implementazione tecnica

**Voto del CD e area riservata (v0.30.0):**
- **Rotte**: pubbliche `voto-cd.mostra` (GET `/voto-cd/{token}`,
  `throttle:60,1`) e `voto-cd.vota` (POST, `throttle:10,1`);
  `iscrizione.inviata` al posto di `iscrizione.email-inviata`; area
  riservata `domande.*` sotto `permesso:domande` (`index`, `show`,
  `ricevuta`, `verbale`, `reinvia`, `chiudi`, `parita`, `diniego`,
  `iscrivi`, `promozione`, `destroy`).
- **`VotazioneCd`**: unico punto che cambia lo stato di una domanda dopo
  la presentazione. Ogni cambio in transazione con `lockForUpdate` sulla
  domanda e nuovo controllo dello stato; email dopo il commit, con
  `InvioEmail` (un invio fallito non annulla voto o esito). Metodi:
  `apri`, `reinviaLink`, `vota`, `chiudi`, `decidiParita`,
  `inviaDiniego`, `sollecita`, `ricordaDiniego`, `faiDecadere`,
  `avvisaRitiro`; statici `caricaDiPresidente`, `emailPresidente`.
- **`Domanda`**: `calcolaEsito()` con la nuova costante `ESITO_PARITA`
  (tolto `esitoCerto()`: la chiusura anticipata ora è solo "hanno votato
  tutti"), `tuttiHannoVotato()`, `riepilogoVoti()`,
  `annullabile()`, `scadenzaDiniego()`, `emailDestinatario()`,
  `nomeDestinatario()`, `nomeCognome()`; costanti `GIORNO_SOLLECITO` (3),
  `GIORNI_PROMEMORIA_DINIEGO` (7), `CHIUSURA_*`.
- **`VotoCd`**: `assegnaToken()` (impronta SHA-256 per la ricerca, copia
  cifrata con `Crypt`), `tokenInChiaro()`, `nomeProprio()`; token nascosti
  nella serializzazione.
- **`Quota`**: importo per categoria, promozione (da ottobre), anno
  coperto, causale, segnaposto dell'IBAN.
- **`IscrizioneSocio`**: socio + rinnovo + Prima Nota in una transazione.
- **`VerbaleAmmissione`**: crea o aggiorna la bozza (solo se in bozza),
  testo dalla vista `verbali/ammissione-telematica` con i dati escapati.
- **Comando** `domande:scadenze`, pianificato `hourly()` con
  `withoutOverlapping()` in `routes/console.php`: sul server serve il
  cron di cPanel `php artisan schedule:run` ogni minuto (`deploy.md`).
- **Email**: tema `sito` e componenti in `resources/views/vendor/mail`
  (`ui.md` §3); `Reply-To` all'email dell'associazione con il tratto
  `MittenteCompagnia`.
- **Test** (`tests/Feature/VotoCdTest.php`): regola dell'esito, carica di
  Presidente, invio dal modulo pubblico, apertura (anche con consigliere
  senza email), voto e motivazione, link rimandato, chiusura con tutti i
  voti, attesa della scadenza con il quorum, accolta d'ufficio, diniego
  del Presidente, parità, nessuna chiusura a mano, sollecito e promemoria,
  decadenza e cancellazione, ritiro durante la votazione, iscrizione con
  Prima Nota e promozione, pagine dell'area riservata, domande di prova.
  Girano su MySQL (le migration usano istruzioni MySQL):
  `DB_CONNECTION=mysql DB_DATABASE=<db di test> php artisan test`.

**Modulo pubblico (v0.27.0–v0.29.x):**

- **Rotte** (`routes/iscrizione.php`), prefisso `iscrizione.`:
  - GET, `throttle:60,1`: `dati` (`/iscrizione`), `riepilogo`
    (`/iscrizione/controlla`), `email-inviata`, `conferma` e `annulla`
    (`/{token}`);
  - POST, `throttle:60,1`: `codice-fiscale` (anteprima, in POST perché in
    GET il codice fiscale finirebbe nei log di accesso del server);
  - POST, `throttle:10,1`: `dati.salva`, `invia`, `conferma.esegui`,
    `annulla.esegui`;
  - fuori dal prefisso: `informativa` (`/informativa-privacy`) e `statuto`
    (`/statuto`).
- **`IscrizioneOnline`**: `prepara()` ricava dal codice fiscale data,
  sesso e luogo di nascita; `crea()` assegna i campi uno per uno
  (`forceFill`, mai `fill($request->all())`), salva l'impronta del token,
  versioni e impronta dell'informativa, `is_test` dall'impostazione;
  `perToken()`, `conferma()` (con blocco della riga), `segnaScaduta()`,
  `annulla()`.
- **`CodiceFiscale`**: normalizzazione, analisi (controllo, omocodia,
  data, sesso, comune o Stato valido alla data di nascita), confronto con
  nome e cognome. Tabella dei codici catastali in
  `resources/data/codici-catastali.php` (circa 1 MB, caricata solo quando
  serve).
- **`Documenti`**: testi versionati in
  `resources/content/{informativa|statuto}/{versione}.md`; `hash()` è lo
  SHA-256 del file, `html()` lo converte con `Str::markdown` (HTML
  escapato). Per pubblicare una nuova versione: nuovo file e nuova
  impostazione `privacy_versione_corrente` o `statuto_versione_corrente`,
  senza toccare le versioni precedenti.
- **`InvioEmail`**: invia e registra in `gl_email_log` (oggetto mai
  salvato, errore troncato). Un invio fallito non annulla l'operazione.
- **Email**: modelli markdown di Laravel (`<x-mail::message>`), mittente
  dal `.env`, `Reply-To` all'email dell'associazione.
- **Assenso del minore** (v0.31.0): regola in `Domanda::assensoRichiesto()`
  (14 anni compiuti, non 18, al giorno indicato; il compleanno conta già),
  usata sia sui dati in sessione sia sulla domanda salvata
  (`richiedeAssensoMinore()`); costanti `ETA_ASSENSO_MINORE`,
  `NORMA_ASSENSO`, `NORMA_IMMAGINE`; testo in `partiTestoAssenso()` /
  `testoAssenso()` / `testoAssensoMinore()`, unico per modulo, ricevuta e
  documenti. `IscrizioneInvioRequest::assensoRichiesto()` ricalcola l'età
  all'invio (il minore può compiere 14 anni tra il passo 1 e l'invio) e
  rende `assenso_minore` `accepted`; `IscrizioneOnline::crea()` registra
  `assenso_minore_il` = `created_at` (stesso istante) solo se richiesto e
  dato: sotto i 14 anni il campo inviato si ignora. Mostrato in riepilogo
  (passo 2), ricevuta, scheda della domanda (riquadro «Domanda»: prestato,
  mancante o non richiesto) e bozza di verbale, sempre con il riferimento
  in piccolo (classe `.norma`, Paolo 04/10/2026). Domanda frequente «Mio
  figlio ha almeno 14 anni: cosa cambia?» (`testi.php`, terzo elemento
  facoltativo = riferimento normativo). Test: `AssensoMinoreTest` (5).
- **Ricevuta PDF** (`RicevutaDomanda`): dalla v0.30.3 su carta intestata
  della Compagnia (`pdf.carta-intestata`: logo, nome, recapiti dalla
  scheda Associazione o da `config/sito.php`), in una pagina: chi chiede,
  dati, dichiarazioni, cosa succede ora; in piccolo i dati della
  presentazione (data e ora in ora italiana, IP, indirizzo, apertura di
  statuto e informativa, impronte); salvata
  in `storage/app/private/domande/{id}/{nome casuale}.pdf` (disco
  `local`).
- **Impostazioni** (`ImpostazioneSeeder`, scrive solo le chiavi mancanti):
  `privacy_versione_corrente` e `statuto_versione_corrente` 1.0,
  `quota_ordinario` 70, `quota_giovane` 30, `domande_modalita_test` false.
- **Posta**: casella `arco@valconi.it` sul cPanel di `valconi.it`. In
  `.env` (la password solo lì, mai nel repository):
  ```
  MAIL_MAILER=smtp
  MAIL_SCHEME=smtps
  MAIL_HOST=mail.valconi.it
  MAIL_PORT=465
  MAIL_USERNAME=arco@valconi.it
  MAIL_PASSWORD=
  MAIL_FROM_ADDRESS=arco@valconi.it
  MAIL_FROM_NAME="Arcieri Castel Viscardo"
  ```
  Il mittente deve restare sul dominio della casella (DMARC).
- **Fuso orario**: `Europe/Rome` (`config/app.php`, `APP_TIMEZONE`;
  applicato davvero dalla v0.30.5), perché le 48 ore, i 5 giorni, la
  minore età e gli orari nella ricevuta sono termini dello statuto. Deve
  coincidere con il fuso della sessione MySQL (italiano su SupportHost e
  Laragon), così le date scritte dal gestionale e `NOW()`/`CURDATE()` delle
  query a mano parlano la stessa ora.
- **Model** (v0.26.0): `Domanda` con `#[ObservedBy([AuditObserver::class])]`,
  le costanti dei termini (5, 10, 30 giorni), `calcolaEsito()`,
  `esitoCerto()`, `cancellaPerFineConservazione()`, `$auditCampiConValore`;
  `VotoCd` con `perToken()`, `haVotato()`, `membroCd()`.
- **`AuditObserver`**: salta i record `is_test`, esclude i token, registra
  i valori solo dei campi elencati in `$auditCampiConValore`.
- **UI**: nessuno `style=` né `<style>`. Layout `layouts/pubblico`
  (topbar con logo, piè di pagina con i dati dell'associazione, `noindex`)
  e componente `<x-passi :corrente="1" />`. Classi nuove in `app.css`,
  `@layer components`:
  - struttura: `pubblico-main`, `pubblico-stretto`, `pubblico-colonne`,
    `pubblico-colonna`, `pubblico-laterale`, `pubblico-footer`,
    `pubblico-centrato`, `testo-secondario`;
  - passi: `passi`, `passo`, `passo-num`, `.fatto`, `.corrente`;
  - scelte: `scelta-gruppo`, `scelta-opzioni`, `scelta` (evidenziata con
    `:has(input:checked)`); anteprima del codice fiscale: `cf-anteprima`
    con `.ok` e `.ko`;
  - quota: `quota-box` con `-testo`, `-etichetta`, `-nota`, `-importo`;
  - dichiarazioni: `dichiarazione` (`.principale`),
    `dichiarazione-corpo`, `dichiarazione-elenco`, `campo-trappola`,
    `elenco-passi`;
  - esiti e testi: `esito-icona` (`.ok`, `.warn`), `documento-testo`.

  A 375 px le colonne diventano una sola e le tabelle dei testi scorrono
  in orizzontale. Descritte anche in `ui.md` §3, "Pagine pubbliche".

## 5. Conformità e sicurezza

**Voto del CD (v0.30.0):**
- **Link di voto**: token casuale di 64 caratteri; per la ricerca si usa
  solo l'impronta SHA-256 (`token_link`). Una copia cifrata con `APP_KEY`
  (`token_cifrato`) permette di rimandare lo stesso link: chi ha il
  database o un backup ma non il `.env` non può ricostruirlo. Se la
  chiave dell'app cambia, al primo reinvio si crea un link nuovo.
- **Audit**: eventi `VOTAZIONE_CD_APERTA`, `VOTO_CD_ESPRESSO`,
  `VOTAZIONE_CD_CHIUSA`, `DOMANDA_DECISIONE_PRESIDENTE`,
  `DOMANDA_DINIEGO_INVIATO`, `DOMANDA_DECADUTA`,
  `DOMANDA_CANCELLATA_FINE_CONSERVAZIONE`, `IMPOSTAZIONE_MODIFICATA`:
  solo id, voti ed esiti, mai nomi, email o motivazioni. Le domande di
  prova non vi entrano. I cambi di stato della domanda li registra anche
  l'observer (valori solo dei campi in `$auditCampiConValore`).
- **Minimizzazione**: il consigliere vede nella pagina e nell'email solo
  nome, categoria, nascita, comune di residenza e data della domanda;
  codice fiscale, recapiti e documento solo nell'area riservata.
- **Concorrenza**: voto, chiusura e decisioni bloccano la riga della
  domanda; due ultimi voti simultanei chiudono una volta sola; un voto
  arrivato dopo la chiusura non si registra.
- **Email e antivirus** (collaudo del 29/09/2026: Avast ha messo in
  quarantena l'email di conferma come "HTML:ExtortMail [Phish]"; causa
  principale DKIM e DMARC mancanti su `valconi.it`, sistemati con Repair
  in cPanel). Regole per i testi: mittente e firma della Compagnia (non
  "Gestionale"), motivo per cui si riceve l'email, niente minacce ("i dati
  vengono cancellati") né urgenze in grassetto, indirizzo del link
  scritto per esteso sullo stesso dominio, `Reply-To` all'associazione.
- **Verbale**: il testo HTML è generato solo dal gestionale, con i dati
  escapati; si mostra con `{!! !!}`. Se un giorno i verbali si potranno
  modificare a mano, servirà la sanificazione dell'HTML (come nel Dante).

**Modulo pubblico:**

- **Audit**: eventi `DOMANDA_ONLINE_INVIATA`, `DOMANDA_ONLINE_CONFERMATA`,
  `DOMANDA_ONLINE_ANNULLATA`, con solo id e stato nel dettaglio (entra
  nell'hash). Le domande di prova non vi entrano.
- **Token**: 64 caratteri casuali, salvati solo come SHA-256. Chi legge il
  database non può confermare o annullare domande.
- **Base giuridica**: la presa visione dell'informativa non è un
  consenso; l'unico consenso vero è per foto e video.
- **Firma**: invio dopo il riepilogo con la dichiarazione obbligatoria +
  ricevuta PDF inviata all'indirizzo indicato e archiviata (data, ora, IP)
  + link per ritirare la domanda = firma elettronica semplice (§1). La
  conferma dal link è stata tolta con la v0.30.0; lo statuto pubblicato
  (art. 6.1) è allineato dalla v0.30.4.
- **Anteprima del codice fiscale**: non legge il database, risponde solo
  con quanto il codice stesso contiene.
- **Limiti** per IP, ciascuno con il suo contatore (limitatori con nome in
  `AppServiceProvider`, dalla v0.30.2): pagine e anteprima del codice
  fiscale 120 al minuto; passo 1 → riepilogo 30 al minuto; invio,
  conferma, annullamento e voto 10 al minuto e 40 all'ora. Pagina
  `errors/429` nello stile del sito. Campo trappola.
- **Collaudo**: su `arco.valconi.it` solo dati di prova finché non ci
  sono dominio e provider definitivi con DPA (SupportHost responsabile ex
  art. 28 GDPR).
- Le integrazioni allo statuto chieste in v0.26.0 (domanda telematica,
  CD telematico, termine per decidere) sono entrate nello statuto v1.0
  (artt. 6 e 16).

## 6. Interazioni con altri moduli

- **Soci**: creazione del socio al versamento della quota, con privacy e
  tutore (§3). `tipo` ricavato dall'età.
- **Rinnovi quota e Prima Nota**: la prima quota del nuovo socio diventa
  un rinnovo dell'anno coperto e un movimento di entrata collegato
  (categoria "Quote associative"), come i rinnovi successivi. Il numero di
  ricevuta del rinnovo resta vuoto: lo assegnerà il modulo Rinnovi quota.
- **Governance CD**: consiglieri in carica e Presidente tramite `socio_id`
  (nome ed email dalla scheda socio); il Presidente per le azioni
  riservate è l'utente con ruolo `presidente`.
- **Verbali**: bozza di verbale della consultazione telematica
  (`gl_domande.verbale_cd_id`, tipo `cd_ammissione`); approvazione e
  libro dei verbali quando ci sarà il modulo Verbali.
- **Impostazioni**: versioni dei testi, quote, modalità di prova.
- **Anagrafica associazione** (`Associazione::istanza()`): piè di pagina
  pubblico, `Reply-To`, email della segreteria.
- **Tesseramento**: la domanda contiene la richiesta di tesseramento
  CSEN; rinnovo e tessera sono del modulo Tesseramento.
- **Rinnovi e tesseramento** (v0.31.0, da fare): l'art. 16 vale per ogni
  tesseramento, quindi anche al rinnovo annuale di un socio giovane che nel
  frattempo ha compiuto 14 anni serve il suo assenso. Da prevedere nei
  moduli Rinnovi quota e Tesseramento.
- **Link personale per il consenso** (soci senza domanda): realizzato nella
  v0.33.0 (`privacy-link.md`), con gli stessi testi della dichiarazione e
  di foto e video. All'iscrizione nel libro soci (`IscrizioneSocio`) il
  consenso a foto e video della domanda passa nella scheda del socio.

## 7. Storia ed evoluzione

- v0.13.0: allineato l'enum `tipo_socio` (vedi sotto, testo originale).
  Iter di stato e voto CD lasciati invariati ("lasciamo com'è").
- v0.26.0 (27/09/2026), analisi del flusso online a partire dai moduli
  CSEN e dallo statuto. Decisioni di Paolo:
  - tutto online, nel modo più semplice e formalmente corretto;
  - polizza base compresa nella quota;
  - quote di 70 € (adulti) e 30 € (minorenni), tesseramento CSEN
    compreso;
  - voto telematico per consigliere;
  - documento d'identità con tipo e numero, senza copia;
  - dichiarazione dell'altro genitore solo se necessaria per legge (non
    lo è);
  - niente firma del minore;
  - `consenso_arbitrato` mantenuto.
- Emerso dall'analisi:
  - la creazione del socio nel Dante non copia né la privacy né il
    tutore;
  - `informativa_hash` del Dante non è un hash;
  - la versione dell'informativa veniva scritta su colonne inesistenti;
  - il dettaglio dell'audit conteneva dati personali;
  - l'informativa modello del CSEN è superata (riferimenti normativi,
    "responsabile" usato per il titolare, dati "su un PC in sede",
    consenso come base del trattamento principale). Riscritta:
    `informativa-privacy-v1.0.md`.
- Statuto 2021: **non è il riferimento** (decisione di Paolo). Il
  riferimento è lo **statuto v1.0**, chiuso il 27/09/2026 (MANUALE-ASD §8,
  punto 16), e il gestionale si adegua a quello. Articoli rilevanti per
  questo modulo: 6 (ammissione), 16 (consultazione telematica), 26
  (comunicazioni via email). Sede e denominazione sono
  quelle indicate da Paolo (campo di tiro "Franco Cortignani"), non
  quelle dello statuto 2021.

- v0.27.0 (28/09/2026), modulo pubblico. Decisioni di Paolo:
  - due passi invece di quattro ("accorpa pure"), con una sola
    dichiarazione obbligatoria;
  - data, sesso e luogo di nascita ricavati dal codice fiscale;
  - `documento_rilasciato_da` e `documento_data_rilascio` vuoti per ora;
    scadenza del documento non richiesta;
  - email del minore facoltativa.
  Tavole di design approvate prima del codice (tela "Gestionale ASD —
  Riferimento UI", `Iscrizione-*`). Revisione critica prima della
  consegna: vedi in fondo.

### Testo originale v0.13.0 (allineamento `tipo_socio`)

L'enum `tipo_socio` di `gl_domande` aveva 5 valori (`ordinario`,
`ordinario_rivista`, `giovane`, `minorenne`, `vitalizio`), mentre
`gl_soci.tipo` ne ha 3. Ridotto a `ordinario`, `giovane`, `minorenne`
via `DB::statement` (MySQL non modifica un ENUM tramite `Blueprint`).
- `ordinario_rivista` era un residuo del Dante.
- `vitalizio` è stato escluso esplicitamente da Paolo.

Unico caso, nella revisione sistematica, in cui il confronto tra due
tabelle del core ha rivelato un disallineamento reale non documentato.

### Revisione critica di v0.26.0 (28/09/2026)

Rilettura di migration e model prima del collaudo. Corretti:

1. **Decisioni su dati vecchi**: `esitoAttuale()` ed `esitoCerto()` usavano
   la relazione `voti` già caricata. Dopo il salvataggio di un voto, nella
   stessa richiesta, avrebbero calcolato l'esito senza quel voto, e la
   votazione avrebbe potuto chiudersi con un esito sbagliato. Ora leggono
   sempre dal database. I contatori per le viste restano sulla relazione
   caricata, con l'avvertenza nel codice.
2. **Nessun consigliere registrato**: `esitoCerto()` restituiva "certo"
   con zero voti, e la domanda sarebbe stata accolta d'ufficio nello
   stesso istante della conferma. Ora si attende la scadenza dei 5 giorni.
3. **Token dei link nell'audit**: alla creazione di una domanda, `token`
   e `token_conferma` sarebbero finiti in `new_values`, leggibili da chi
   consulta l'Audit Log (anche il Revisore) e utilizzabili finché il link
   è valido. Aggiunti agli esclusi dell'`AuditObserver`, insieme a
   `token_link` per il voto del CD.
4. **Controllo `is_test` nell'observer**: leggeva il campo con
   `getAttribute()`, che con `Model::shouldBeStrict()` attivo lancerebbe
   un'eccezione sui model senza quel campo, fuori dal try, bloccando il
   salvataggio di soci e utenti. Ora legge l'array degli attributi.
5. **`down()` della migration 000083**: con domande già `decaduta`, il
   ritorno all'enum precedente sarebbe fallito. Ora le riporta a `scaduta`
   prima di modificare la colonna.

Da tenere presente nei controller:
- mai `Domanda::create($request->all())`: `$fillable` comprende `stato`,
  `is_test` e `socio_id`, che il modulo pubblico non deve poter
  impostare. Solo array validati e costruiti campo per campo;
- le operazioni del modulo pubblico non hanno utente autenticato, e
  `AuditLogService` registrerebbe "sistema". Passare
  `utente_nome = 'Richiedente (modulo pubblico)'` negli eventi di
  business, e valutare lo stesso per l'observer.

### Secondo giro di revisione (28/09/2026)

Rilettura mirata a sicurezza dei link, dati personali e coerenza con
informativa e statuto. Corretti:

1. **Token dei link in chiaro nel database.** Il Gestionale APS salva in
   chiaro i token di conferma email, annullamento e voto del CD. Chi
   accede al database o a un backup potrebbe confermare o annullare una
   domanda, o votare al posto di un consigliere. Qui si applica la stessa
   regola già decisa per il link del consenso: nel database solo lo
   SHA-256 del token, il token in chiaro solo nell'email.
   - `Domanda::hashToken()` e `VotoCd::perToken()` per la ricerca.
   - Le colonne esistenti (64 caratteri) bastano: nessuna migration.
   - Vale per `token`, `token_conferma` e `token_link`.
2. **Dati personali delle domande nell'audit oltre i termini
   dell'informativa.** L'observer registrava tutti i valori della domanda
   (anagrafica, documento, IP, motivazioni) in `old_values`/`new_values`,
   dove sarebbero rimasti per sempre. L'informativa (§7) promette invece
   la cancellazione delle domande non confermate entro 30 giorni e di
   quelle non accolte dopo 12 mesi.
   - Ora `Domanda` dichiara `$auditCampiConValore`: stato, date dell'iter,
     consensi, versioni, collegamenti a socio e verbale. Degli altri campi
     resta solo il nome.
   - Il meccanismo è nell'`AuditObserver` e vale per qualunque model che
     dichiari la proprietà.
   - Per i dati del socio resta valida la pseudonimizzazione già prevista
     (`audit-log.md` §8).

Verificato e confermato senza modifiche:
- la regola dell'esito sui casi limite: parità con Presidente che non ha
  votato, quorum esatto, consiglio vuoto;
- la minore età al giorno del diciottesimo compleanno: maggiorenne;
- migration: ordine di `after()` e `constrained()`, default e NOT NULL
  dell'enum `stato`, `down()` completi.

Da fare con i controller e i comandi:
- **Cancellazione programmata** delle domande non confermate (30 giorni)
  e non accolte (12 mesi), come promesso dall'informativa. Da aggiungere
  al comando giornaliero della scadenza, che chiamerà
  `Domanda::cancellaPerFineConservazione()` (terzo giro, sotto).
- **Consiglieri senza email**: ogni membro del CD in carica conta nel
  quorum anche se non può ricevere il link. All'apertura della
  consultazione va segnalato al Presidente chi non ha email, perché
  senza il suo voto l'accoglimento d'ufficio diventa più probabile.
- **Fuso orario**: termini e minore età dipendono da `APP_TIMEZONE`, che
  deve essere `Europe/Rome`.

### Terzo giro di revisione (28/09/2026)

Seguiti i dati di una domanda in tutte le tabelle in cui finiscono, per
verificare le promesse di conservazione dell'informativa. Trovato:

1. **Indirizzi email nel registro degli invii dopo la cancellazione.**
   `gl_email_log` ha `domanda_id` con `nullOnDelete`: cancellando una
   domanda, le righe restavano con indirizzo, nome e oggetto, che contiene
   il nominativo. L'helper del Dante vieta di cancellare questa tabella
   ("prova legale").
   - Soluzione: le righe restano come prova dell'invio, ma senza dati
     personali.
2. **Ricevuta PDF orfana.** Il file sul disco non era legato a nessuna
   cancellazione.
3. **Verbale del CD.** Nominativo, esito e motivazione di una domanda non
   accolta restano nel verbale, che è un libro sociale da conservare.
   - Corretto: l'informativa (§7) ora lo dice. Prima prometteva la
     cancellazione totale dopo 12 mesi.

Per 1 e 2: nuovo metodo `Domanda::cancellaPerFineConservazione()`, l'unico
punto che sa dove finiscono i dati di una domanda.
- I voti si cancellano con la domanda (cascade).
- Il registro degli invii viene anonimizzato; il file della ricevuta si
  elimina dopo il commit.
- Il verbale resta, e il registro delle operazioni è già senza dati
  personali.
- Rifiuta le domande collegate a un socio.

Verificato senza modifiche:
- la proprietà `$auditCampiConValore` non interferisce con gli attributi
  Eloquent;
- nelle migration, `after()` su una colonna aggiunta nello stesso
  `Schema::table` è accettato da MySQL;
- il filtro `is_test` dell'observer non può lanciare eccezioni.

Da ricordare nei controller:
- un solo voto con `presidente = true` per domanda: se il Presidente
  cambia durante la consultazione, vale chi era in carica all'apertura;
- `VotoCd` non ha l'observer, perché i voti delle domande di prova
  finirebbero nella catena. Ogni voto va registrato con un evento di
  business (`VOTO_CD_ESPRESSO`, solo id e voto), dopo il controllo
  `is_test`.

### Revisione critica di v0.27.0 (28/09/2026)

Giro di revisione sul modulo pubblico prima della consegna. Corretto:
1. **Doppia conferma**: due clic ravvicinati confermavano due volte (due
   ricevute, due avvisi). Ora la conferma blocca la riga e controlla lo
   stato, e il pulsante si disattiva al primo clic (anche su "Invia la
   domanda" e "Annulla").
2. **Link riaperto dopo la conferma**: mostrava "link non valido". Ora
   l'impronta del token resta e la pagina dice "già confermata", con lo
   stato; l'annullamento resta possibile solo prima della conferma.
3. **Link scaduto riaperto**: la seconda volta diceva "non valido"; ora
   dice sempre "scaduto".
4. **Modulo aperto senza i testi**: si potevano inviare domande senza
   statuto o informativa pubblicati (impronta vuota). Ora il modulo
   risponde 503 finché i due file mancano.
5. **Email del minore assente**: letta con `?? null` in `prepara()`, così
   non dipende dal fatto che la request passi o no i campi vuoti.
6. **Codice fiscale**: la regola `size:16` duplicava il controllo di
   `CodiceFiscaleValido` con un messaggio generico; tolta. Il campo accetta
   fino a 20 caratteri, per chi incolla il codice con gli spazi (la
   request li toglie).
7. **Messaggi di errore in inglese** se manca il pacchetto delle
   traduzioni: aggiunti i messaggi generici in italiano nella request.
8. **Link della segreteria verso `/domande/{id}`**, rotta che non esiste
   ancora: tolto il pulsante finché non arriva l'area riservata.

Verificato senza modifiche: nessuno `style=`, direttive Blade mai
attaccate a una parola, direttive e componenti bilanciati, classi CSS
tutte definite, `php -l` su tutti i file PHP.

### Correzioni dopo il collaudo locale (28/09/2026)

Segnalate da Paolo al primo collaudo:
1. **Asterisco dell'email mancante** per "Per me": l'obbligatorietà cambiava
   con Alpine (`x-bind:required`), ma l'asterisco lo disegna il componente
   solo con `required` lato server. Aggiunto a `x-campo` il parametro
   `obbligatorio-se="<espressione Alpine>"`, che fa seguire all'espressione
   sia l'asterisco sia l'attributo `required` (`ui.md` §3).
2. **Impronte nella ricevuta PDF fuori dalla cella**: DomPDF non spezza una
   stringa di 64 caratteri senza spazi. Ora le impronte sono stampate in
   gruppi di 8 caratteri separati da spazi, che vanno a capo; per
   confrontarle con `sha256sum` si tolgono gli spazi.
3. **Passi lontani su telefono**: i due tondi "1" e "2" andavano agli
   estremi dello schermo. Ora sono vicini e centrati a ogni larghezza.
4. **"Per me" con il codice fiscale di un minore**: l'errore arrivava solo
   dopo l'invio, in cima al modulo. Ora l'anteprima del codice fiscale
   dice anche se la persona è minorenne (`minorenne` nella risposta di
   `iscrizione.codice-fiscale`), e sotto il campo compare subito l'avviso
   "È un minorenne" con il pulsante "Compila come genitore": passa a "Per
   mio figlio o mia figlia", tiene i dati già inseriti (diventano quelli
   del minore) e porta alla sezione del genitore. Avviso simmetrico "È un
   maggiorenne" nel caso opposto. Il controllo del server resta.

### v0.28.0 — pagine pubbliche nello stile del sito (28/09/2026)

Richiesta di Paolo: le pagine di iscrizione devono avere il layout del
sito della Compagnia (`arciericastello.valconi.it`, temporaneo). Soluzione
scelta tra tre (Paolo: "va bene la prima"):
1. **scelta**: il modulo resta nel gestionale, su un sottodominio, con
   intestazione, menu, colori e piè di pagina del sito (`ui.md` §3, "Tema
   del sito"); il menu del sito porta alla pagina "Iscrizioni" del sito,
   che spiega come iscriversi e ha il pulsante verso il modulo;
2. scartata: gestionale dentro la cartella del sito sotto `/iscrizioni/`
   (WordPress e Laravel nella stessa cartella, riscritture e cookie
   condivisi, aggiornamenti che si rompono a vicenda);
3. scartata: modulo rifatto in WordPress che invia i dati al gestionale
   (controlli duplicati e un accesso dall'esterno al gestionale).

Menu del sito completo su desktop, a tendina su telefono; pulsante
principale verde (confermati da Paolo sulla tavola). Email dell'associazione
corretta: `arcieri.castelviscardo@gmail.com` (il link del sito puntava a un
indirizzo senza il punto).

Dopo il primo collaudo (Paolo: "le pagine vanno strette alla larghezza del
sito", "l'header identico al sito, non si deve notare il passaggio"):
intestazione e piè di pagina ricopiati dal tema figlio del sito
(`astra-child`, fornito da Paolo) con le stesse classi, regole e immagini;
contenuto dentro `page-wrap` da 1280 px, allineato ai bordi della barra
(`ui.md` §3).

### Revisione critica prima del deploy (28/09/2026)

Paolo ha chiesto se i tre giri di revisione erano stati fatti: dopo v0.27.0
no, le correzioni da v0.27.1 a v0.28.1 erano state controllate solo con
`php -l` e il bilanciamento di tag e direttive. Fatti i tre giri prima del
deploy:
1. **Correttezza**: le risposte dell'anteprima del codice fiscale potevano
   arrivare fuori ordine scrivendo in fretta, e mostrare il risultato di un
   codice precedente (anche l'avviso "È un minorenne"). Ora ogni richiesta
   ha un numero e le risposte superate si scartano. Tolto anche un commento
   JavaScript dentro l'attributo `x-data`.
2. **Sicurezza e privacy**: l'anteprima mandava il codice fiscale
   nell'indirizzo (`GET ?cf=…`), quindi nei log di accesso di Apache,
   conservati dal provider fuori dai termini dell'informativa. Ora è una
   POST con token CSRF, limite 60 al minuto.
3. **Coerenza e deploy**: nessuno `style=`, direttive e tag bilanciati,
   documentazione allineata alle rotte. Restano da fare fuori dal
   gestionale: la pagina `/iscrizioni/` del sito (oggi 404, e il menu del
   modulo ci porta), e il piè di pagina del sito che dice "B.A.S." (il
   gestionale lo ricopia uguale).

### v0.29.0 — pagina unica di iscrizione, modello Dante (28/09/2026)

Richiesta di Paolo: pagine più snelle e razionali ("il codice fiscale ha un
campo lunghissimo"), poi l'impostazione della pagina di iscrizione della
Dante: istruzioni in cima, modulo a schede, domande frequenti di lato.
Decisioni prese sulle tavole della tela ("Pagina unica di iscrizione"):
- **Una sola pagina nel gestionale** (soluzione A): la voce "Iscrizioni"
  del menu del sito porterà direttamente a `/iscrizione`. Scartata la
  presentazione in WordPress con un clic in più (B) e l'iframe (cookie
  bloccati, sessione persa).
- **In cima** "Come iscriversi" (4 passi) e "Perché associarsi"; su
  telefono sono blocchi a fisarmonica chiusi (`<details>`), su desktop
  affiancati e sempre aperti. Nella scheda 2 non si ripetono.
- **Testi** in `resources/content/iscrizione/testi.php` (rivisti da
  Paolo): si correggono senza toccare le viste. I termini (48 ore, 5, 10,
  30 giorni) vanno tenuti allineati allo statuto e alle costanti.
- **Schede** "1 Dati e quota" e "2 Consensi e invio": sono l'aspetto dei
  due passi, che restano pagine controllate dal server (nel passo 2 la
  scheda 1 ha la spunta ed è un link). Due schede, non tre come nella
  Dante: consensi e riepilogo stanno già insieme.
- **Selettore** "Chi si iscrive? Maggiorenne / Minorenne" a due pulsanti.
- **Fascia della quota** subito sotto il selettore, anche su telefono.
- **Campi larghi quanto il contenuto**: nome e cognome 150 px, codice
  fiscale 180 px con accanto il riquadro "Nato il 10/05/1990 a Terni
  (TR)" (solo data e luogo, con il bollino verde), email e telefono,
  residenza su una riga (indirizzo, comune, CAP 80 px, provincia 60 px),
  documento (tipo 200 px, numero 180 px). Una sola scheda per il modulo,
  con sezioni "Genitore che presenta la domanda" (minori), "Chi diventa
  socio" / "Minore che diventa socio", "Residenza", "Documento d'identità
  (del genitore)". Il documento è sempre l'ultima sezione, anche per i
  minori, perché i campi sono uno solo per i due casi.
- **Tolti** i testi di aiuto sotto l'email (resta "Facoltativa" per il
  minore), "Si versa dopo l'accettazione" e "Solo tipo e numero…".
- **Domande frequenti** a destra (sotto, su telefono), con la nuova "Serve
  il certificato medico?" (agonistico per le gare ufficiali di calendario).
- **Scheda 2**: riepilogo compatto (dati su tre colonne, link "Modifica"),
  dichiarazioni invariate, "aprilo **entro 48 ore**", pulsanti "Modifica i
  dati" e "Invia la domanda".

File: viste `iscrizione/dati` e `iscrizione/riepilogo` riscritte, nuovi
parziali `_intro`, `_faq`, `_schede`, `_codice-fiscale` (esito accanto al
campo, regione `aria-live` sempre presente), sezione 7 di `app.css`,
`x-dato` accetta attributi (classe), icona `giu`, anteprima del codice
fiscale nel formato "Nato il gg/mm/aaaa a …". Il componente `x-passi` e le
classi `quota-box`, `passi`, `pubblico-colonne`, `pubblico-laterale` non
sono più usati dal modulo.

Revisione in tre giri prima della consegna:
1. correttezza: bilanciamento di tag e direttive, variabili passate alle
   viste, ordine dei blocchi su desktop e telefono; corretta la gerarchia
   dei titoli (h1 pagina, h2 sezioni, h3 sottosezioni) e il bordo del
   riquadro del codice fiscale in caso di errore (il colore rosso veniva
   annullato);
2. sicurezza e privacy: nessuna rotta nuova; testi statici escapati; il
   codice fiscale viaggia ancora solo in POST;
3. regole UI: nessuno `style=`, direttive mai attaccate a parole, bersagli
   di tocco da 44 px su telefono (fisarmoniche, selettore, pulsanti),
   messaggio del server aggiornato a «Minorenne».

Ritocco dopo la consegna (Paolo, 28/09/2026): tolti a vista "Chi si
iscrive?" (resta solo per i lettori di schermo, come nome del gruppo di
pulsanti) e il titolo "Chi diventa socio" per l'adulto; il titolo "Minore
che diventa socio" resta quando la domanda è per un minore, per
distinguere i dati del minore da quelli del genitore.
Stessa regola nel riepilogo della scheda 2: il riquadro dell'adulto non ha
titolo (resta il link "Modifica", a destra); per il minore resta "Minore che
diventa socio".

### v0.30.0 — voto del CD, esiti, area riservata (29/09/2026)

Decisioni di Paolo in sessione:
- **processo semplificato**: la domanda vale con l'invio del modulo (la
  conferma è sulla pagina), niente conferma via email entro 48 ore; al
  richiedente solo la ricevuta con il link per annullare ("togli
  «finché il Consiglio non ha deciso»": si ritira finché non è iscritta);
- **esito**: senza la maggioranza dei consiglieri votanti accolta
  d'ufficio (come lo statuto); con il quorum decide la maggioranza dei
  voti espressi; maggioranza contraria → diniego scritto dal Presidente;
  parità → prevale il Presidente, e se non ha votato decide lui;
- **chiusura**: con il quorum si aspettano comunque i 5 giorni; da sola
  solo quando hanno votato tutti; il Presidente **non** chiude la
  votazione: interviene solo sulla parità (alla scadenza, o prima se hanno
  votato tutti) e per scrivere le motivazioni del diniego (Paolo,
  29/09/2026, dopo la consegna: tolto il pulsante "Chiudi la votazione");
- consiglieri senza email: nella votazione, contano nel quorum come non
  votanti;
- pagina di voto senza spiegazioni della procedura; niente variante "il
  tuo voto chiude la votazione", al suo posto l'email dell'esito a tutti
  i consiglieri;
- email nello stile delle pagine pubbliche;
- incasso della quota registrato in Prima Nota;
- promozione di ottobre–dicembre (socio subito, quota fino al 31/12
  dell'anno dopo), con impostazione generale e scelta per domanda;
- IBAN ancora da comunicare: segnaposto `IT XX …`;
- `Reply-To` all'email dell'associazione, lasciato com'è.

Le regole su conferma, parità e chiusura cambiano gli artt. 6.1, 6.4 e
16.4 dello statuto v1.0: testo proposto in `statuto/statuto-modifiche-v1.1.md`.

Tavole approvate prima del codice (tela "Gestionale ASD — Riferimento
UI", righe "Voto del CD — pagine pubbliche ed email" e "Area riservata —
domande, voto ed esiti"; estratto scaricabile in PDF).

Emerso in collaudo: le email partivano ma a usa.net finivano in
quarantena dell'antivirus ("HTML:ExtortMail [Phish]"). Cause: DKIM e DMARC
mancanti su `valconi.it` (sistemati da Paolo) e testi con i segnali tipici
delle truffe (scadenza stretta, minaccia di cancellazione, nome generico
"Gestionale ASD"). Testi riscritti con le regole del §5.

Revisione in tre giri prima della consegna:
1. **correttezza**: 19 test su MySQL, pagine controllate a 1440 e 375 px
   con dati di prova. Corretti: precedenza tra `.` e `??` nella cronologia
   (errore 500 sulle domande chiuse), plurali dei voti ("1 favorevoli",
   ora `riepilogoVoti()`), data di accoglimento nell'email quando decide
   il Presidente, ritiro di una domanda già decisa (ora "Domanda già
   decisa" anche dopo il POST), `UserFactory` allineata a `gl_users`;
2. **sicurezza e privacy**: nomi, cognomi e comune ora accettano solo
   lettere, spazi, apostrofi, trattini e punti (un nome come "[Vota
   qui](https://…)" sarebbe diventato un link nelle email ai consiglieri);
   token dei link solo come impronta più copia cifrata; azioni del
   Presidente controllate sul ruolo; audit senza dati personali; voto dopo
   la chiusura rifiutato sotto blocco della riga;
3. **regole UI**: nessuno `style=` né `<style>` nelle viste (l'unico CSS
   in linea è quello che Laravel copia nelle email dal tema), direttive
   mai attaccate a parole, etichette in grassetto, bersagli da 44 px e
   motivazioni a tutta larghezza su telefono.

Punti aperti:
- statuto v1.1 da adottare (vedi sopra);
- ricevuta numerata della prima quota: la assegnerà il modulo Rinnovi
  quota;
- la pagina "Impostazioni" non esiste ancora: la promozione si cambia
  dall'elenco delle domande, gli importi delle quote da tinker;
- prossimo passo: link personale per il consenso privacy dei soci senza
  domanda online.

### v0.30.1 — niente chiusura manuale della votazione (29/09/2026)

Decisione di Paolo dopo la consegna della v0.30.0: il Presidente non può
chiudere la votazione prima che tutti abbiano votato. Interviene solo
sulla parità senza il suo voto (alla scadenza, o prima se hanno votato
tutti) e per scrivere le motivazioni del diniego. Tolti il pulsante
"Chiudi la votazione", la rotta `domande.chiudi`, il metodo del
controller e il modo di chiusura `presidente`; il test ora verifica che la
chiusura manuale non esista. Aggiornati tavola "Domanda — in votazione",
`statuto/statuto-modifiche-v1.1.md` (art. 16.4 lett. d: "alla scadenza del
termine, o prima quando tutti hanno votato") e `MANUALE-ASD.md`.

### v0.30.2 — correzioni dal collaudo A1 (29/09/2026)

- Codice fiscale: l'errore (del server dopo "Avanti" o trovato nel
  browser) compare in rosso nel riquadro accanto al campo, al posto di
  data e luogo di nascita, non più sotto il campo. Modificando il codice
  spariscono testo e bordo rosso; codice incompleto segnalato all'uscita
  dal campo. `x-campo` ha l'opzione `errore-esterno`.
- 429 "Too Many Requests" dopo alcuni "Avanti / Modifica": con
  `throttle:N,1` senza nome Laravel usa un solo contatore per IP per
  tutte le rotte, quindi pagine, anteprima del codice fiscale e POST si
  sommavano. Ora limitatori con nome, uno per tipo di richiesta (§5), e
  pagina `errors/429`.
- 500 all'invio: non era codice. Sul server era rimasta la cache delle
  rotte della versione precedente (`Route [iscrizione.inviata] not
  defined`). In `deploy.md` §5 aggiunto `php artisan optimize:clear`
  prima delle cache.

### v0.30.3 — collaudo A1, A6, A8 (29/09/2026)

Richieste di Paolo dal collaudo:
- **Ricevuta** molto semplice e su carta intestata della Compagnia: nuovo
  layout `pdf/carta-intestata.blade.php` (logo e nome a sinistra, recapiti
  a destra, filetto verde e oro, piede con sede e codice fiscale); ricevuta
  riscritta in una pagina. Le ore ora sono in ora italiana (prima UTC,
  fuso dell'app).
- **Riquadro dell'errore del codice fiscale** alto quanto il campo (38 px,
  44 su telefono), testo più piccolo, al massimo due righe (testo intero
  al passaggio del mouse).
- **Tendina del tipo di documento** nello stile del sito: componente
  `<x-tendina>` al posto della select nativa (elenco del browser in stile
  di sistema). Tastiera e ruoli ARIA di una listbox. Tolto `overflow:
  hidden` da `.iscr-modulo`, che tagliava l'elenco.
- **A8**: link "Leggi lo statuto" come per l'informativa; su desktop
  finestra sulla pagina (`<x-modale larga>`), su telefono pagina del
  documento con "Torna alla domanda" (§3). L'obbligo di aprire i testi
  prima di spuntare, fatto in un primo momento, è stato tolto dopo il
  collaudo A: basta la dichiarazione (decisione di Paolo).
- **A6** (avviso ai consiglieri sul ritiro non arrivato): la causa non era
  il ritiro. La votazione non si era mai aperta (nessun `invito_voto_cd`
  in `gl_email_log`), perché all'invio non risultava nessun consigliere in
  carica collegato a un socio in `gl_governance_cd`, e `VotazioneCd::apri`
  si fermava in silenzio. Ora: errore nel log e un avviso (una volta per
  domanda, tipo `votazione_senza_cd`) al Presidente o alla segreteria; il
  comando orario apre le votazioni mancanti appena il CD è inserito; una
  domanda senza votazione aperta non si chiude alla scadenza (con zero
  consiglieri sarebbe stata accolta d'ufficio). Decisione di Paolo
  (29/09/2026): se la votazione si apre in ritardo, i 5 giorni restano
  contati dall'invio della domanda (statuto art. 6); `scadenza_cd` non
  cambia.

Ritiro dopo l'iscrizione (domanda di Paolo, 29/09/2026). Lo statuto non
parla del ritiro della domanda: il blocco dopo l'iscrizione è una scelta
del gestionale (`Domanda::annullabile()`). Dopo l'iscrizione la persona è
socio e per uscire c'è il recesso (statuto art. 9, c. 1, n. 1: dimissioni
scritte al CD, anche per email, efficaci dalla ricezione; quota non
restituita, art. 9, c. 3). Il ritiro invece cancella domanda e ricevuta,
che per un socio sono la prova di quanto ha sottoscritto e vanno
conservate. Deciso di tenere il blocco e di aggiungere, come punto aperto:

- **Recesso dal link della ricevuta**: dopo l'iscrizione la pagina del
  link, invece di "Domanda già decisa", propone il recesso. Pulsante di
  conferma (GET mostra, POST agisce), email alla segreteria e al
  Presidente con data e ora di ricezione, registro invii e audit; i dati
  della domanda restano. Il CD prende atto e lo annota nel libro soci
  (cessazione del socio con causa "recesso"). Nessuna modifica dello
  statuto: l'art. 9 ammette già le dimissioni per posta elettronica.
  Da decidere quando si fa: se registrare subito la cessazione o solo
  avvisare il CD; testo della pagina e dell'email; tavole sulla tela.

Indirizzo, sito ed email della Compagnia (Paolo, 29/09/2026), in
`config/sito.php`:
- indirizzo **pubblico** (sito, carta intestata, contatti, comunicazioni):
  Campo di tiro "Franco Cortignani" / Località Macchietto di Viceno, SP107
  / 05014 Castel Viscardo (TR). Lo usano la carta intestata (sempre, non
  la scheda Associazione) e il piè delle email;
- indirizzo **ufficiale** (atti, Agenzia delle Entrate, modello AA5/6,
  RASD): Campo di tiro "Franco Cortignani", località Macchietto, Viceno,
  05014 Castel Viscardo (TR). È già quello dello statuto (art. 1, c. 2);
- email `info@arciericastello.it`: valore predefinito di `SITO_EMAIL`
  (decisione di Paolo); sito definitivo `arciericastello.it`, da attivare
  nel `.env` con `SITO_URL` quando è online (fino ad allora il sito
  temporaneo).
- Informativa privacy aggiornata (richiesta di Paolo, 29/09/2026): sede
  nella forma ufficiale, email info@, allineata al flusso attuale (niente
  conferma dal link, apertura dei testi registrata, documento solo tipo e
  numero, conservazione della domanda ritirata e decaduta). Corretta
  direttamente la **1.0**: vedi la regola sotto. Restano i segnaposto di
  hosting (punto 4) e consulente fiscale (punto 5).

**Versioni dei testi prima dell'apertura** (decisione di Paolo,
29/09/2026). Finché il gestionale non apre al pubblico tutti i dati sono di
prova e saranno azzerati: informativa e statuto si correggono sul posto,
con il numero di versione che avranno all'avvio (oggi 1.0), senza creare
versioni intermedie. La regola "nuovo file e nuova impostazione, mai
toccare le versioni precedenti" (§4, `Documenti`) vale dall'apertura in
poi, quando le domande vere ne conservano versione e impronta.

Oggetto delle email ai singoli consiglieri (collaudo B, richiesta di
Paolo): nome e cognome del consigliere in testa ("Andrea Papini ·
Consiglio Direttivo: …") per invito, sollecito, esito della votazione e
domanda ritirata, così con una casella di prova sola si vede a chi è
arrivata ciascuna (`MittenteCompagnia::oggettoPer`).

Piè della carta intestata (collaudo, 29/09/2026): email e sito presi dai
valori definitivi di `config/sito.php` (`email_definitiva`,
`dominio_definitivo`) e non dalla scheda Associazione o da `SITO_URL`,
che sul server puntavano ancora alla casella e al sito temporanei. La
scheda Associazione resta la fonte dell'indirizzo della segreteria (avvisi
e "rispondi a") finché non viene aggiornata.

Scheda del socio e denominazione (Paolo, 29/09/2026):
- **Scheda socio**: per un socio nato da una domanda online, alla voce
  privacy dei documenti per l'iscrizione compare "Domanda online n. X del
  gg/mm/aaaa · informativa v1.0" (prima, per errore, "Raccolto di
  persona": la vista conosceva solo cartaceo e di persona), con i pulsanti
  "Ricevuta" (PDF) e "Domanda" (scheda in `/domande`), visibili a chi ha
  accesso alla sezione Domande. Usa `Socio::domandaOrigine`, caricata in
  `SociController@show`.
- **Denominazione**: sempre "Compagnia Arcieri Castel Viscardo A.S.D.",
  come nello statuto (`config/sito.php`, `piede.intestazione`): carta
  intestata (nome in testa e piede), piè delle email, nome del "rispondi
  a". Non dalla scheda Associazione. Restano dalla scheda l'intestatario
  del bonifico nell'email di accoglimento (deve essere quello del conto) e
  i dati per gli atti.

Termine per la quota ed email di accoglimento (Paolo, 30/09/2026, collaudo):
- termine per versare la quota dopo l'accoglimento: **5 giorni** (prima
  30), `Domanda::GIORNI_VERSAMENTO_QUOTA`; va nello statuto, art. 6, c. 6
  (`statuto/statuto-modifiche-v1.1.md`). Nello statuto pubblicato dalla v0.30.4
  (04/10/2026). L'appello contro il diniego resta
  a 30 giorni (art. 6.5);
- email "Domanda accolta" riscritta con il testo di Paolo: iscrizione
  effettiva, con assicurazione e accesso alle strutture, solo dopo il
  versamento; importo, termine, bonifico a "Compagnia Arcieri Castel
  Viscardo A.S.D." (denominazione di `config/sito.php`), IBAN, causale;
  tolta la frase sul contenuto della quota;
- conta il bonifico **disposto** entro i 5 giorni (statuto art. 6.6 come
  proposto), e la decadenza automatica scatta solo dopo altri 5 giorni di
  margine per accredito e registrazione (`GIORNI_MARGINE_DECADENZA`,
  `Domanda::dataDecadenza()`): termine il giorno 5, decadenza dal giorno
  11. Nella scheda della domanda, passato il termine: "Termine passato il
  … · decade il … se il versamento non è registrato". Email: "bonifico da
  disporre entro il …".

Email "Domanda ricevuta" (Paolo, 30/09/2026): testo semplificato, senza
riquadro di date: ricezione, trasmissione al CD (art. 6), esito per email
con le istruzioni per la quota, ricevuta PDF in allegato, link per
annullarla.

Messaggi di validazione in italiano (collaudo B3, 30/09/2026): il
progetto non aveva la cartella `lang`, e una regola senza messaggio
personalizzato mostrava la chiave ("validation.min.string"). Aggiunti
`lang/it/validation.php` (tutte le regole di Laravel 12, più i nomi dei
campi comuni), `auth.php`, `passwords.php`, `pagination.php`. Per la
motivazione del voto contrario, della parità e del diniego: "La
motivazione deve avere almeno 20 caratteri."

Scheda della domanda, riquadro "Voto del Consiglio Direttivo" (collaudo,
30/09/2026): tabella dei voti a tre colonne (consigliere con la carica
sotto, voto con data o stato del link sotto, azioni), senza scorrimento
orizzontale nella colonna della scheda; riquadri iniziali uno accanto
all'altro se c'è spazio, altrimenti due per riga, con il valore su una
riga sola (su telefono può andare a capo).

### v0.30.4 — statuto pubblicato con le modifiche unite (04/10/2026)

Richieste di Paolo dopo il collaudo, senza modifiche al codice:
- **Statuto pubblicato** (`resources/content/statuto/1.0.md`, pagina
  `/statuto`, testo che il richiedente accetta): ora è il testo completo
  della bozza 1.1 ("unisci le modifiche allo statuto"). Prima diceva ancora
  che la domanda vale con la conferma dal link (art. 6.1), che la parità
  senza il Presidente porta all'accoglimento d'ufficio (art. 6.4), che la
  votazione si chiude anche "quando l'esito non può più cambiare" (art.
  16.4 lett. d) e che la quota si versa entro 30 giorni (art. 6.6): regole
  diverse da quelle che il gestionale applica dalla v0.30.0. Entrano anche
  l'assenso del minore dai 14 anni (art. 6.3) e le clausole del modello
  CSEN (artt. 11.3, 14, 16.7, 22.5, 24-bis, 31). Numero di versione 1.0,
  per la regola dei testi prima dell'apertura (sopra, v0.30.3).
  Effetti: l'impronta SHA-256 dello statuto 1.0 cambia (le domande di
  prova già presentate conservano quella vecchia, ed è giusto così: è il
  testo che avevano accettato); il modulo non chiede ancora l'assenso del
  minore, che lo statuto pubblicato ora prevede (prossimo obiettivo).
- **Bozza 1.1** (`statuto-1.1-bozza.md`, `.docx`, `ACV-Statuto-1.1.pdf`;
  superata e tolta il 05/10/2026: resta solo il testo B.A.S. in
  `docs/statuto/`, vedi `profilo-ente.md` §7):
  art. 6.6 con il versamento "disposto entro 5 giorni" (prima 30), data
  della bozza aggiornata al 4 ottobre 2026. Il PDF è rigenerato dal Word
  con LibreOffice: impaginazione quasi identica; per quella di Word basta
  riesportarlo da Word. `statuto/statuto-modifiche-v1.1.md` ora è il registro delle
  modifiche recepite.
- **Copia in `docs/specifiche/`** di questa scheda: allineata (identica a
  quella in `docs/`).
- **Lista di collaudo** (`docs/test/test-domande-iscrizione.md`): allineata
  alla copia del progetto (v0.30.3: simulazioni con il giorno in più per il
  fuso, quota a 5 giorni con margine, reset, CD di prova via SQL), più la
  prova A9 sullo statuto pubblicato.
- **Cron** (`deploy.md` §6): uscita sempre su `/dev/null`; il `cron.log`
  del collaudo va tolto.

Notato nella revisione: `config/app.php` aveva `'timezone' => 'UTC'`
scritto a mano, quindi `APP_TIMEZONE=Europe/Rome` del `.env` non aveva
effetto. Corretto nella v0.30.5 (sotto).

### v0.30.5 — ora italiana (04/10/2026)

Decisione di Paolo: "il gestionale non è aperto e non ci sono dati reali,
perciò si possono fare tutte le modifiche necessarie".
- **Causa**: `config/app.php` aveva il fuso `'UTC'` fisso (valore di
  partenza di Laravel), e `APP_TIMEZONE` del `.env` era ignorato. Il
  gestionale ragionava in UTC, MySQL in ora italiana: due ore di
  differenza (una d'inverno). Effetti visti in collaudo: simulazioni con un
  giorno in più, ore della ricevuta da convertire a mano (v0.30.3).
  Effetti possibili con dati veri: termini (`scadenza_cd`,
  `quota_scadenza`), minore età e controlli "oggi" sbagliati tra mezzanotte
  e le 2; date nelle query a mano sfasate rispetto al gestionale.
- **Correzione**: `'timezone' => env('APP_TIMEZONE', 'Europe/Rome')`. Nessun
  altro punto del codice fissa il fuso: le viste usano
  `config('app.timezone')`; la ricevuta converte in `Europe/Rome`, che ora
  non cambia nulla e resta come garanzia. Cambio dell'ora legale gestito
  da Carbon (`addDays` conta giorni di calendario).
- **Database**: nessuna migration. Le colonne `TIMESTAMP` e `DATETIME`
  conservano la stringa scritta dal gestionale; la sessione MySQL resta nel
  fuso del server. Non si imposta `timezone` nella connessione: un offset
  fisso (`+02:00`) sbaglierebbe d'inverno.
- **Audit log**: la catena non si rompe. L'hash si calcola sulla riga
  riletta dal database (`created_at` come stringa salvata), e la verifica
  rilegge la stessa stringa: il fuso dell'app non entra nel calcolo.
- **Dati di prova già presenti**: scritti in UTC, d'ora in poi letti come
  ora italiana, quindi due ore indietro. Non si convertono: si azzerano con
  il reset della lista di collaudo (domande, voti, email, verbali); le
  righe di audit di prova restano valide per la catena e spariranno
  all'azzeramento finale.
- **Lista di collaudo**: simulazioni con i giorni esatti (niente giorno in
  più), verifica del fuso con tinker, nuova prova A10 sulle ore.

### v0.31.0 — assenso del minore dai 14 anni (04/10/2026)

Analisi (richiesta di Paolo: "bisogna capire bene l'ambito"; sua prima
ipotesi: iscrizione dai 12 anni, privacy dai 14):
- **Tesseramento**: D.Lgs. 36/2021, art. 16, c. 2 (testo coordinato con il
  D.Lgs. 120/2023): "Il minore che abbia compiuto i 14 anni di età non può
  essere tesserato se non presta personalmente il proprio assenso". Sotto i
  14 anni chiedono i genitori, tenendo conto di capacità e aspirazioni del
  minore. La soglia di legge è 14, non 12 (il 12 ricorre per l'ascolto del
  minore, art. 315-bis c.c.). Da far confermare al CSEN.
- **Privacy**: i 14 anni dell'art. 2-quinquies del Codice privacy valgono
  solo per il consenso ai servizi della società dell'informazione. Qui il
  trattamento principale non si basa sul consenso (rapporto associativo,
  obblighi di legge) e la spunta sull'informativa è una presa visione:
  nessun consenso privacy del minore da raccogliere. L'unico consenso vero,
  foto e video, lo dà il genitore; dai 14 anni si chiede anche l'accordo
  del minore, per prudenza (si tratta della sua immagine).

Decisioni di Paolo (04/10/2026): soglia 14 anni compiuti al giorno
dell'invio; una sola dichiarazione in più nel passo 2, obbligatoria, che
comprende foto e video quando il genitore li ha consentiti; spunta nel
modulo (la mette il minore o il genitore dopo averglielo chiesto), senza
un link separato al minore; "riporta sempre il riferimento alla normativa
in piccolo". Tavole "Assenso del minore" sulla tela, approvate prima del
codice. Statuto: l'art. 6.3 pubblicato dalla v0.30.4 lo prevede già.

Ricevuta: con il blocco del minore la ricevuta passava a due pagine (dalla
v0.30.3 deve stare in una). Per i minori le tabelle «Il minore» e «Il
genitore» ora sono affiancate (`pdf/_righe-dati`, classi `colonne`,
`colonna` nella carta intestata) e la dichiarazione del minore è un titolo
come quello del genitore, con il testo citato e la norma in piccolo, senza
riquadro (scostamento dalla tavola, che è stata allineata). Verificato:
minore con assenso e adulto in una pagina.

Revisione in tre giri:
1. **correttezza**: 24 test su MySQL (5 nuovi: regola dei 14 anni e
   compleanno, quindicenne senza e con assenso, tredicenne, 14 anni
   compiuti tra il passo 1 e l'invio, adulto); pagine a 1440 e 375 px,
   ricevuta generata e controllata; spunte ripristinate dopo un errore e
   frase su foto e video che segue la casella anche al ripristino;
2. **sicurezza e privacy**: solo un booleano dal modulo, età e testo
   calcolati sul server; sotto i 14 anni il campo si ignora; il nome nel
   testo è escapato (Blade, DomPDF); nell'audit entra solo la data;
3. **regole UI**: nessuno `style=` né `<style>` nelle viste (il CSS dei PDF
   resta nel layout `pdf.carta-intestata`), direttive mai attaccate a
   parole, etichette in grassetto, riferimento normativo in piccolo in
   ogni punto.

Da fare: assenso ai rinnovi (§6); valutare nell'informativa una frase in
linguaggio semplice per il minore dai 14 anni.

### v0.31.1 — testi della B.A.S. (05/10/2026)

Decisione di Paolo: il gestionale parte con la B.A.S. il 01/01/2027 e la
B.A.S. adotta lo statuto rivisto ("bisogna adeguare la B.A.S. alle scelte
che servono per l'ASD"): la procedura online resta quella di questa
scheda. Il testo pubblicato su `/statuto` (1.0) diventa il testo B.A.S.;
informativa con titolare la B.A.S.; denominazione provvisoria "… B.A.S."
nelle email, nel piè di pagina e nella carta intestata. Dettagli e
differenze: `profilo-ente.md` §7. Le impronte di statuto e informativa 1.0
cambiano (le domande di prova conservano quelle vecchie).

### Revisione critica di v0.30.4–v0.31.3 (05/10/2026)

Domanda di Paolo dopo il deploy: "hai fatto i 3 giri di revisione critica
del codice?". Stato reale: per la v0.31.0 sì (sopra); per v0.30.4–v0.30.5
(statuto unito, fuso orario) e v0.31.1–v0.31.3 (testi della B.A.S.) solo
test e controlli mirati (catena dell'audit dopo il cambio di fuso, ricerca
delle diciture A.S.D.), non i tre giri. Fatti ora sull'intero diff dalla
v0.30.3:
1. **correttezza**: sessione scaduta all'invio, compleanno a mezzanotte,
   `created_at` impostato a mano (Laravel non lo sovrascrive), ripristino
   delle spunte, ricevuta in una pagina, statuto pubblicato con 31 articoli
   più il 24-bis e i quattro commi dell'art. 31. Trovata e corretta
   (v0.31.4) un'incoerenza: nel passo 2 il riferimento sulle foto (art. 10
   c.c., art. 96 L. 633/1941) compariva anche senza il consenso a foto e
   video; ora segue la casella, come la frase e come la ricevuta;
2. **sicurezza e privacy**: dal modulo arriva solo un booleano; nome e testi
   escapati; nell'audit solo la data dell'assenso; nessun nuovo input letto
   senza validazione;
3. **regole UI**: nessuno `style=` né `<style>` nelle viste (fuori dai PDF),
   direttive mai attaccate a parole, nessun `{!! !!}` nuovo.
24 test su MySQL superati.
- v0.32.0 (05/10/2026): iscrizioni online chiuse al pubblico finché il
  Presidente non le apre (Impostazioni › Ente, `profilo-ente.md`); chi è
  collegato prova il modulo e le sue domande nascono di prova. Statuto e
  informativa accettati sono quelli del periodo dell'ente in vigore; il
  riepilogo manda un'impronta di periodo e versioni e, se cambia periodo
  prima dell'invio, la domanda non parte e la persona rilegge i testi.
  Ricevuta con la denominazione del periodo della domanda. Tre giri di
  revisione: (1) correttezza — un nuovo periodo con data passata avrebbe
  cambiato i documenti già emessi: ora si accetta solo da domani in poi
  (tranne il primo); domanda a cavallo di due periodi (impronta);
  (2) sicurezza e permessi — modifiche solo al Presidente, controllate
  anche lato server; voci della lista di controllo da un elenco chiuso;
  (3) regole UI — nessuno `style=`, norma in piccolo sotto forma e regime,
  375 px senza scorrimento orizzontale. Rimasti `config('app.name')` nella
  barra in alto e nel layout PDF: sostituiti con il profilo. 33 test su
  MySQL superati.

### v0.33.0 — consenso a foto e video nella scheda del socio (05/10/2026)

Con il link personale per l'informativa (`privacy-link.md`) è emersa una
lacuna: il consenso a foto e video della domanda (`consenso_foto_video`)
non passava nella scheda del socio. Ora `IscrizioneSocio` lo copia
(`foto_video_consenso`, `foto_video_il` = invio della domanda,
`foto_video_origine = domanda`); la migration lo riporta anche per i soci
già iscritti da domanda online. Test: `VotoCdTest`, iscrizione nel libro
soci.
