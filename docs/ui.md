# UI — Identità visiva, token e componenti condivisi

**Stato:** in uso (fondamenta, componenti Blade, layout pubblico)
**Introdotto in:** v0.17.0 (2026-09-23); componenti v0.20.0; modelli PDF
v0.24.0; layout pubblico ed email v0.27.0 (2026-09-28)
**File coinvolti:**
- `resources/css/app.css` (riscritto: token + classi condivise)
- `resources/js/app.js` (font locali + Alpine via npm)
- `package.json` (nuove dipendenze, aggiunte con `npm install`, vedi §4)
- `resources/views/layouts/pubblico.blade.php`,
  `resources/views/components/passi.blade.php` (v0.27.0)
- Riferimento visivo: tela di design "Gestionale ASD — Riferimento UI"
  (tavole *Elenco Soci*, *Fondamenti UI* e *Iscrizione-\**)

## 1. Logica

Prima di scrivere la prima vista si fissano le regole grafiche comuni,
perché la prima pagina costruita senza regole diventerebbe la regola per
caso. La coerenza si ottiene su tre livelli, in quest'ordine:

1. **Fondamenta**: token (colori, font, raggi, misure del layout) e
   classi condivise in un unico foglio di stile.
2. **Archetipi di pagina**: quasi tutte le 20 sezioni ricadono in
   quattro tipi — *elenco* (filtri, tabella, azioni per riga,
   paginazione), *scheda* (dettaglio di un record), *modulo*
   (creazione/modifica), *report o cruscotto* (plafond 398/91, scadenze).
   Ogni modulo si costruisce solo con questi quattro.
3. **Componenti Blade** per gli elementi ricorrenti e per le regole
   trasversali (vedi §3).

Il linguaggio visivo riprende quello del Gestionale APS (Dante), già
collaudato: topbar colorata, sidebar con l'anno sociale in cima, titolo
di pagina con la seconda parola colorata, etichette piccole in
maiuscoletto, card con intestazione di sezione, KPI in riga, tabelle con
badge di stato. Cambiano l'identità (palette dal logo, carattere dei
titoli) e soprattutto l'implementazione (vedi §7).

## 2. Struttura dati (token)

Nessuna tabella: i "dati" di questo modulo sono i token in `@theme` di
`app.css` (Tailwind 4 non usa più `tailwind.config.js`, la
configurazione vive nel CSS).

**Palette** (derivata dal logo; ogni colore ha un solo significato):

| Token | Hex | Ruolo |
|---|---|---|
| `primario` | `#004aad` | Blu delle scritte del logo. Marchio e azione: topbar, pulsanti principali, link, voce di menu attiva |
| `notte` | `#081939` | Blu del contorno. Titoli e testo forte |
| `giallo` | `#f8f048` | Giallo del bersaglio. Accento **solo su fondo blu** (nome in topbar, avatar): su fondo chiaro non è leggibile |
| `rosso` | `#bd2c31` | Rosso del bersaglio. Errori, anomalie, azioni distruttive |
| `pietra` | `#6e685f` | Grigio della torre. Testo secondario minore |
| `fondo` | `#f3f2ee` | Sfondo pagina |
| `ok` / `warn` / `ko` | `#1f6b41` / `#8a5a0d` / `#9e2328` | Stati positivo / in corso / problema (verde e ambra ripresi dal Dante), ciascuno con il proprio fondo tenue |

Differenza voluta rispetto al Dante: lì il bordeaux era insieme colore
del marchio e colore "caldo" dell'interfaccia; qui il blu è il marchio e
il rosso significa solo "problema", quindi un badge rosso non si
confonde mai con un elemento decorativo.

**Tipografia:**
- Titoli e valori KPI: **Barlow Semi Condensed** (600/700) — tono da
  segnaletica sportiva, sobrio. Il carattere arrotondato del logo resta
  solo nel logo: in un gestionale risulterebbe infantile.
- Testo, tabelle, campi: **Inter** (400/500/600/700), come nel Dante.
- **Etichette dei campi sempre in grassetto e nel colore del testo
  scuro**, in tutti i contesti: form (`form-label`), schede di dettaglio
  (`dato-label`), barra dei filtri e tabelle trasformate in schede su
  telefono. Richiesta di Paolo dopo il collaudo di Soci: nelle schede
  le etichette grigie in maiuscoletto risultavano poco leggibili.
- Codici (tessera, codice fiscale, protocollo): monospace di sistema,
  classe `.code`.
- Font **ospitati in locale** tramite i pacchetti `@fontsource`, non da
  Google Fonts: il caricamento da Google trasmette l'IP del visitatore a
  un terzo, tema rilevante per un gestionale con un modulo GDPR.

## 3. Comportamento / flusso

**Regola principale**: nelle viste **niente `style="..."` e niente
`<style>`**. Unica eccezione: i modelli PDF in `resources/views/pdf/`,
che hanno un proprio `<style>` perché DomPDF non legge `app.css` (niente
Vite né Tailwind); usano la stessa palette in forma semplificata e anche
lì niente `style=` sui singoli elementi.

**Modelli PDF** (v0.24.0): tutti estendono il layout comune
`resources/views/pdf/layout.blade.php`, ripreso dal layout PDF del
Gestionale APS (`pdf.layout-contabilita`) con le stesse variabili
(`$docTitolo`, `$docCodice`, `$docSottotitolo`, `$docData`,
`$docDataLabel`, `$docLuogo`, `$docOggetto`, `$bodyPadding`,
`$showPagination`, più `$orientamento`) e le stesse classi di corpo
(`doc-title`, `doc-section`, `doc-box`, `doc-firme`…), più `kpi-bar` e
`doc-table` (intestazione blu, righe alternate) per i documenti-elenco.
- **Intestazione e piede**: se esistono `public/img/pdf-header.jpg` e
  `pdf-footer.jpg` (grafiche preparate, come nel Dante) si usano quelle;
  altrimenti si compongono con logo e nome dell'associazione. Una sezione
  `@section('piede', '…')` aggiunge una nota al piè di pagina.
- **Numero di pagina**: contatore CSS di DomPDF ("Pagina N"). Il Dante
  mostra "N / totale" con uno script PHP interno a DomPDF, che richiede
  `isPhpEnabled`; qui resta spento per sicurezza (lo stesso Gestionale APS
  lo sconsiglia nel commento di `HtmlSanitizer`). Il totale delle pagine
  non è quindi disponibile.
- **Caratteri**: DejaVu Sans (incluso in DomPDF, con gli accenti). Barlow e
  Inter non si usano nei PDF: DomPDF richiederebbe i file TTF, che i
  pacchetti `@fontsource` non forniscono.

Se una vista ha bisogno di uno stile nuovo, quello stile diventa una
classe in `app.css` o un componente Blade. Le utility
Tailwind restano ammesse solo per l'impaginazione (flex, grid, gap,
margini).

**Classi condivise** (in `@layer components`, con i nomi del Dante dove
esisteva un equivalente): `topbar-*`, `layout`, `sidebar-*`
(con `sidebar-gruppo` e `sidebar-conta`), `page-header`/`page-title`/
`page-sub`/`breadcrumb`, `btn` (`-primary`, `-secondary`, `-ghost`,
`-danger`, `-sm`), `action-btn`, `badge` (`-ok`, `-warn`, `-ko`,
`-info`, `-neutral`), `card`/`section-header`/`card-body`,
`kpi-row`/`kpi-card`/`kpi-label`/`kpi-value`/`kpi-sub`,
`data-table` (`td-name`, `td-sub`, `td-num`, `td-azioni`),
`table-footer`, `empty-state`, `filter-bar`, `form-*`, `alert-*`,
`modal-*`.

**Componenti Blade** (in `resources/views/components/`, scritti con il
modulo Soci, v0.20.0):
- `x-page-header` (titolo con parola evidenziata, sottotitolo, link
  indietro, slot `azioni`);
- `x-card` (intestazione di sezione, slot `azioni`, `senza-padding` per
  tabelle);
- `x-kpi` (anche come link-filtro), `x-badge`, `x-badge-stato`;
- `x-campo` (input, select, textarea con etichetta, aiuto, errore,
  `old()` e attributi ARIA gestiti da sé; con `obbligatorio-se="…"` un
  campo obbligatorio solo quando un'espressione Alpine è vera, asterisco
  compreso), `x-spunta`;
- `x-dato` (coppia etichetta/valore delle schede);
- `x-avviso`, `x-sola-lettura`, `x-vuoto`;
- `x-paginazione` (numeri su desktop, Prec./Succ. su telefono);
- `x-modale` (Alpine, si apre con `$dispatch('apri-modale', 'nome')`);
- `x-icona` (le icone di linea usate nell'app).
Le tabelle restano markup HTML con le classi `data-table responsive`,
`td-primario`, `td-solo-desktop` e `data-label` sulle celle: un
componente avrebbe nascosto poco e reso più rigide le celle.

**Pagine pubbliche** (v0.27.0, prima la domanda di iscrizione online,
`domande-iscrizione.md`): layout `layouts.pubblico`, senza sidebar né
login.
- Topbar con logo, nome ("Compagnia Arcieri Castel Viscardo") e ragione
  ("A.S.D. affiliata CSEN"); piè di pagina con i dati di
  `Associazione::istanza()` e i link a informativa e statuto;
  `meta robots noindex`; `@stack('scripts')`.
- La sezione `@section('stretto', '1')` restringe la colonna, per le
  pagine di esito (email inviata, conferma, link scaduto).
- `<x-passi :corrente="1" />`: indicatore dei passi di un modulo in più
  pagine (qui: Dati, Controlla e invia).
- Classi in `@layer components`:
  - struttura: `pubblico-main`, `pubblico-stretto`, `pubblico-colonne`
    (modulo più colonna laterale), `pubblico-colonna`,
    `pubblico-laterale`, `pubblico-footer`, `pubblico-centrato`,
    `testo-secondario`;
  - passi: `passi`, `passo`, `passo-num`, con `.fatto` e `.corrente`;
    vicini e centrati a ogni larghezza (larghezza minima 48 px e 12 px tra
    i passi: ogni passo è largo quanto la sua etichetta);
  - pulsanti dentro un avviso: `avviso-azioni`;
  - scelta a schede: `scelta-gruppo`, `scelta-opzioni`, `scelta`,
    evidenziata con `:has(input:checked)` senza JavaScript;
  - aiuto sotto un campo calcolato dal server: `cf-anteprima` con `.ok` e
    `.ko`;
  - riquadro della quota: `quota-box`, `quota-box-testo`,
    `-etichetta`, `-nota`, `-importo`;
  - dichiarazioni da spuntare: `dichiarazione` (`.principale` per quella
    obbligatoria), `dichiarazione-corpo`, `dichiarazione-elenco`;
    `campo-trappola` (nascosto agli umani, per i bot); `elenco-passi`;
  - esiti: `esito-icona` (`.ok`, `.warn`);
  - testi lunghi (informativa, statuto): `documento-testo`, con titoli,
    elenchi e tabelle.
- A 375 px la colonna laterale va sotto il modulo e le tabelle dei testi
  scorrono in orizzontale.
- I pulsanti che inviano un POST irripetibile (invio, conferma,
  annullamento) si disattivano al primo clic con Alpine
  (`x-on:submit="inviato = true"`, `x-bind:disabled="inviato"`).

**Tema del sito** (v0.28.0, richiesta di Paolo: "le pagine di iscrizione
nel layout del sito"). Le pagine pubbliche hanno l'aspetto del sito della
Compagnia (WordPress, tema Astra Child), non quello dell'area riservata:
- `layouts.pubblico` mette la classe `tema-sito` sul `<body>`; in `app.css`
  (sezione 5) la classe ridefinisce i token: verde `#0f4124` come colore
  d'azione, verde notte `#0A2E1C`, oro `#F2B84A` (solo su fondo scuro),
  arancione `#FF8C00` (solo voce attiva del menu), sabbia `#E4DCC8` come
  fondo, carbone `#15181A` per testo e piè di pagina, Poppins come
  carattere, raggi 8/12 px. I componenti non cambiano: seguono i token.
- Intestazione e piè di pagina sono la **copia** di `header.php`,
  `footer.php` e delle regole di `assets/css/custom.css` (v3.41.2) del tema
  figlio del sito (`astra-child`): stesse classi (`page-wrap`,
  `mock-header-single`, `header-logo-img`, `mock-nav`, `nav-toggle-btn`,
  `mock-footer`), stessi valori, stesse immagini (`header-desktop.jpg`,
  `header-mobile.jpg`, `header-bar-slice.png` in `public/images/sito/`),
  sezione 6 di `app.css`. Contenuto allineato ai bordi dell'intestazione
  (50 px, come `.main-container` del sito) dentro `page-wrap` da 1280 px.
  Differenze volute: menu a tendina aperto con Alpine invece che con lo
  script del sito; piè di pagina tenuto in fondo con flex invece che con
  `footer-fixed` via JavaScript; nel piè di pagina in più i link a
  informativa e statuto.
  Intestazione attaccata al bordo superiore (margine 0 e angoli
  arrotondati solo in basso) invece dei 40 px del CSS del sito: richiesta
  di Paolo dopo il deploy del 28/09/2026.
- Pagine compatte (richiesta di Paolo): campi alti 38 px su desktop, meno
  spazio tra campi, schede e sezioni. Su telefono i campi restano a 44 px
  e 16 px di testo (regola del §3: bersagli di tocco e niente zoom di iOS).
- Voci del menu, immagini e righe del piè di pagina in `config/sito.php`
  (`SITO_URL` nel `.env`). Se cambiano nel sito (menu in Aspetto → Menu,
  intestazione o piè di pagina nel tema), vanno aggiornati anche qui.
- Pulsante principale verde con testo bianco, non arancione: il bianco
  sull'arancione del sito ha contrasto 2,3:1 (minimo 4,5:1). Deciso con
  Paolo sulla tavola.
- Poppins in locale con `@fontsource/poppins`, come Inter e Barlow.
- Tavole "Iscrizione 1 — stile del sito" (desktop e telefono) sulla tela.

**Pagina di iscrizione** (v0.29.0, sezione 7 di `app.css`): classi
`iscr-*` per titolo, istruzioni a fisarmonica (`iscr-blocco`, aperte su
desktop con Alpine, chiuse su telefono), modulo in una scheda con schede di
passo (`iscr-schede`), righe di campi a larghezza fissa (`iscr-riga-nome`,
`-contatti`, `-residenza`, `-documento`), `selettore` (radio a due
pulsanti, accessibile da tastiera), `quota-fascia`, esito del codice
fiscale accanto al campo (`cf-campo`, `cf-esito`), riepilogo
(`iscr-riepilogo`, `iscr-dati`) e domande frequenti (`iscr-faq`). Criterio:
un campo è largo quanto il suo contenuto, non quanto la colonna.

**Email** (v0.27.0, tema dalla v0.30.0): modelli markdown di Laravel
(`<x-mail::message>`, `<x-mail::button>`, `<x-mail::panel>`,
`<x-mail::table>`) in `resources/views/emails/`, mai HTML scritto a mano.
Dalla v0.30.0 hanno l'aspetto del sito (tavole "Email — …" sulla tela,
decisione di Paolo del 29/09/2026):
- tema `sito` in `resources/views/vendor/mail/html/themes/sito.css`
  (`config/mail.php`, chiave `markdown`): fondo sabbia, scheda bianca con
  bordo verde in alto, titolo maiuscolo verde notte, pulsante verde,
  riquadro crema per i dati (`x-mail::panel`), carattere Poppins con
  ripiego su Arial;
- componenti ridefiniti solo dove serve: `html/header` (immagine
  dell'intestazione del sito, indirizzo assoluto), `html/message` e
  `text/message` (piè con ragione sociale, codice fiscale e sede da
  `config/sito.php`); gli altri restano quelli di Laravel;
- classi per il markdown: `<p class="occhiello">` sopra il titolo,
  `<p class="indirizzo">` per la riga con l'indirizzo del link.
Il CSS del tema viene copiato da Laravel dentro l'HTML dell'email: è
l'unica eccezione alla regola "niente `style=`", e lo stile resta
comunque deciso in un file solo. Testi: mittente e motivo chiari, niente
urgenze in grassetto né minacce, indirizzo del link scritto per esteso
(regole contro i falsi allarmi degli antivirus, `domande-iscrizione.md`
§5).

**Voto del CD e domande** (v0.30.0, sezione 8 di `app.css`, tavole "Voto
1–3" e "Domande — …"):
- pagine pubbliche di voto (`voto-cd/*`, tema del sito): `voto-pagina`,
  `voto-scheda` (dati del richiedente), `voto-termine` (fascia scura con
  la scadenza), `voto-modulo`, `voto-scelte` e `voto-scelta` (`.favorevole`
  verde, `.contrario` rosso, evidenziate con `:has(input:checked)`),
  `voto-motivo` e `voto-contatore` (la motivazione compare solo con
  "Contrario"), `voto-modulo-piede`; titoli con `iscr-titolo` e
  `iscr-occhiello`. Nessuna spiegazione della procedura: i consiglieri la
  conoscono;
- area riservata: `scheda-colonne`/`scheda-colonna` (azione del momento
  in alto, poi voto e cronologia a sinistra, dati a destra),
  `mini-kpi-riga`/`mini-kpi` (termine, quorum, voti, esito con i voti di
  oggi), `riga-nota` (motivazione sotto la riga del consigliere; su
  telefono a tutta larghezza), `riquadro-riga` (bozza di verbale),
  `cronologia` (`.futuro` per gli eventi previsti), `elenco-spunte`,
  `quota-iscrivi`, `sottotitolo-card`, `testo-urgente`, `riga-azioni`,
  `badge-riga.stretta`;
- stati delle domande in `config/stati.php` (dominio `domanda`): «In
  votazione», «Diniego da inviare», «Decide il Presidente», «Accolta»,
  «Non accolta», «Decaduta» e lo stato virtuale «Iscritta» (domanda
  collegata a un socio); badge in più nel parziale `domande/_stato`:
  «Quota da versare», «D'ufficio», «Prova»;
- icona `orologio` in `x-icona`.
Verificato a 375 px: tabella dei voti in schede, motivazioni a tutta
larghezza, cronologia su due righe, pulsanti a tutta larghezza.

**Assenso del minore e riferimenti normativi** (v0.31.0, sezione 10 di
`app.css`, tavole "Assenso del minore"):
- `dichiarazione-testa` (etichetta e badge in riga) e
  `dichiarazione-etichetta` (maiuscoletto nel colore d'azione) per la
  dichiarazione del minore, dentro la solita `dichiarazione principale`;
- `norma`: riferimento normativo in piccolo (11 px, colore delle
  etichette) sotto ogni dichiarazione che ne ha uno, anche nelle domande
  frequenti e nelle schede (`dato-norma` lo manda a capo dentro un
  `x-dato`). Regola di Paolo (04/10/2026): "riporta sempre il riferimento
  alla normativa in piccolo";
- PDF (`pdf.carta-intestata`): `.norma` (6,5 pt), `.citazione` (corsivo),
  `table.colonne` con `td.colonna` / `td.colonna-destra` per due tabelle
  affiancate (minore e genitore nella ricevuta). DomPDF ignora i selettori
  `>` e `+`: classi esplicite sulle celle. Parziale `pdf/_righe-dati` per
  le tabelle etichetta/valore.

**Regole trasversali, gestite una volta sola dentro i componenti:**
- **Sola lettura** (Revisore sulle sezioni contabili e sull'Audit Log,
  Tesoriere su Volontari): i componenti leggono il permesso della
  sezione corrente e nascondono le azioni di modifica, con l'avviso
  `alert-info` "Sola lettura". Nessun `@if` sparso nelle viste. Usano
  gli helper `puoModificare()`/`soloLettura()` di `User` (`permessi.md`
  §4).
- **Stati degli enum**: la corrispondenza stato → variante di badge
  (`pagato` → `ok`, `in_attesa` → `warn`, `annullato` → `neutral`…) vive
  in un solo punto, così lo stesso stato ha lo stesso aspetto ovunque.
  Deciso con il primo modulo: file `config/stati.php`, letto dal
  componente `<x-badge-stato dominio="…" :valore="…" />`.
- **Anno chiuso**: la motivazione obbligatoria (Scontrini, Volontari) usa
  sempre la stessa modale, collegata all'audit log.
- **Sidebar**: una voce diretta per ogni sezione della matrice permessi,
  raggruppate in quattro aree (Associazione, Contabilità, Governance,
  Controllo) più Dashboard e Impostazioni. Nessuna pagina-hub
  intermedia come nel Dante. Ogni voce compare solo se il ruolo ha
  accesso alla sezione.
- **Telefono** (requisito, non adattamento di ripiego): ogni pagina deve
  essere pienamente usabile da telefono, con larghezza minima di
  riferimento 360 px. Sotto i 768 px:
  - topbar compatta (logo, nome breve, avatar, Esci) e sidebar a
    scomparsa con voci più alte;
  - azioni dell'intestazione a tutta larghezza, filtri e form su una
    colonna, pulsanti del form a tutta larghezza con l'azione principale
    in alto;
  - bersagli di tocco di almeno 44 px (40 px per i pulsanti di riga);
  - campi a 16 px, sotto i quali iOS ingrandisce la pagina al tocco;
  - **tabelle trasformate in schede**: ogni riga diventa un blocco con la
    cella principale come titolo e le altre celle con la propria
    etichetta; le colonne secondarie si possono nascondere. Si attiva con
    la classe `responsive` sulla tabella e `data-label` sulle celle, e
    sarà gestito dal componente tabella, non vista per vista;
  - modali come pannelli che salgono dal basso.
  Ogni archetipo di pagina si progetta per entrambe le larghezze, e ogni
  vista si verifica a 375 px oltre che su desktop. Il flusso Scontrini da
  foto parte invece dal telefono.

## 4. Implementazione tecnica

- **Tailwind 4 con Vite** (già presente nel core): `@import 'tailwindcss'`
  e token in `@theme`; le classi condivise in `@layer components` usano
  le variabili dei token (`var(--color-primario)`), quindi un cambio di
  colore si fa in un punto solo.
- **Dipendenze nuove**, da installare nella cartella del progetto:
  ```
  npm install alpinejs @fontsource/inter @fontsource/barlow-semi-condensed
  ```
  npm scrive le versioni in `package.json` e le fissa in
  `package-lock.json` (Alpine non è più caricato da CDN con una versione
  non bloccata, come nel Dante).
- **Deploy su cPanel**: `npm run build` in locale, poi si carica
  `public/build/`. Sul server non serve Node.
- **Accessibilità di base**: focus visibile su tutti gli elementi
  interattivi, `prefers-reduced-motion` rispettato, contrasto del testo
  ≥ 4,5:1 (per questo le etichette usano `#5f5a52` e non il grigio
  pietra puro), `aria-label` obbligatorio sui pulsanti solo icona
  (`action-btn`).

## 5. Conformità e sicurezza

- Font in locale: nessuna trasmissione dell'IP dei visitatori a Google.
- Nessuna risorsa da CDN esterni (Alpine incluso nel bundle): meno
  dipendenze da terzi e comportamento riproducibile.

## 6. Interazioni con altri moduli

- **Permessi** (`permessi.md`): la sidebar e la sola lettura dipendono da
  `gl_sezioni`/`gl_ruoli_sezioni`, letti tramite gli helper di `User`.
- **Audit Log**: la modale di motivazione per l'anno chiuso scrive in
  `gl_audit_log`.
- **Tutti i moduli**: ogni vista si costruisce con gli archetipi e i
  componenti di questa scheda.

## 7. Storia ed evoluzione

- Analisi del Gestionale APS (Dante) su layout, 174 viste e
  configurazione frontend. Linguaggio visivo giudicato buono e coerente
  (da riprendere); implementazione da non replicare: Tailwind
  installato ma quasi inutilizzato (19 occorrenze), circa 415 righe di
  CSS nel layout, 4.849 attributi `style=` nelle viste e 65 blocchi
  `<style>` locali, Alpine e font da CDN esterni, 4 soli componenti.
- Emerso durante l'analisi, fuori ambito ASD: in `prima-nota/create` del
  Dante le virgolette doppie in un passo di `<x-help-banner :passi="...">`
  chiudono in anticipo l'attributo e fanno stampare testo spurio.
- Palette estratta dai colori reali del logo della Compagnia.
- Direzione approvata da Paolo sulla tela di design (tavole *Elenco
  Soci* e *Fondamenti UI*), incluse la sidebar a voci dirette al posto
  degli hub del Dante, il carattere dei titoli e il giallo nella topbar.
- Da valutare in seguito: portare nel derivato il sistema di aiuto su tre
  livelli del Dante (tooltip sul campo, banner guida, manuale in
  sovrimpressione).
- Su indicazione di Paolo, l'uso da telefono è passato da "adattamento"
  a requisito: aggiunte le regole di §3 (tabelle in schede, bersagli di
  tocco, campi a 16 px, modali dal basso) e le relative classi in
  `app.css`.
- v0.31.0 (04/10/2026): classi dell'assenso del minore e `norma` (§3);
  colonne affiancate nei PDF.
- v0.30.0 (29/09/2026): tema `sito` delle email, pagine di voto del CD,
  area riservata delle domande (§3), icona `orologio`. Tavole "Voto",
  "Email" e "Domanda" approvate da Paolo prima del codice (con le
  correzioni: via le spiegazioni dalla pagina di voto, email nello stile
  delle pagine pubbliche).
- v0.28.0 (28/09/2026): tema del sito per le pagine pubbliche (§3), icona
  `menu` in `x-icona`.
- v0.27.0 (28/09/2026): layout pubblico, componente `x-passi`, classi del
  modulo pubblico e regola sulle email (modelli markdown di Laravel).
  Tavole `Iscrizione-*` approvate da Paolo prima del codice. Corretto in
  §3 un paragrafo ("Se una vista ha bisogno di uno stile nuovo…") finito
  per errore dentro l'elenco dei modelli PDF.
- v0.32.0 (05/10/2026): sezione 11 di `app.css` "Impostazioni › Ente":
  `ente-griglia`, `ente-periodi`, `dati-elenco`, `iscrizioni-stato`,
  `periodo-riga` (`-testa`, `-sede`, `-testi`, `-rasd`), scelte estese con
  descrizione e norma (`scelta-estesa`, `scelta-testo`, `scelta-titolo`,
  `scelta-desc`, `scelta-opzioni.tre`), `td-pila` (testo e sottotitolo
  impilati in una cella che su telefono diventa riga etichetta/valore),
  lista di controllo (`lista-controllo*`: segno verificato/da fare, casella
  per le voci a mano con bersaglio di 44 px via `::after` senza ingrandire
  il disegno), `passaggio-colonne`, `chiuse-corpo`. Tavole "Impostazioni ›
  Ente" sulla tela approvate da Paolo prima del codice.
- v0.32.1 (05/10/2026, collaudo G3): dati fissi dell'ente in due righe,
  `ente-dati-recapiti` (codice fiscale, email, PEC, telefono: 200 px, due
  colonne uguali, 180 px) e `ente-dati-banca` (banca, IBAN più largo, BIC
  di 180 px); fino a 1200 px due colonne (sotto, email e PEC sarebbero
  troppo strette per un indirizzo PEC), a 768 px una; l'ultima riga senza
  margine inferiore, come quando l'IBAN era fuori dalle righe. Verificato a
  1440, 1280, 1201, 1200, 901, 769, 768 e 375 px senza scorrimento
  orizzontale, anche con l'errore sull'IBAN.
- v0.33.0 (05/10/2026): link personale per l'informativa (tavole
  `Privacy-1…5`, approvate da Paolo). Pagina pubblica con le classi delle
  pagine di voto (`voto-pagina`, `voto-scheda`, `voto-modulo`) e nuove
  `privacy-saluto`, `privacy-testo` (informativa in un `<details>` aperto,
  corpo che scorre fino a 360 px, freccia `.iscr-freccia` visibile anche su
  desktop), `privacy-scelte`, `privacy-piede`; dichiarazioni con
  `.dichiarazione` della domanda. Area riservata: `btn-danger-outline`
  (revoca), `conteggio-ko` (numero nel pulsante "Link privacy"),
  `link-privacy-conti`, `link-privacy-rinvio`, `link-privacy-elenco`.
  Email: `p.indirizzo` va a capo (`word-break: break-all`). Verificato a
  1440 e 375 px.
- v0.34.0 (06/10/2026): sezione 12 di `app.css` "Audit Log" (tavole
  `Audit-1…5`, approvate da Paolo): `audit-fascia` (`certificato`,
  `non_certificato`, `anomalia`), `kpi-row-5` (5 indicatori; 3 colonne fino
  a 1200 px, 2 su telefono), tabella `audit-tabella` con un `<tbody>` per
  riga (riga e dettaglio aperto insieme, Alpine `aperta`), `audit-diff`
  (prima/dopo, valori cambiati in rosso e verde), `audit-meta`,
  `audit-verifica-form`, `audit-risultato`, `audit-certifica`,
  `audit-colonne`, `audit-passi`. PDF: le larghezze delle colonne vanno
  sulle celle d'intestazione (`th.c-…`), perché DomPDF ignora `<col>`
  (corretto anche il libro soci). Verificato a 1440 e 375 px.
- v0.34.1 (06/10/2026): **finestre** (`x-modale`) mai più alte dello
  schermo: corpo che scorre, piede fermo, figli del corpo non
  restringibili; su telefono al massimo il 92% dell'altezza. **Moduli
  nelle finestre** (`form-modale`) più compatti: etichette 12 px, campi
  34 px (38 su telefono, testo sempre a 16 px su telefono per iOS), file
  12–13 px. **Paginazione** (`x-paginazione`): parametri facoltativi
  `scelte` e `scelta` per «Righe per pagina» (`per_pagina`, gli altri
  parametri restano). Audit Log: `audit-numero`, riga compatta su
  telefono (freccia in alto a destra).
- v0.35.0 (06/10/2026): sezione 13 di `app.css` "Prima Nota" (tavole
  `PN-1…PN-9`, approvate da Paolo): importi `importo-entrata` (verde, con
  «+»), `importo-uscita`, `importo-neutro` (giroconti); `pn-numero` (cifre
  tabellari), `pn-desc`, `pn-etichette`, `badge-tag`; modulo a due colonne
  `pn-colonne`/`pn-colonna` con riquadro d'aiuto `pn-aiuto` (una colonna
  sotto i 1100 px); scelte con sottotitolo `scelta-testo`/`scelta-sub`,
  `pn-scelte`; giustificativi `pn-allegati`/`pn-allegato`, `pn-file`;
  lista di controllo della chiusura `pn-controlli`/`pn-controllo`;
  categorie `pn-categorie`, `pn-categoria-spenta`, freccia `pn-su` (la
  freccia «giù» ruotata). Tabelle responsive: `.td-solo-mobile` (cella
  visibile solo su telefono, es. la riga «Importo» col segno al posto di
  Entrata/Uscita/Stato) accanto a `.td-solo-desktop`. Registro PDF A4
  orizzontale `pdf/prima-nota`. Verificato a 1440 e 375 px.
