# Passaggio al dominio definitivo — procedura (14/10/2026) ed esito (19/10/2026)

## 0. Esito: trasloco di account (18–19/10/2026)

**Fatto il 19/10/2026.** Il gestionale è su `https://gestionale.arciericastello.it`.

La procedura qui sotto partiva da un dato sbagliato: che sito e gestionale
fossero nello **stesso account cPanel**. Gli account invece erano due, su
server diversi:
- `uiynixlg` su `gaia`: il gestionale e `arco.valconi.it`;
- `arcieric` su `lia`: `arciericastello.it`, la posta, i DNS.

Il sottodominio creato in `arcieric` (§1) non era raggiungibile da
`uiynixlg`, quindi il collegamento del §2 non era possibile. Paolo ha scelto
di abbandonare `arco.valconi.it`, e il gestionale si è **trasferito**
nell'account `arcieric`:
- database copiato con `mysqldump`;
- cartella copiata in uno ZIP, `vendor` e `.env` compresi;
- `.env` aggiornato;
- cron nuovo, cron vecchio tolto.

Passi, intoppi e rimedi sono in `deploy.md` §0, che descrive l'ambiente
attuale.

Differenze rispetto ai paragrafi seguenti:
- §1, §3, §4, §5, §6, §7 e §10 sono stati fatti nell'account `arcieric`,
  come scritto;
- §2: collegamento a `/home/arcieric/gestionale-asd/public`, dopo il
  trasloco;
- §8: **non fatto**. `arco.valconi.it` non si reindirizza: il gestionale
  vecchio è rimasto in manutenzione e l'account si abbandona;
- §9, il link «Iscriviti» del sito: da fare all'apertura delle iscrizioni.


Fase 0 del piano (`piano-2027.md`): il gestionale passa da
`arco.valconi.it` a **`gestionale.arciericastello.it`** con i dati di
prova, così SSL, cron ed email si collaudano prima dell'Assemblea.

Dati di Paolo (14/10/2026):
- **Dominio**: `arciericastello.it`, registrato su SupportHost.
- **Indirizzo del gestionale**: sottodominio `gestionale.arciericastello.it`.
- **Account cPanel**: lo stesso di oggi (`uiynixlg`). Lì c'è già anche il
  sito `arciericastello.it` (WordPress), che non si tocca.
- **Posta**: casella `portaleacv@arciericastello.it` su SupportHost, SMTP
  porta 465 (SSL).
- **Vecchio indirizzo**: `arco.valconi.it`, reindirizzato al nuovo.
- **Hosting per l'informativa**: SupportHost OÜ, server in Germania.

Il gestionale resta nella stessa cartella (`~/gestionale-asd`) e nello
stesso database. Cambiano solo l'indirizzo, la posta e il `.env`. Durata:
un'ora circa, più l'attesa del certificato SSL (di solito pochi minuti).

## 1. Sottodominio (cPanel › Domini)

1. *Domini* › **Crea un nuovo dominio**.
2. Dominio: `gestionale.arciericastello.it`.
3. Togli la spunta da «Condividi la radice del documento». Come radice
   scrivi `subdomains/gestionale.arciericastello.it`, come per
   `arco.valconi.it`: nel passo 2 si sostituisce con il collegamento al
   gestionale.
4. *Invia*. Il record DNS del sottodominio lo crea il cPanel, perché il
   dominio usa i nameserver di SupportHost. Controllo: *Zone Editor* ›
   `arciericastello.it`, deve esserci un record **A** (o CNAME) per
   `gestionale`.

## 2. Collegare il sottodominio al gestionale (Terminale)

Come per `arco.valconi.it` (`deploy.md` §3.4). La cartella creata dal
cPanel si mette da parte e al suo posto va il collegamento a
`~/gestionale-asd/public`:

```
cd ~/subdomains
ls -la gestionale.arciericastello.it          # cosa c'è (di solito .well-known, a volte .htaccess o .user.ini)
mv gestionale.arciericastello.it gestionale.arciericastello.it.bak
ln -s ~/gestionale-asd/public gestionale.arciericastello.it
ls -l gestionale.arciericastello.it           # deve mostrare -> /home/uiynixlg/gestionale-asd/public
```

Se il cPanel ha messo la radice altrove, usa quel percorso.

Se nella cartella `.bak` c'è un `.htaccess` con un blocco `AddHandler`
(gestore PHP del cPanel), si fa come in §3.4: il blocco va in testa al
`public/.htaccess` del gestionale.

## 3. PHP del sottodominio (cPanel)

Lezione del primo rilascio (`deploy.md` §3.4): il sito deve usare lo
stesso PHP del Terminale.

1. *MultiPHP Manager*: `gestionale.arciericastello.it` su **inherit**.
2. *Select PHP Version* › *Extensions*: controlla che ci siano **zip**
   (nuova, v0.40.0), fileinfo, intl, gd, exif, pdo_mysql, mbstring.
3. Se hai dubbi, la prova di §3.4: un `info.php` temporaneo in `public/`
   deve mostrare il percorso `/opt/alt/php83/…`. Poi cancellalo subito.

## 4. Certificato SSL

1. *SSL/TLS Status*: seleziona `gestionale.arciericastello.it` e
   `www.gestionale.arciericastello.it` (se c'è), poi **Run AutoSSL**.
2. Aspetta che siano verdi (pochi minuti, a volte fino a un'ora). Prova
   `https://gestionale.arciericastello.it`: si deve aprire la pagina di
   accesso con il lucchetto, senza avvisi.

## 5. Casella di posta del gestionale

1. *Account email*: la casella `portaleacv@arciericastello.it` c'è. Apri
   **Connetti dispositivi** e annota il **server in uscita (SMTP)** con SSL:
   di solito `mail.arciericastello.it`, a volte il nome del server
   SupportHost. Porta **465**.
2. *Email Deliverability* › `arciericastello.it`:
   - **DKIM** e **SPF** devono risultare «Valid». Se c'è «Problems exist»,
     premi **Repair** e conferma: il cPanel scrive i record giusti, perché
     i DNS sono suoi.
   - L'SPF deve comprendere il server che spedisce (il cPanel lo propone);
     se il sito WordPress usa un servizio esterno per le email, va aggiunto
     anche quello.
3. **DMARC** (*Zone Editor* › `arciericastello.it` › *Aggiungi record*,
   tipo **TXT**):
   - nome: `_dmarc.arciericastello.it`
   - valore: `v=DMARC1; p=none; rua=mailto:info@arciericastello.it; adkim=r; aspf=r`

   Si parte con `p=none` (solo rapporti, nessun rifiuto) per due o tre
   settimane. Se nei rapporti tutto passa, si sale a `p=quarantine`. Se per
   `valconi.it` hai già un DMARC funzionante, usa la stessa forma.

## 6. `.env` del server

Nel File Manager, `~/gestionale-asd/.env`, cambia solo queste righe e
lascia il resto com'è:

```
APP_URL=https://gestionale.arciericastello.it

MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=mail.arciericastello.it          # quello annotato al passo 5.1
MAIL_PORT=465
MAIL_USERNAME=portaleacv@arciericastello.it
MAIL_PASSWORD="la-password-della-casella"
MAIL_FROM_ADDRESS="portaleacv@arciericastello.it"
MAIL_FROM_NAME="Compagnia Arcieri Castel Viscardo"

SITO_URL=https://arciericastello.it        # solo se il sito è già online su questo indirizzo
```

Poi da Terminale:
```
cd ~/gestionale-asd
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

La password della casella sta **solo** nel `.env` del server: mai nei
documenti, nel codice o in chat.

Le risposte alle email del gestionale vanno all'indirizzo della scheda
Associazione (Impostazioni › Ente › Dati fissi, campo Email). Lì va
`info@arciericastello.it`, se non c'è già.

## 7. Cron

Non cambia niente: stesso account, stessa cartella. Il comando del cron
(`cd ~/gestionale-asd && /usr/local/bin/php artisan schedule:run >>
/dev/null 2>&1`) continua a funzionare. Le email dei comandi (promemoria,
scadenze) prendono il nuovo indirizzo da `APP_URL`.

## 8. Reindirizzare il vecchio indirizzo

Oggi `arco.valconi.it` punta alla stessa cartella `public` del gestionale.
Al suo posto mettiamo una cartella che rimanda tutto al nuovo indirizzo,
pagina per pagina: i link già mandati per email (privacy, voto del CD,
password) continuano a funzionare.

```
cd ~/subdomains
rm arco.valconi.it                       # toglie solo il collegamento, non il gestionale
mkdir arco.valconi.it
cat > arco.valconi.it/.htaccess <<'FINE'
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/\.well-known/
RewriteRule ^(.*)$ https://gestionale.arciericastello.it/$1 [R=301,L]
FINE
```

La riga `.well-known` lascia lavorare AutoSSL, così anche il vecchio
indirizzo mantiene il certificato.

Prova: `https://arco.valconi.it/login` deve aprire
`https://gestionale.arciericastello.it/login`.

Il reindirizzamento si tiene almeno fino a fine 2027. Poi il sottodominio
si può cancellare.

## 9. Sito WordPress

Il link «Iscriviti» (o simile) del sito va portato a
`https://gestionale.arciericastello.it/iscrizione`. Le pagine pubbliche
del gestionale riprendono menu e piè di pagina del sito da
`config/sito.php`: se il menu del sito cambia, va aggiornato lì (lo
faccio io).

## 10. Controlli finali

1. `https://gestionale.arciericastello.it`: accesso, lucchetto verde,
   menu.
2. **Email**: «Password dimenticata?» con il tuo indirizzo. L'email deve
   arrivare da `portaleacv@arciericastello.it` con il link al nuovo
   indirizzo. In Gmail, *Mostra originale*: SPF, DKIM e DMARC tutti
   **PASS**.
3. `https://gestionale.arciericastello.it/iscrizione` da una finestra
   anonima: con le iscrizioni chiuse, la pagina di cortesia.
4. Un PDF (es. libro soci) e un'email con allegato (ricevuta di prova).
5. Il giorno dopo: in *Promemoria* il prossimo invio, nell'Audit Log
   nessun errore. Opzionale: `php artisan schedule:list`.
6. `https://arco.valconi.it` rimanda al nuovo indirizzo.

Dopo il passaggio tutti devono rientrare: la sessione era legata al
vecchio indirizzo.

## 11. Dopo

- `deploy.md` §3.4 e §3.6: il nuovo sottodominio come indirizzo in uso (lo
  aggiorno io appena mi dici che è fatto, con quello che è successo
  davvero, come per il primo rilascio).
- `.env.example`: ha già i valori del nuovo dominio, senza password
  (v0.41.0).
- Lista di collaudo: le prove usano il nuovo indirizzo.
