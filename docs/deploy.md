# Deploy — primo rilascio su arco.valconi.it (ambiente di collaudo)

**Stato:** eseguita il 27/09/2026, verifiche del §4 tutte superate
**Introdotto in:** v0.25.1 (2026-09-27)
**Ambiente:** SupportHost, account cPanel `uiynixlg`, sottodominio
`arco.valconi.it`, PHP 8.3 (CLI e web), Composer in `~/bin`.

## 1. Logica

Primo rilascio del gestionale su un server reale, deciso quando la
sezione Soci è stata completata. È un **ambiente di collaudo**: il dominio
definitivo e il provider non sono ancora scelti, quindi il gestionale
vive su un sottodominio di `valconi.it` e verrà migrato più avanti.

Regole di questo ambiente:
- **solo dati di prova**, nessun socio reale: i dati personali dei soci
  vanno presso il fornitore definitivo, con accordo sul trattamento dei
  dati (DPA);
- da qui in poi **la formula dell'hash dell'audit log è congelata**
  (`AuditIntegrityService::rigaPerHash()`): il primo record in un database
  che si vuole conservare rende la formula immodificabile. In collaudo il
  database si potrà ancora ricreare da zero, ma conviene abituarsi a
  considerarla fissa;
- la migrazione futura sarà la ripetizione di questa procedura sul nuovo
  server, più la copia del database e dei file caricati.

## 2. Struttura sul server

```
/home/uiynixlg/
├── bin/composer                 Composer (installato a mano)
├── gestionale-asd/              applicazione Laravel (FUORI dalla radice web)
│   ├── app/ config/ …
│   ├── public/                  unica cartella esposta al web
│   ├── storage/                 log, sessioni, file caricati (privati)
│   └── .env                     configurazione di produzione (mai nello ZIP)
└── subdomains/
    └── arco.valconi.it  →  symlink a ~/gestionale-asd/public
                            (document root del sottodominio in cPanel:
                            /subdomains/arco.valconi.it)
```

Stessa architettura a symlink del Gestionale APS: il codice, il `.env` e i
file caricati dai soci non sono mai raggiungibili dal browser.

## 3. Procedura

### 3.1 Preparazione in locale (PC)

1. Tutto salvato su git: `git status` deve essere pulito (altrimenti
   commit).
2. Compilare CSS e JS:
   ```
   npm run build
   ```
3. Creare l'archivio del codice (solo i file versionati: esclude da sé
   `.env`, `vendor/`, `node_modules/`, `public/build/`, `public/hot` e i
   file di prova in `storage/`):
   ```
   git archive -o deploy.zip HEAD
   ```
4. Creare a parte l'archivio degli asset compilati (`public/build` è
   escluso da git): in Esplora risorse, tasto destro su `public\build` →
   *Comprimi in file ZIP* → `build.zip`.

### 3.2 Database (cPanel → Database MySQL)

1. Crea il database: `uiynixlg_asd`.
2. Crea l'utente: `uiynixlg_asd`, con password lunga generata dal pannello
   (annotala: serve nel `.env`).
3. Aggiungi l'utente al database con **tutti i privilegi**.

### 3.3 Caricamento dei file (cPanel → File Manager)

1. Nella home (`/home/uiynixlg`) crea la cartella `gestionale-asd`.
2. Entra in `gestionale-asd`, carica `deploy.zip` ed estrailo lì; poi
   elimina lo ZIP.
3. Entra in `gestionale-asd/public`, carica `build.zip` ed estrailo: deve
   risultare `gestionale-asd/public/build/manifest.json`. Elimina lo ZIP.

### 3.4 Collegamento del sottodominio (Terminale)

La document root di `arco.valconi.it` (cPanel → *Domini*) è
`/subdomains/arco.valconi.it`, cioè `~/subdomains/arco.valconi.it`.
Il WordPress di prova che c'era è stato rimosso prima del deploy.
```
ls -la ~/subdomains/
rmdir ~/subdomains/arco.valconi.it      # solo se esiste ed è vuota
ln -s ~/gestionale-asd/public ~/subdomains/arco.valconi.it
ls -l ~/subdomains/arco.valconi.it
```
L'ultima riga deve mostrare
`arco.valconi.it -> /home/uiynixlg/gestionale-asd/public`.

**Caso reale al primo rilascio**: la cartella non era vuota. Conteneva
residui di WordPress (`licenza.html`, `error_log`, `cgi-bin`), la cartella
`.well-known` di AutoSSL (il pannello la ricrea nella nuova radice) e tre
file generati dal cPanel da conservare:
- `.htaccess` con il **gestore PHP** scelto in *MultiPHP Manager*
  (`AddHandler application/x-httpd-ea-php82___lsphp …`): senza, il sito
  userebbe un PHP diverso da quello scelto;
- `.user.ini` e `php.ini` (MultiPHP INI Editor): percorso del log degli
  errori PHP.
Procedura seguita: cartella rinominata in `.bak`, collegamento creato,
blocco del gestore PHP aggiunto **in testa** all'`.htaccess` di Laravel
(che resta indispensabile), file `.ini` copiati in `public/`.

**Esito della verifica (primo rilascio)**: dopo l'allineamento in
*MultiPHP Manager* il blocco del gestore è stato tolto dal pannello
dall'`.htaccess`, ma il sito usa comunque **`ea-php83`**
(`/opt/cpanel/ea-php83/root/etc/php.ini`), mentre il Terminale usa
**`alt-php83`**. Stessa versione (8.3.33), configurazioni separate:
- limiti del sito già sufficienti (`upload_max_filesize` e `post_max_size`
  256M, `memory_limit` 1024M): nessuna modifica; il limite di 5 MB per
  file lo impone comunque il gestionale in validazione;
- **le estensioni del sito si verificano a parte** (quelle attivate in
  *Select PHP Version* valgono per il Terminale, non per `ea-php83`);
- eventuali modifiche future ai parametri PHP del sito vanno fatte in
  *MultiPHP INI Editor* (non in *Select PHP Version*).

**Problema emerso al collaudo**: il caricamento dei documenti dava errore
500, `Class "finfo" not found`: in `ea-php83` manca l'estensione
`fileinfo`, che Laravel usa per verificare il tipo reale dei file (era
attiva solo nel PHP del Terminale). Soluzione indicata: far usare al sito
lo stesso PHP del Terminale, portando `arco.valconi.it` a **inherit** in
*MultiPHP Manager*, così vale il selettore di CloudLinux (*Select PHP
Version*, `alt-php83`, estensioni già configurate); in alternativa,
chiedere al supporto SupportHost di attivare `fileinfo` in `ea-php83`.
Verifica: il percorso `ini` della pagina di controllo deve iniziare con
`/opt/alt/php83/`, e tutte le estensioni devono risultare presenti.
Lezione per la migrazione: verificare le estensioni **del PHP del sito**,
non solo quelle del Terminale, prima del primo collaudo.

**Esito**: con `arco.valconi.it` su *inherit* il sito usa `alt-php83`
(`/opt/alt/php83/etc/php.ini`), lo stesso PHP del Terminale; `fileinfo`
presente. Mancava `intl`, da attivare in *Select PHP Version →
Extensions*. Poiché ora vale il selettore di CloudLinux, anche i limiti si
impostano lì (*Options*): `upload_max_filesize` 8M, `post_max_size` 10M,
`memory_limit` 256M — i valori alti visti prima erano quelli di
`ea-php83`, non più in uso.

**Due versioni di PHP da allineare**: il Terminale usa `alt-php83`
(CloudLinux, *Select PHP Version*), il sito usava `ea-php82` (*MultiPHP
Manager*). Le estensioni attivate in *Select PHP Version* valgono per la
prima, non necessariamente per la seconda. Allineare il sito in *MultiPHP
Manager* alla stessa versione del Terminale e verificare le estensioni
del sito con un `info.php` temporaneo (da cancellare subito).

**`.htaccess` di `public/` fuori dagli ZIP successivi**: dato che sul
server contiene anche il gestore PHP del cPanel, è escluso da
`git archive` con `.gitattributes` (`public/.htaccess export-ignore`), così
un rilascio non lo sovrascrive. Se un giorno cambiasse l'`.htaccess` di
Laravel nel progetto, andrà aggiornato a mano sul server mantenendo il
blocco del cPanel.

### 3.5 Dipendenze PHP (Terminale)

```
cd ~/gestionale-asd
composer install --no-dev --optimize-autoloader
```
`--no-dev` esclude gli strumenti di sviluppo; DomPDF viene installato
perché è una dipendenza normale.

### 3.6 Configurazione `.env`

1. Crea il file partendo dall'esempio:
   ```
   cp .env.example .env
   ```
2. Modificalo dal File Manager (tasto destro → *Edit*; mostra i file
   nascosti con *Impostazioni → Show Hidden Files*). Valori da impostare:
   ```
   APP_NAME="Compagnia Arcieri Castel Viscardo A.S.D."
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://arco.valconi.it
   APP_LOCALE=it

   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=uiynixlg_asd
   DB_USERNAME=uiynixlg_asd
   DB_PASSWORD=la-password-annotata

   SESSION_SECURE_COOKIE=true
   MAIL_MAILER=log
   ```
   Per `SESSION_DRIVER`, `CACHE_STORE` e `QUEUE_CONNECTION` usa **gli stessi
   valori del `.env` locale**, che funzionano già con le tabelle del core.
3. Genera la chiave dell'applicazione:
   ```
   php artisan key:generate
   ```
   **`APP_DEBUG=false` è obbligatorio**: con `true` una pagina d'errore
   mostrerebbe a chiunque configurazione e dati.

### 3.7 Database: tabelle e dati iniziali (Terminale)

```
php artisan migrate --force
php artisan db:seed --force
```
In produzione il seeder **non** crea l'utente di prova
(`DatabaseSeeder`, solo in `local`) e l'anagrafica dell'associazione nasce
con i dati reali della Compagnia (`AssociazioneSeeder`, con `firstOrCreate`).

### 3.8 Primo utente (Terminale)

```
php artisan tinker
```
```php
$u = App\Models\User::create(['name' => 'Paolo', 'email' => 'indirizzo@reale.it', 'password' => 'password-lunga-e-unica', 'attivo' => true]);
$u->assegnaRuoli(['presidente']);
exit
```
Dalla v0.40.0 i ruoli stanno nella tabella `gl_users_ruoli` (un utente può
averne più d'uno). Solo il primo Presidente si crea con tinker: gli altri
utenti li crea il Presidente (o il Segretario) dalla pagina **Utenti**, e
ognuno sceglie la propria password con il link che riceve per email.

### 3.9 Permessi e ottimizzazione (Terminale)

```
chmod -R 775 storage bootstrap/cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```
Dopo ogni modifica al `.env` va rilanciato `php artisan config:cache`,
altrimenti Laravel continua a usare la configurazione precedente.

### 3.10 HTTPS e limiti di PHP (cPanel)

1. *SSL/TLS Status*: verifica che `arco.valconi.it` abbia il certificato
   AutoSSL (altrimenti *Run AutoSSL*).
2. *Domini*: attiva **Force HTTPS Redirect** per `arco.valconi.it`.
3. *Select PHP Version → Options*: `upload_max_filesize = 8M`,
   `post_max_size = 10M` (i documenti dei soci arrivano a 5 MB).

## 4. Verifiche dopo il rilascio

- [ ] `https://arco.valconi.it` apre la pagina di accesso, con lucchetto
      e stile corretto (se manca lo stile: `public/build` non estratto o
      file `public/hot` presente).
- [ ] Login con l'utente creato; 6 password sbagliate → blocco.
- [ ] Soci: inserimento di un socio di prova, modifica, cessazione.
- [ ] Documenti: caricamento, anteprima, download.
- [ ] PDF del libro soci e CSV.
- [ ] Da telefono (rete mobile, non Wi‑Fi di casa): elenco e scheda.
- [ ] In tinker: `App\Services\AuditIntegrityService::verificaCatena()['anomalie']`
      restituisce `[]`.
- [ ] `https://arco.valconi.it/.env` e `https://arco.valconi.it/composer.json`
      devono dare errore 404 (verifica che il web veda solo `public/`).

## 5. Rilasci successivi

1. In locale: commit, `npm run build`, `git archive -o deploy.zip HEAD`,
   `build.zip`.
2. Sul server: estrarre `deploy.zip` in `~/gestionale-asd`
   (sovrascrivendo) e `build.zip` in `public/` (sostituendo la cartella
   `build` per intero: vecchio manifest e nuovi file non convivono).
3. Terminale, in `~/gestionale-asd`:
   ```
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   php artisan optimize:clear
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   `optimize:clear` sempre: senza, può restare la cache delle rotte della
   versione precedente e le rotte nuove risultano "not defined" (errore
   500 del collaudo del 29/09/2026).
   Se `build.zip` contiene percorsi che iniziano con `public/build/` si
   estrae nella radice, se con `build/` si estrae con `-d public`
   (controllare con `unzip -l build.zip | head`); se una cartella è in
   sola lettura, `chmod -R u+w` prima di estrarre.
Il `.env` e la cartella `storage/` del server non sono nello ZIP, quindi
non vengono toccati.

## 6. Interazioni e punti aperti

- **Estensione PHP «zip»** (dalla v0.40.0, importazione dei soci da Excel):
  serve a leggere i file `.xlsx` (`App\Support\LettoreXlsx`).
  - **Non è sempre attiva**: in Laragon mancava (13/10/2026: «Class
    "ZipArchive" not found» nei test).
  - **In locale**: Laragon › menu PHP › Extensions › spunta `zip`, oppure in
    `php.ini` togliere il `;` da `extension=zip`; poi riavviare Laragon e
    controllare con `php -m`.
  - **Sul server**, prima di installare la v0.40.0: cPanel › «Select PHP
    Version» › Extensions › `zip` spuntata (con CloudLinux), oppure da
    Terminale `php -m | grep -i zip`.
  - Senza l'estensione l'importazione risponde «Manca l'estensione PHP «zip»…»
    invece di un errore 500.

- **Lettura degli scontrini** (dalla v0.39.0, `scontrini.md` §4): nel
  `.env` del server `OCR_SPACE_KEY=` con la chiave di OCR.space (la stessa
  di `gestionale-acv`, `conta_impostazioni.ocr_space_api_key`), poi
  `php artisan config:cache`. Mai nel codice, nel database o in chat. Senza
  chiave gli scontrini si caricano lo stesso, con i dati scritti a mano (lo
  dice la riga sotto l'elenco Scontrini). In *Select PHP Version* devono
  essere attive le estensioni `gd` ed `exif` (copia ridotta e raddrizzata
  della foto). Limiti di caricamento: bastano quelli del §3.10
  (`upload_max_filesize` 8M, `post_max_size` 10M; foto fino a 8 MB).
  **In locale (Laragon)**: se compare «SSL certificate problem: unable to
  get local issuer certificate», è lo Scudo Web di Avast che controlla
  l'HTTPS: eccezione `https://api.ocr.space/*` in Avast (Impostazioni ›
  Generale › Eccezioni), oppure `OCR_SPACE_CA=` con un file di certificati
  che contenga anche quello di Avast. Mai spegnere la verifica.

- **Sito ed email definitivi** (Paolo, 29/09/2026): `info@arciericastello.it`
  è già il valore predefinito di `SITO_EMAIL` (se il `.env` del server ha
  ancora `SITO_EMAIL` con la casella Gmail, va tolta o cambiata). Il sito
  `arciericastello.it` si attiva con `SITO_URL=https://arciericastello.it`
  nel `.env`, poi `php artisan config:cache`.
  Valutare anche `MAIL_FROM_ADDRESS` (oggi `arco@valconi.it`) con DKIM e
  DMARC del nuovo dominio.

- **Posta**: `MAIL_MAILER=log` finché non si configura l'SMTP del cPanel;
  serve per le notifiche HIGH dell'audit e per il futuro link del
  consenso privacy (`soci.md`).
- **Cron** (necessario dalla v0.30.0): il comando `domande:scadenze`
  gira ogni ora (chiusura delle votazioni, solleciti, decadenze,
  cancellazioni). In cPanel → *Cron Jobs*, una riga ogni minuto:
  ```
  * * * * * cd ~/gestionale-asd && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
  ```
  (il percorso di `php` è quello dato da `which php` nel Terminale). Senza
  cron le votazioni non si chiudono alla scadenza. Prova a mano:
  `php artisan domande:scadenze --prova`.
  L'uscita va sempre su `/dev/null`: gli errori veri finiscono comunque in
  `storage/logs/laravel.log`. Per una diagnosi si può mandare
  temporaneamente l'uscita in un file (`>> storage/logs/cron.log 2>&1`,
  come nel collaudo del 29–30/09/2026, quando ha confermato che il comando
  orario gira al minuto 00), ma va riportata a `/dev/null` subito dopo:
  `schedule:run` scrive una riga ogni minuto e il file cresce senza
  limite. Dopo averla riportata: `rm ~/gestionale-asd/storage/logs/cron.log`.
- **Posta** (collaudo del 29/09/2026): le email partivano (esito
  `inviata` in `gl_email_log`) ma non arrivavano a usa.net e venivano
  messe in quarantena dall'antivirus. Causa: DKIM e DMARC mancanti su
  `valconi.it`; sistemati con *Email Deliverability → Repair*. Per un
  nuovo dominio: controllare subito SPF, DKIM e DMARC e fare una prova su
  mail-tester.com. Nel `.env`: `APP_NAME` e `MAIL_FROM_NAME` con il nome
  della Compagnia (il nome del mittente è la prima cosa che il
  destinatario vede).
- **Fuso orario** (v0.30.5): il gestionale lavora in `Europe/Rome`
  (`config/app.php`, `APP_TIMEZONE` nel `.env`, che può anche mancare), lo
  stesso fuso della sessione MySQL. Su un nuovo server verificare che
  coincidano: `php artisan tinker --execute="echo now();"` e `SELECT NOW();`
  devono dare la stessa ora. Se MySQL fosse in UTC, le query a mano (e le
  simulazioni del collaudo) andrebbero corrette di conseguenza; le date
  salvate dal gestionale restano comunque giuste.
- **Audit Log (v0.34.0)**: il cron già attivo (`schedule:run` ogni minuto)
  lancia anche `audit:verifica-integrita` il 1° di ogni mese alle 05:00;
  con anomalie avvisa per email il Presidente e gli eventuali Revisori.
  Certificati e attestazioni in `storage/app/private/audit/`: vanno nel
  backup e nella copia di `storage/app/private` alla migrazione. La
  colonna `gl_audit_log.created_at` è DATETIME: la catena non dipende dal
  fuso di MySQL del nuovo server.
- **Tessere CSEN (v0.37.0)**: lo stesso cron lancia `tessere:pulisci` ogni
  giorno alle 03:30 (cancella i CSV del portale CSEN rimasti in anteprima
  in `storage/app/private/tmp/tessere`). La migration 000100 si ferma
  prima di toccare le tabelle se in `gl_tesseramenti` ci sono numeri vuoti
  o ripetuti: correggerli e rilanciare.
- **Test automatici**: girano su MySQL, non su SQLite (le migration usano
  istruzioni MySQL). Dalla v0.33.0 `phpunit.xml` punta già a MySQL e al
  database `asd_test` (host, utente e password dal `.env`): basta creare
  una volta il database vuoto (in Laragon, HeidiSQL o
  `mysql -u root -e "CREATE DATABASE asd_test"`) e lanciare
  `php artisan test`. Il database dei test viene azzerato a ogni giro: non
  usare mai quello di sviluppo. (La forma `DB_CONNECTION=mysql … php
  artisan test` funziona solo nelle shell Unix, non in Cmder.)
- **Migrazione al dominio definitivo**: ripetere §3 sul nuovo server,
  poi copiare database (`mysqldump`) e `storage/app/private`, e aggiornare
  `APP_URL`.
- **Backup**: quelli giornalieri di SupportHost coprono file e database;
  prima di ogni rilascio con migration conviene comunque un dump manuale.

## 7. Storia ed evoluzione

- v0.40.0 (13/10/2026):
  - migration `000103`: ruoli nella tabella `gl_users_ruoli`, tolta
    `gl_users.ruolo`, sezione «Utenti» con i permessi; nessun seeder da
    rilanciare;
  - §3.8 riscritto: dopo il primo Presidente gli utenti si creano dalla
    pagina Utenti;
  - il link per la password usa `gl_password_reset_tokens` (c'era già) e
    la posta del server;
  - l'importazione dei soci legge l'Excel senza librerie nuove (nessun
    `composer require`), ma con l'estensione PHP **zip** da attivare se manca
    (§6; in Laragon mancava);
  - `tessere:pulisci` toglie anche i file dell'importazione rimasti più di
    un giorno.

- v0.37.0 (07/10/2026): comando giornaliero `tessere:pulisci`; controllo
  dei numeri di tessera nella migration (§6).
- v0.30.5 (04/10/2026): fuso dell'app in ora italiana (§6, "Fuso
  orario").
- v0.30.4 (04/10/2026): regola per l'uscita del cron (§6): sempre
  `/dev/null`, il file `cron.log` usato nel collaudo va tolto.

- v0.30.0 (29/09/2026): cron obbligatorio per `domande:scadenze`; note
  sulla posta (DKIM e DMARC) e sui test su MySQL (§6).

- Scelto un ambiente di collaudo su `valconi.it` (hosting già in uso e
  collaudato con il Gestionale APS) in attesa di dominio e provider
  definitivi; confronto dei provider in chat: raccomandato SupportHost,
  con datacenter europeo e DPA.
- Durante la preparazione: PHP portato a 8.3 anche da riga di comando;
  estensioni MySQL nella variante `nd_` di CloudLinux (le due famiglie
  sono alternative); Composer installato in `~/bin` come per il Dante.
- Corretti prima del rilascio, perché avrebbero avuto effetti in
  produzione: utente di prova creato dal seeder anche fuori dal locale;
  `AssociazioneSeeder` con dati d'esempio e `updateOrCreate`.
- v0.32.0 (05/10/2026): dopo la migration, una volta sola,
  `php artisan db:seed --class=ProfiloEnteSeeder --force` (periodi B.A.S.
  2027 e A.S.D. 2028) e `php artisan db:seed --class=ImpostazioneSeeder --force`
  (aggiunge `iscrizioni_aperte`, spenta; entrambi non toccano i dati
  esistenti). Da questo rilascio `/iscrizione` è chiusa al pubblico finché
  il Presidente non la apre da Impostazioni › Ente. Il nome nella barra
  dell'area riservata viene dal profilo dell'ente, non più da `APP_NAME`.
