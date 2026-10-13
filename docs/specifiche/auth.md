# Accesso, protezione delle rotte e layout (primo strato web)

**Stato:** in sviluppo (codice consegnato)
**Introdotto in:** v0.19.0 (2026-09-23)
**File coinvolti:**
- `app/Http/Controllers/AuthController.php` (nuovo)
- `app/Http/Middleware/UtenteAttivo.php`, `PermessoMiddleware.php` (nuovi)
- `bootstrap/app.php` (registrazione alias `attivo` e `permesso`)
- `routes/web.php` (riscritto)
- `config/navigazione.php` (nuovo)
- `resources/views/layouts/app.blade.php`, `auth/login.blade.php`,
  `dashboard.blade.php` (nuove)
- `resources/css/app.css` (classi `login-*` e `form-check`)
- `public/images/logo-arcieri.png` (logo)

## 1. Logica

Il core non ha uno strato web: `routes/web.php` conteneva solo la rotta
di benvenuto di Laravel, e non esistono controller, login né viste.
Dell'applicazione esistono il database e i model. Questo modulo è il
primo mattone dello strato web, su cui poggiano tutti gli altri: accesso,
protezione delle rotte per sezione, layout con sidebar.

Scartati gli starter kit di Laravel 12: si portano dietro React, Vue o
Livewire con le loro librerie di interfaccia, in contrasto con lo stack
Blade + Alpine e con le regole di `ui.md`. Scelta un'autenticazione
scritta a mano, ripresa dal Gestionale APS (Dante), dove è già in uso.

Escluso per ora: recupero automatico della password via email (richiede
SMTP configurato). Il link "Password dimenticata?" della pagina di
accesso apre una mail precompilata verso `admin@arciericastelviscardo.it`;
la password viene poi reimpostata da chi amministra il gestionale. Da
sostituire con il recupero automatico quando si configura la posta.

## 2. Struttura dati

Nessuna modifica di schema. Usa `gl_users` (`email`, `password`,
`attivo`, `ultimo_accesso`, `ruolo`) e, per i permessi,
`gl_sezioni`/`gl_ruoli_sezioni` tramite gli helper di `User`
(`permessi.md` §4).

## 3. Comportamento / flusso

1. **Login** (`/login`, rotte `login` e `login.submit`): entra solo un
   utente con `attivo = true`. Dopo 5 tentativi falliti per coppia
   email+IP, il login si blocca per 60 secondi. In caso di errore il
   messaggio è generico ("Email o password non corrette") per non
   rivelare quali account esistono. Al successo: rigenerazione della
   sessione, aggiornamento di `ultimo_accesso`, redirect alla pagina
   richiesta o alla dashboard.
2. **A ogni richiesta** dell'area riservata, il middleware `attivo`
   ricontrolla il flag: un utente disattivato mentre è collegato viene
   disconnesso subito, non al login successivo.
3. **Rotte di un modulo**: ogni gruppo porta
   `->middleware('permesso:<sezione>')`; chi non ha accesso riceve 403.
   La sola lettura non blocca la rotta: sono viste e controller a
   impedire le modifiche con `puoModificare()`.
4. **Sessione scaduta (errore 419)**: se il token di un modulo non è
   più valido (pagina caricata prima che la sessione scadesse o
   cambiasse), non si mostra la pagina d'errore. Su "Esci" si va
   direttamente al login (l'utente voleva uscire comunque); su qualunque
   altro modulo si torna indietro con i dati già inseriti (password
   esclusa) e l'avviso "La sessione è scaduta: controlla i dati e
   riprova". Gestito una volta sola in `bootstrap/app.php`.
5. **Sidebar**: generata da `config/navigazione.php`. Una voce compare
   solo se la sua rotta esiste già **e** il ruolo accede alla sezione; un
   gruppo senza voci non compare. Il menu cresce da solo man mano che si
   costruiscono i moduli, senza voci verso pagine vuote.

**Convenzioni per i moduli futuri** (anche in testa a `routes/web.php`):
- rotte con nome `<modulo>.<azione>`, pagina principale `<modulo>.index`
  (è il nome che la sidebar cerca);
- un gruppo di rotte per modulo, con `permesso:<codice sezione>`;
- rotte aggiunte solo quando il controller esiste.

## 4. Implementazione tecnica

- **Dal Dante, ripresi**: struttura di `AuthController` (`showLogin`,
  `login`, `logout`), nomi delle rotte, rigenerazione sessione,
  `ultimo_accesso`; l'idea di `CheckRole` di ricontrollare `attivo` a
  ogni richiesta (qui middleware separato `attivo`); nome e sintassi di
  `PermessoMiddleware` (`permesso:x`, OR con `|`), così lavorare sui due
  progetti non cambia abitudini.
- **Rispetto al Dante, aggiunti**: blocco dei tentativi
  (`RateLimiter`); condizione `attivo => true` direttamente in
  `Auth::attempt()`.
- **Rispetto al Dante, semplificato**: `PermessoMiddleware` si appoggia
  su `User::puoAccedere()` invece che su un `PermessiService`, perché qui
  i permessi sono a sezione intera, senza funzioni granulari.
- **Alias dei middleware** registrati in `bootstrap/app.php` con
  `$middleware->alias([...])` (Laravel 12 non ha più `Kernel.php`).
- **Layout**: la costruzione del menu è un piccolo blocco `@php` in
  testa al layout. Se crescesse (contatori per voce, come "Domande 2"),
  andrà spostata in un View Composer.
- **Anno sociale in sidebar**: provvisoriamente l'anno solare corrente;
  da collegare all'anno sociale/contabile con Prima Nota.
- **Ragione sociale in topbar e footer**: da `config('app.name')`, cioè
  `APP_NAME` in `.env` (es. `APP_NAME="Compagnia Arcieri Castel Viscardo
  A.S.D."`).
- **Pagina di accesso**: pulsante per mostrare/nascondere la password
  (Alpine, con `aria-pressed` e etichetta che cambia), basato sulla
  classe generica `form-input-azione`, riusabile per altri campi con
  un'azione interna.
- **Gestione del 419**: callback di render su `HttpException` con
  stato 419 (Laravel converte `TokenMismatchException` in questa
  eccezione prima di chiamare i callback).
- **Messaggi flash**: `session('success')` e `session('error')`
  diventano avvisi `alert-ok`/`alert-ko` in cima alla pagina.

## 5. Conformità e sicurezza

- Protezione da tentativi ripetuti di indovinare la password.
- Messaggio di errore che non rivela l'esistenza di un account.
- Revoca immediata dell'accesso alla disattivazione dell'utente.
- Controllo di accesso per sezione a livello di rotta (minimizzazione
  dell'accesso, `permessi.md` §5).

## 6. Interazioni con altri moduli

- **Permessi** (`permessi.md`): `permesso:` e la sidebar usano gli
  helper di `User`.
- **UI** (`ui.md`): layout e login usano solo classi di `app.css`.
- **Utenti** (v0.40.0): pagina Utenti per creare, modificare e
  disattivare; la password si sceglie con il link per email.
- **Tutti i moduli**: seguono le convenzioni di §3.

## 7. Storia ed evoluzione

- Scoperto che il core non ha alcuno strato web (solo la rotta di
  benvenuto) al momento di scrivere il layout.
- Proposta un'autenticazione scritta a mano; Paolo ha fornito controller
  e middleware del Gestionale APS, da cui sono state riprese le soluzioni
  già collaudate con le correzioni di §4.
- Il controllo di `attivo` al login chiude il punto rimasto aperto
  sull'analisi di `User.php`.
- Primi test di Paolo: errore 419 su "Esci" in simulazione telefono
  (pagina con token non più valido), da cui la gestione del 419 in §3;
  aggiunti il pulsante mostra/nascondi password e il link "Password
  dimenticata?" verso l'indirizzo dell'amministratore.

- **v0.40.0 (13/10/2026)**: «Password dimenticata?» non è più un link
  email all'amministratore, ma una pagina (`PasswordController`, tavola
  UT-3b):
  - si chiede l'email; la risposta è sempre la stessa e il link parte solo
    a un utente attivo;
  - il link vale 24 ore e una volta sola (broker di Laravel, tabella
    `gl_password_reset_tokens`, `config/auth.php`);
  - password di almeno 12 caratteri, poi si entra subito;
  - limiti: 5 richieste l'ora per indirizzo, `throttle:10,1` sulle due
    pagine.

  Lo stesso link lo manda la pagina Utenti a un utente nuovo o a chi l'ha
  persa: nessuno sceglie la password di un altro. Dettaglio in
  `analisi-ruoli-utenti.md` §8.
