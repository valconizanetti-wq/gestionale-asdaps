# Link personale per l'informativa privacy, foto e video

**Stato:** implementato (v0.33.0, 05/10/2026); tavole approvate da Paolo
**Introdotto in:** v0.33.0
**Tavole:** tela "Gestionale ASD — Riferimento UI", fila "Link personale per
l'informativa privacy, foto e video" (`Privacy-1` … `Privacy-5`)
**File coinvolti:**
- Migration: `2026_10_05_000091_create_gl_privacy_link_table`,
  `2026_10_05_000092_add_foto_video_to_gl_soci_table` (anche
  `privacy_metodo` con il valore `link`, `gl_email_log.socio_id` e la copia
  di foto e video per i soci già iscritti da domanda online)
- Model: `PrivacyLink` (nuovo), `Socio` (campi foto e video, stato privacy)
- Servizio: `App\Services\LinkPrivacy` (destinatario, invio, invio in blocco,
  registrazione)
- Controller: `PrivacyLinkController` (pagina pubblica),
  `SocioDocumentiController` (invio dalla scheda, revoca foto e video),
  `SociController` (invio in blocco)
- Mail: `PrivacyLinkInvito`; vista `emails/privacy/link`
- Viste: `privacy/pagina`, `privacy/registrata`, `privacy/non-valido`;
  `soci/show` (riquadro dei documenti, finestra della revoca), `soci/index`
  (pulsante e finestra dell'invio in blocco)
- `InvioEmail` (parametro `socio`), `EmailLog`, `RiskMatrix`
  (`foto_video_consenso` MEDIUM), tema delle email (`p.indirizzo` va a capo)
- Informativa 1.0, punto 2: dati della conferma con il link (correzione sul
  posto, regola dei testi prima dell'apertura)
- `IscrizioneSocio`: copia del consenso a foto e video dalla domanda
- Rotte pubbliche in `routes/iscrizione.php`
- Test: `tests/Feature/PrivacyLinkTest.php`

## 1. Logica

Oggi il consenso privacy di un socio si registra in tre modi: con la
domanda online (`privacy_metodo = online`), di persona (un clic
dell'operatore) o con il modulo cartaceo firmato (`soci.md`, v0.22.0). Per
i soci senza domanda online (i soci della B.A.S. già iscritti, gli
inserimenti a mano) Paolo ha deciso il **link personale via email**
(`soci.md`; `domande-iscrizione.md` §6). Nel Gestionale APS la funzione
manca: una volta collaudata va portata nel core e nel Gestionale APS.

Che cosa si raccoglie. L'informativa (`resources/content/informativa/1.0.md`,
punto 3) fonda quasi tutti i trattamenti sul rapporto associativo e sulla
legge, non sul consenso: al socio si chiede di **dichiarare di aver letto
l'informativa** (prova dell'art. 13 GDPR), come nella domanda online. L'unico
vero consenso è quello **facoltativo a foto e video** (punto 3, lett. f;
art. 6.1.a), revocabile in ogni momento.

Lacuna trovata (05/10/2026): la domanda online raccoglie il consenso a foto
e video (`gl_domande.consenso_foto_video`), ma all'iscrizione nel libro soci
il dato non passa in `gl_soci`, che non ha un campo per registrarlo. Oggi la
scheda del socio non dice se si possono pubblicare le sue immagini e la
segreteria non può registrare una revoca.

Decisioni di Paolo (05/10/2026):
- la pagina del link chiede anche il **consenso a foto e video**; il dato
  entra nella scheda del socio con la data ed è revocabile; all'iscrizione
  da domanda online si copia dalla domanda;
- il link si invia dalla **scheda del socio** e con un **invio in blocco**
  dall'elenco Soci, a chi non ha letto l'informativa in vigore;
- il link vale **fino all'uso**, una volta sola; un nuovo invio annulla il
  link precedente;
- il link serve anche per le **nuove versioni dell'informativa** (la 2.0
  dell'A.S.D. nel 2028): scheda e invio in blocco considerano anche chi ha
  letto una versione precedente.

## 2. Struttura dati

**Nuova tabella `gl_privacy_link`** (una riga per ogni link inviato):

| Campo | Tipo | Note |
|---|---|---|
| `socio_id` | FK `gl_soci` | |
| `token_hash` | char(64), unico | SHA-256 del token (`Domanda::hashToken`): il token in chiaro esiste solo nell'email |
| `destinatario` | enum `socio`, `tutore` | A chi è andato il link: decide i testi della pagina |
| `email` | string | Indirizzo usato per l'invio (prova) |
| `inviato_da` | FK `users` | Operatore |
| `inviato_il` | datetime | |
| `annullato_il` | datetime, null | Un invio più recente lo ha sostituito |
| `usato_il` | datetime, null | Conferma registrata |
| `ip` | string(45), null | Di chi ha confermato |
| `informativa_versione` | string(10), null | Versione letta (quella in vigore al momento della conferma) |
| `foto_video` | boolean, null | Scelta espressa con il link |

Un link è **valido** se `usato_il` e `annullato_il` sono vuoti e il socio è
attivo e non pseudonimizzato. Nessuna scadenza (decisione di Paolo).

**`gl_soci`, campi nuovi** (foto e video):

| Campo | Tipo | Note |
|---|---|---|
| `foto_video_consenso` | boolean, default false | Stato attuale |
| `foto_video_il` | datetime, null | Data dell'ultima scelta (consenso o revoca) |
| `foto_video_origine` | enum `domanda`, `link`, `revoca_email`, `revoca_persona`, `revoca_lettera`, null | Da dove viene lo stato attuale |

`privacy_metodo` riceve il valore **`link`** (oggi `online`, `presenza`,
`cartaceo`): con `online` resta la domanda; con `link` la conferma dal link
personale (data, ora, IP e versione come per la domanda).

**`gl_email_log`**: colonna `socio_id` (nullable, come `domanda_id`) per
registrare anche le email del link.

## 3. Comportamento / flusso

**Destinatario.** Socio minorenne (per età alla data dell'invio): l'email
del tutore (`tutore_email`); maggiorenne: la sua email. Senza indirizzo il
pulsante è disattivato con il motivo ("Nessuna email: inseriscila nella
scheda"); restano *in presenza* e *modulo cartaceo*.

**Stato del consenso privacy** (scheda, elenco, invio in blocco), letto con
la versione dell'informativa in vigore oggi (`ProfiloEnte::versione`):
1. *Mancante*: nessuna conferma e nessun link in attesa;
2. *In attesa*: link inviato e non ancora usato (data e indirizzo);
3. *Presente*: conferma della versione in vigore (domanda, link, presenza o
   modulo);
4. *Da aggiornare*: conferma di una versione precedente. Nel conteggio dei
   documenti mancanti conta come presente: la lettura della versione di
   allora resta la prova per il passato.

**Dalla scheda del socio** (tavola `Privacy-4`): "Invia il link" (stati 1 e
4), "Invia di nuovo" (stato 2: link nuovo, il precedente annullato),
accanto a "In presenza" e "Carica modulo" che restano. Messaggio dopo
l'invio: "Link inviato a …".

**Invio in blocco** (tavola `Privacy-5`): pulsante "Link privacy" nella
testata dell'elenco Soci, con il numero dei soci attivi negli stati 1, 2 e 4.
La finestra mostra: senza consenso, versione precedente, quanti riceveranno
l'email, chi non ha email (per nome), e la spunta "Manda di nuovo anche a
chi ha già un link non usato" (spenta: un secondo clic non manda doppioni).
L'invio è sincrono (pochi soci, nessuna coda su cPanel); a ogni email
fallita il messaggio finale dice quante sono partite e quante no, con i
nomi. Ogni invio in `gl_email_log`.

**Email** (tavola `Privacy-3`): mittente e stile del sito come le altre
(`MittenteCompagnia`), "Rispondi a" la segreteria; oggetto "Informativa
privacy: conferma la lettura" (al genitore: "… per Giulia Rossi"; nuova
versione: "Informativa privacy aggiornata: conferma la lettura"); pulsante
"Apri la pagina" e indirizzo in chiaro; "vale una volta sola; se arriva
un'altra email come questa, vale la più recente".

**Pagina pubblica** `/privacy/{token}` (tavole `Privacy-1` e `Privacy-2`),
layout del sito, senza login:
- GET con link valido: saluto (al genitore: "come genitore di Giulia"),
  socio e versione dell'informativa (con "Cosa cambia" se il socio aveva
  letto una versione precedente: testo breve preso dall'informativa nuova),
  informativa completa in un riquadro apribile, con "Apri in una pagina a
  parte";
- dichiarazione obbligatoria "Dichiaro di aver letto l'informativa
  (versione X)[, come genitore di …]" con il riferimento in piccolo (art. 13
  Reg. UE 2016/679);
- consenso facoltativo a foto e video, già spuntato se oggi il consenso c'è
  ("Oggi: consenso dato il … Togliendo la spunta lo revochi"); per un minore
  dai 14 anni il testo aggiunge "dopo averne parlato con lui/lei" (come
  l'assenso della domanda comprende foto e video); riferimento in piccolo
  (art. 10 c.c., art. 96 L. 633/1941);
- POST "Conferma": senza la dichiarazione errore accanto alla casella,
  spunte conservate; con la dichiarazione si registrano link e socio, poi
  si torna al GET, che mostra il riepilogo ("Grazie, è registrato":
  versione, foto e video, data e ora; come revocare). Ricaricare non
  registra due volte;
- link usato: lo stesso riepilogo; link annullato, inesistente o socio non
  più attivo: "Link non valido" con il rimando all'ultima email e alla
  segreteria.

**Registrazione della conferma** (in una transazione, con il link bloccato
in lettura):
- link: `usato_il`, `ip`, `informativa_versione`, `foto_video`;
- socio: `privacy_accettata_il`, `privacy_ip`, `privacy_versione`,
  `privacy_metodo = link`; foto e video: se la scelta cambia lo stato,
  `foto_video_consenso`, `foto_video_il`, `foto_video_origine = link` (una
  spunta tolta è una revoca dal link).

**Foto e video nella scheda** (riga nuova del riquadro "Documenti per
l'iscrizione", facoltativa: non conta tra i mancanti): "Sì · consenso dato il
… (domanda online / link personale)" oppure "No · revocato il … (per email)"
oppure "No · non dato". La segreteria registra **solo la revoca** (finestra
con data e modo della richiesta: email, di persona, lettera); il consenso lo
dà solo il socio o il genitore, con la domanda o con il link.

**Iscrizione da domanda online** (`IscrizioneSocio`): oltre alla privacy
copia `consenso_foto_video` in `foto_video_consenso`, con
`foto_video_il` = invio della domanda e `foto_video_origine = domanda`.

## 4. Implementazione tecnica

- **Token**: `Domanda::generaToken()` (64 caratteri) e `hashToken()`; nel
  database solo l'impronta. Ricerca con lunghezza controllata prima della
  query, come `VotoCd::perToken`.
- **Rotte** in `routes/iscrizione.php`: `GET /privacy/{token}`
  (`privacy-link.mostra`, `throttle:pubblico-pagine`), `POST /privacy/{token}`
  (`privacy-link.conferma`, `throttle:pubblico-azioni`). Area riservata,
  sezione `soci` con permesso di modifica: `POST soci/{socio}/privacy/link`
  (`soci.privacy.link`), `POST soci/{socio}/foto-video/revoca`
  (`soci.foto-video.revoca`), `POST soci/link-privacy`
  (`soci.privacy-link.tutti`). I conteggi della finestra si calcolano nella
  pagina dell'elenco (`LinkPrivacy::riepilogo`), solo per chi modifica.
- **Model**: `PrivacyLink` (`perToken`, `scopeAperti`, `isValido`,
  `perTutore`); `Socio::statoPrivacy()`, `destinatarioPrivacy()`,
  relazioni `privacyLink` e `privacyLinkAperto` (ultimo link aperto,
  `ofMany`), costanti `ORIGINI_FOTO_VIDEO` e `MODI_REVOCA`.
- **Servizio `LinkPrivacy`**: `invia(Socio, User)` (annulla i link aperti,
  crea il nuovo, manda l'email con `InvioEmail` e `socio_id` nel registro;
  se l'email non parte annulla anche il link nuovo), `query()` (soci
  attivi non pseudonimizzati senza la versione in vigore), `riepilogo()`,
  `inviaATutti(bool $ancheInAttesa, User)`, `registra(PrivacyLink, bool
  $foto, Request)` (transazione con il link bloccato in lettura).
- **Viste**: componenti esistenti (`x-card`, `x-badge`, `x-modale`,
  `x-campo`); pagine pubbliche con `layouts/pubblico` (tema del sito) e le
  classi già usate dalle pagine del voto; niente `style=`; verifica a 1440 e
  375 px.
- **Audit**: `Socio` ha già l'`AuditObserver` (le modifiche di privacy e
  foto e video sono tracciate); evento di business `PRIVACY_LINK_INVIATO`
  con il solo id del socio e del link, nessun dato personale.

## 5. Conformità e sicurezza

- **Prova della lettura** (art. 5.2 e 7.1 GDPR): data, ora, IP, versione e
  link usato; l'email di invio resta in `gl_email_log`.
- **Consenso a foto e video**: libero (facoltativo, non condiziona
  l'iscrizione), specifico (una casella sola), revocabile con la stessa
  facilità (togliendo la spunta dal link o scrivendo alla segreteria).
- **Minori**: decide chi esercita la responsabilità genitoriale
  (informativa, punto 9); l'assenso del minore dai 14 anni (art. 16 D.Lgs.
  36/2021) riguarda il tesseramento e non si chiede qui.
- **Sicurezza del link**: token di 64 caratteri, impronta nel database,
  monouso, annullato da un nuovo invio, limiti per IP sulle rotte, nessun
  dato del socio oltre a nome e categoria nella pagina.
- **Informativa**: il punto 2 parla di "data e ora di invio ... della
  domanda"; da estendere ai consensi dati con il link personale (correzione
  sul posto della 1.0, regola dei testi prima dell'apertura). Da far
  confermare a Paolo.

## 6. Interazioni con altri moduli

- **Domande di iscrizione**: stessa informativa, stessi testi della
  dichiarazione e di foto e video; `IscrizioneSocio` copia foto e video.
- **Profilo dell'ente**: versione dell'informativa in vigore dal periodo
  (`ProfiloEnte`); con l'informativa 2.0 del 2028 i soci passano a "Da
  aggiornare" e l'invio in blocco li raggiunge.
- **Soci**: riquadro dei documenti, elenco (pulsante e conteggio).
- **Comunicazioni / email**: `gl_email_log` con `socio_id`.
- **Gestionale Core e Gestionale APS**: funzione da riportare
  (`SYNC-LOG.md`).

## 7. Storia ed evoluzione

- 05/10/2026: analisi e tavole. Decisioni di Paolo: foto e video nel link e
  nella scheda del socio (con copia dalla domanda), invio dalla scheda e in
  blocco, link valido fino all'uso e monouso, link anche per le nuove
  versioni dell'informativa. Lacuna trovata: il consenso a foto e video
  della domanda non arrivava nella scheda del socio.

### v0.33.0 — realizzazione (05/10/2026)

Tavole `Privacy-1…5` approvate da Paolo insieme a tre punti: la segreteria
registra solo la revoca di foto e video; per un minore dai 14 anni il
testo su foto e video aggiunge "dopo averne parlato con lui/lei"; il punto
2 dell'informativa 1.0 nomina la conferma con il link.

Differenze rispetto alle tavole:
- niente riquadro "Cosa cambia" per le nuove versioni dell'informativa:
  la pagina dice quale versione il socio aveva letto e quando; un testo
  sulle novità si aggiungerà con l'informativa 2.0;
- "Vedi l'elenco" della finestra dell'invio in blocco apre l'elenco dei
  nomi nella finestra stessa (con la spunta compaiono anche quelli con un
  link in attesa); il pulsante dice "Invia le email";
- un socio con la privacy già in regola non ha il pulsante del link.

Comportamenti da sapere:
- se la segreteria registra il consenso di persona o con il modulo mentre
  un link è aperto, il link resta valido: usarlo registra di nuovo la
  lettura (con data, ora e IP), senza danni;
- il socio sospeso non riceve il link (solo i soci attivi);
- l'oggetto "Informativa privacy aggiornata" va solo a chi aveva letto una
  versione diversa da quella in vigore.

Revisione critica (tre giri, 05/10/2026):
1. *Codice*: l'oggetto dell'email diceva "aggiornata" anche a chi aveva
   già letto la versione in vigore (corretto: solo con versione diversa);
   la revoca accettava una data precedente al consenso (ora errore
   accanto al campo); il cambio del consenso a foto e video finiva
   nell'audit come operazione ordinaria (ora MEDIUM in `RiskMatrix`).
2. *Pagine ed email*: la freccia del riquadro dell'informativa non si
   vedeva su desktop (la regola di `.iscr-freccia` la nasconde sopra i 768
   px; ora visibile in `.privacy-testo`); nelle email l'indirizzo del link
   in fondo non andava a capo e usciva dal riquadro (valeva anche per
   l'email di voto; corretto nel tema `sito.css`).
3. *Regressioni*: 41 test su MySQL superati (8 nuovi in
   `PrivacyLinkTest`, più la copia di foto e video nel test
   dell'iscrizione); pagine pubbliche e area riservata a 1440 e 375 px
   senza scorrimento orizzontale; migration provate anche all'indietro.
