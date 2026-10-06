# Test domande di iscrizione e voto CD (collaudo v0.30.3, aggiornata alla v0.34.2)

Lista di collaudo manuale su arco.valconi.it. Collaudo della v0.30.3
concluso il 30/09/2026: blocchi A–F superati, pulizia fatta. Collaudo della
v0.32.0 (blocco G, A11–A13) del 05/10/2026: superato, con la sola richiesta
sulla disposizione dei dati fissi (G3), fatta nella v0.32.1 (installata).
Dalla v0.33.0 blocco H (link personale per l'informativa). Copia viva,
con le spunte, nel documento Claude «Test domande di iscrizione e voto CD»;
questa copia nel repository e `claude/test-domande-iscrizione.md` nel
progetto vanno tenute uguali.
Per ogni prova fallita portare nella chat di debug: numero della prova, id
della domanda, cosa si è fatto, atteso, risultato, screenshot, righe di
`storage/logs/laravel.log` dell'orario.

## Preparazione
- Iscrizioni online (dalla v0.32.0 chiuse al pubblico per impostazione predefinita): per i blocchi A–F, che usano finestre anonime, aprirle da Impostazioni › Ente («Apri le iscrizioni») insieme alla modalità test; richiuderle alla fine (F4). Con le iscrizioni chiuse si può provare il modulo anche solo da collegati: le domande nascono comunque di prova.
- Modalità test: `php artisan tinker --execute="App\Models\Impostazione::set('domande_modalita_test', true, 'boolean');"`
- CD di prova in `gl_governance_cd` (senza schermata, via SQL: vedi «CD di prova» sotto): Andrea Papini (Presidente), Massimo Papini, Paolo Zanetti (Segretario e Tesoriere), Luigi Tascio, tutti soci attivi con `valconi@usa.net`. Senza CD la votazione non si apre (causa della A6 del 29/09).
- Cron attivo (`deploy.md` §6, output su `/dev/null`); `php artisan domande:scadenze --prova` senza errori. Pagine anche a 375 px.
- Fuso orario (dalla v0.30.5): gestionale e MySQL sono entrambi in ora italiana, quindi NOW() e CURDATE() delle query danno gli stessi valori del gestionale e le simulazioni usano i giorni esatti. Verifica: `php artisan tinker --execute="echo now(), ' ', config('app.timezone');"` deve dare l'ora italiana e `Europe/Rome`, come `SELECT NOW();` in phpMyAdmin.
- Simulare i giorni (N = id domanda), poi lanciare subito a mano `php artisan domande:scadenze` (il cron lo esegue solo una volta all'ora, al minuto 00):
  - sollecito: `UPDATE gl_domande SET created_at = NOW() - INTERVAL 3 DAY WHERE id = N;`
  - fine 5 giorni: `UPDATE gl_domande SET scadenza_cd = NOW() - INTERVAL 1 MINUTE WHERE id = N;`
  - promemoria diniego (domanda «da rifiutare», diniego non ancora inviato): `UPDATE gl_domande SET esito_il = NOW() - INTERVAL 7 DAY, promemoria_diniego_il = NULL WHERE id = N;`
  - termine per la quota passato ma nel margine (non decade): `UPDATE gl_domande SET quota_scadenza = CURDATE() - INTERVAL 1 DAY WHERE id = N;`
  - decadenza (termine di 5 giorni + 5 di margine): `UPDATE gl_domande SET quota_scadenza = CURDATE() - INTERVAL 6 DAY WHERE id = N;`

## Reset tra un giro e l'altro (dati fittizi: si cancella tutto)
```sql
DELETE FROM gl_voti_cd;
DELETE FROM gl_email_log;
UPDATE gl_domande SET verbale_cd_id = NULL;
DELETE FROM gl_verbali WHERE tipo = 'cd_ammissione' OR domanda_id IS NOT NULL;
DELETE FROM gl_domande;
ALTER TABLE gl_voti_cd AUTO_INCREMENT = 1;
ALTER TABLE gl_email_log AUTO_INCREMENT = 1;
ALTER TABLE gl_domande AUTO_INCREMENT = 1;
```
Poi: `rm -rf storage/app/private/domande`, spostare `laravel.log`, `php artisan cache:clear` e le tre cache, modalità test accesa, finestra anonima nuova per ogni domanda. Il CD non si tocca.

## A. Modulo pubblico e annullamento
- A1 Adulto, telefono: dati dal CF, riepilogo, pagina «domanda inviata». Tendina del documento nello stile del sito. Nessun 429 con più giri avanti/modifica.
- A2 Minorenne, desktop: genitore + minore, email del minore vuota; quota socio giovane; email al genitore.
- A3 Errori: CF nel riquadro accanto al campo, alto come il campo, in rosso; sparisce correggendo; codice incompleto segnalato all'uscita dal campo. Email, obbligatori, consenso: messaggio accanto al campo, dati conservati.
- A4 Nome `[clicca](http://esempio.it)`: rifiutato.
- A5 Email «domanda ricevuta» col nuovo testo (ricezione, trasmissione al CD, esito per email, ricevuta in allegato, «puoi annullarla»): stile del sito, PDF allegato su carta intestata in una pagina (Compagnia Arcieri Castel Viscardo A.S.D., indirizzo pubblico, info@arciericastello.it · arciericastello.it), ore italiane, link di ritiro, niente antivirus.
- A6 Ritiro durante il voto (inviti già partiti): conferma; avviso ai consiglieri; link di voto chiusi.
- A6b Senza CD in carica: avviso «votazione non aperta» al Presidente/segreteria, una volta; inserito il CD, il comando orario apre la votazione; alla scadenza non viene accolta d'ufficio.
- A7 Ritiro di una domanda accolta: entro il termine per la quota si ritira; con termine passato (`quota_scadenza = CURDATE() - INTERVAL 1 DAY`) il link porta a «Iscrizione non annullabile»; dopo l'iscrizione (E6) stessa pagina con il rimando al recesso.
- A8 Riepilogo: link «Leggi lo statuto» e «Leggi l'informativa»; caselle spuntabili subito, senza aprire i testi (se aperti, l'ora compare in ricevuta); desktop: finestra sulla pagina; telefono: pagina del documento con «Torna alla domanda», al ritorno spunte conservate. /informativa-privacy e /statuto leggibili a 375 px; statuto e informativa 1.0; l'informativa riporta sede ufficiale e info@arciericastello.it.
- A9 (v0.30.4, v0.31.1) Statuto pubblicato con le modifiche unite: titolo «Statuto della Compagnia Arcieri Castel Viscardo B.A.S.», art. 1.1 sulla B.A.S. costituita il 24/07/2024, art. 2.4 sul RASD «quando ne possiede i requisiti», art. 31 con le norme transitorie; informativa con titolare «… B.A.S.»; email, piè di pagina e carta intestata «… B.A.S.». Su /statuto l'art. 6 dice «valida con l'invio del modulo» (c. 1), «assenso al tesseramento» del minore dai 14 anni (c. 3), «entro 5 giorni dalla sua presentazione» e «decide il Presidente» (c. 4), «disposto entro 5 giorni» (c. 6); l'art. 16.4 lett. d non dice più «quando l'esito non può più cambiare»; ci sono l'art. 24-bis e l'art. 16.7. La ricevuta di una domanda nuova riporta l'impronta del testo nuovo (diversa da quella delle domande di prova precedenti).
- A10 (v0.30.5) Ore italiane ovunque: «domanda inviata», ricevuta, email ai consiglieri (termine), scheda e cronologia della domanda, voto registrato e verbale mostrano la stessa ora dell'orologio (in ottobre UTC+2); il termine del Consiglio è a 5 giorni esatti dall'invio. Un CF che compie 18 anni oggi è maggiorenne anche tra mezzanotte e le 2.
- A11 (v0.31.0) Minore di 15 anni, desktop, con foto e video: nel passo 2 compare «Dichiarazione del minore» con il badge dell'età, il testo con la frase su foto e video e il riferimento normativo in piccolo; togliendo la spunta di foto e video la frase sparisce. Senza la dichiarazione: errore rosso «Serve la dichiarazione di …» e domanda non inviata, spunte conservate. Con la dichiarazione: ricevuta in una pagina, minore e genitore affiancati, «Il minore dichiara (15 anni), con l'invio del …» con testo e norma; scheda della domanda: «Assenso del minore: prestato il …»; bozza di verbale (dopo la chiusura): paragrafo «Assenso del minore».
- A12 (v0.31.0) Minore di 13 anni, telefono: nessuna dichiarazione del minore; scheda «Non richiesto (meno di 14 anni)»; ricevuta senza il blocco del minore.
- A13 (v0.31.0) Domanda frequente «Mio figlio ha almeno 14 anni: cosa cambia?» con il riferimento in piccolo; adulto: nessun cambiamento, ricevuta in una pagina.

## B. Voto dei consiglieri
- B1 4 inviti, nome e link diversi.
- B2 Favorevole: «voto registrato»; il link non rivota.
- B3 Contrario con motivo di 19 caratteri o meno: errore; da 20 in su (es. 21): ok.
- B4 Token manomesso: «link non valido».
- B5 «Invia di nuovo» (area riservata → Domande → scheda della domanda → tabella dei voti, colonna azioni; solo votazione aperta, consigliere con email che non ha ancora votato, utente con permesso di modifica): arriva lo stesso link; il pulsante non c'è per chi ha votato.
- B6 Senza email (scheda socio di Luigi): avviso in scheda e al Presidente; conta come non votante. Da fare dopo C1 e C4, o rimettendo l'email.

## C. Esiti (una domanda per prova)
- C1 4 favorevoli: chiusa subito; esito «Accolta»; email di accoglimento col nuovo testo: quota, «bonifico da disporre entro il …» (5 giorni, statuto art. 6.6), bonifico a Compagnia Arcieri Castel Viscardo A.S.D., IBAN segnaposto, causale.
- C2 3 su 4 favorevoli: resta aperta; a scadenza accolta, verbale «alla scadenza del termine».
- C3 1 solo voto: a scadenza accolta d'ufficio (email, scheda, verbale).
- C4 3 contrari + 1 favorevole: «da rifiutare», email al Presidente (10 giorni); diniego ≥ 20 caratteri; richiedente riceve diniego con appello 30 giorni.
- C5 2–2 con il Presidente: prevale il suo voto (favorevole → accolta; contrario → da rifiutare).
- C6 Parità senza Presidente: serve un quinto consigliere di prova (Giulia Conti); 2–2, Andrea non vota, a scadenza «voti pari»; Presidente accoglie (accoglimento) o respinge con motivo (diniego). Per ultima.
- C7 Nessun pulsante di chiusura anticipata.

## D. Scadenze
- D1 Sollecito al 3° giorno solo a chi non ha votato, una volta (comando lanciato due volte).
- D2 Promemoria diniego dopo 7 giorni, uno solo.
- D3 Decadenza per quota non versata: con termine passato da 1 giorno la domanda resta accolta e la scheda dice «Termine passato il … · decade il …»; con 6 giorni decade.
- D4 `--prova`: riepilogo, nessuna modifica né email.
- D5 Il cron chiude da solo una votazione scaduta.

## E. Area riservata
- E1 Elenco: filtri, anno, ricerca, riquadri coerenti, 375 px.
- E2 Scheda per ogni stato: voti, motivazioni, cronologia, azioni giuste.
- E3 Permessi: sola lettura senza pulsanti; non Presidente senza decisione e diniego.
- E4 Bozza di verbale (scheda della domanda → «Apri il verbale») per C1, C3, C4, C6: esito, voto di ciascun consigliere con data e ora, motivazioni, decisione del Presidente. Stato «Bozza»: approvazione, numero e PDF arriveranno con il modulo Verbali.
- E5 Ricevuta PDF (carta intestata, una pagina, statuto e informativa aperti il…).
- E6 Iscrizione nel libro soci: bloccata sulle domande di prova; con `is_test = 0`: socio, rinnovo, Prima Nota QUOTE, email di benvenuto; nella scheda del socio, voce privacy «Domanda online n. X del …» con i pulsanti Ricevuta e Domanda; poi pulire a mano.
- E7 Promozione ottobre–dicembre (dal 1° ottobre): quota anno dopo, valida al 31/12 dell'anno successivo; per domanda e generale.
- E8 Eliminazione domanda di prova; pulsante assente sulle vere.

## F. Email e pulizia
- F1 Tutte le email: mittente, stile, link, antivirus, telefono.
- F2 `gl_email_log`: tutto «inviata».
- F3 Nessun errore nuovo in `laravel.log`.
- F4 Pulizia: domande di prova, quinto consigliere, email di Luigi, modalità test spenta (`... set('domande_modalita_test', false, 'boolean');`), iscrizioni online di nuovo chiuse (Impostazioni › Ente).

## G. Profilo dell'ente e iscrizioni chiuse (v0.32.0)
- G1 Impostazioni › Ente da Presidente, 1440 e 375 px: avviso «Nessun periodo in vigore oggi», primo periodo (B.A.S., Art. 148 TUIR in piccolo), dati fissi con IBAN «Da inserire», due periodi (B.A.S. 2027, A.S.D. 2028) con «Passaggio · x su y», iscrizioni chiuse.
- G2 Altro ruolo (segretario): la voce Impostazioni non c'è e `/impostazioni` dà 403.
- G3 Dati fissi (v0.32.1: prima riga codice fiscale, email, PEC, telefono; seconda banca, IBAN, BIC; nel riquadro di Impostazioni › Ente compare anche il BIC): inserire l'IBAN con spazi e minuscole → salvato senza spazi e in maiuscolo; un IBAN sbagliato dà l'errore accanto al campo.
- G4 Nuovo periodo con data di oggi → errore «deve cominciare da domani in poi»; con data futura e forma «associazione» il riquadro RASD non compare; con A.S.D. sì. Eliminarlo dalla sua pagina (Elimina → conferma).
- G5 Lista di controllo A.S.D.: statuto, regime e affiliazione verificati; informativa 2.0 e RASD «Da fare»; spuntare e togliere «Conto bancario» (anche a 375 px: la casella si tocca bene).
- G6 Finestra anonima su `/iscrizione` con iscrizioni chiuse: «Iscrizioni online non ancora aperte» e il pulsante email; da collegati: modulo con l'avviso «Iscrizioni chiuse al pubblico», domanda inviata di prova.
- G7 Denominazione: barra in alto, piè dell'area riservata, piè del sito, email e ricevuta riportano «Compagnia Arcieri Castel Viscardo B.A.S.» e nessuna «A.S.D.».
- G8 Apri le iscrizioni → la finestra anonima vede il modulo; Chiudi → di nuovo la pagina di chiusura. Nel registro: «Iscrizioni online aperte/chiuse».

## H. Link personale per l'informativa, foto e video (v0.33.0)
Preparazione: due soci di prova in Soci › Nuovo socio, un adulto con
`valconi@usa.net` e un minore di 15 anni senza email propria con il genitore
`valconi@usa.net`; un terzo socio senza email. Dettaglio in `privacy-link.md`.
- H1 Scheda dell'adulto: riga «Consenso privacy» Mancante con «Il link andrà a …» e i pulsanti «Invia il link», «In presenza», «Carica modulo»; riga «Foto e video» No, «Non dato». A 375 px i pulsanti vanno a capo e si toccano bene.
- H2 «Invia il link»: messaggio «Link inviato a …»; la riga diventa «In attesa» con data, ora e indirizzo. Email «Informativa privacy: conferma la lettura» nello stile del sito, «Rispondi a» la segreteria, pulsante e indirizzo in fondo che va a capo.
- H3 Pagina del link (telefono e desktop): saluto, socio, versione 1.0, informativa nel riquadro che si apre e chiude, «Apri in una pagina a parte»; «Conferma» senza la dichiarazione → errore rosso accanto alla casella, spunte conservate.
- H4 Con la dichiarazione e foto e video: «Grazie, è registrato» con versione, «Consenso dato», data e ora; riaprendo il link stesso riepilogo. Scheda: «Presente» · «Link personale il … alle …»; «Foto e video» Sì · link personale.
- H5 «Invia di nuovo» (prima di confermare, su un altro socio): il primo link dà «Link non valido», il secondo funziona.
- H6 Minore: email al genitore con oggetto «… per Nome Cognome»; pagina «come genitore di …», testo di foto e video con «dopo averne parlato con lui/lei»; con un minore sotto i 14 anni la frase non c'è.
- H7 Revoca: nella scheda «Registra la revoca» → data prima del consenso dà errore; con data valida e «Per email» la riga diventa «No · Revocato il … · richiesta per email». Dal link: togliendo la spunta già messa → revoca «link personale».
- H8 Socio senza email: «Nessuna email: inseriscila nella scheda…» e «Invia il link» disattivato.
- H9 Elenco Soci: pulsante «Link privacy» con il numero; finestra con i conteggi, i soci senza email per nome, la spunta per rimandare a chi ha un link in attesa e «Vedi l'elenco». «Invia le email» → «Inviati N link privacy.»; secondo clic senza spunta → «Nessun link da inviare.»
- H10 Domanda online di prova con foto e video, iscritta nel libro soci con `is_test = 0` (come E6): nella scheda del nuovo socio «Foto e video» Sì · domanda online.
- H11 Registro: `gl_email_log` con `socio_id` e tipo `privacy_link`; audit «PRIVACY_LINK_INVIATO» senza dati personali; revoca registrata come MEDIUM.
- H12 Pulizia: eliminare i soci di prova (Presidente) e i link: `DELETE FROM gl_privacy_link WHERE socio_id IN (…);`

## I. Audit Log (v0.34.0)
Dettaglio in `docs/specifiche/audit-log.md` §9. Da Presidente, poi da un utente Revisore se c'è.
- I1 Menu: voce «Audit Log» per Presidente (e Revisore); il segretario non la vede e `/audit-log` dà 403.
- I2 Elenco a 1440 e 375 px: fascia «Certificato dell'anno non ancora generato», 5 indicatori, filtri (anno, mese, modulo, rischio, evento, operatore, cerca), righe con data, modulo e riferimento, operazione in italiano («Socio: modifica», «Link privacy inviato»), rischio Alto/Medio/Basso. **v0.34.1**: numero di riga (colonna «N.» su desktop, «n. 22» accanto alla data su telefono); su telefono numero, data, ora e freccia sulla stessa riga; in fondo «Righe per pagina» 10 / 50 / 100 / Tutte, scelta ricordata finché non si esce; dal telefono la prima volta 10.
- I3 Freccia di una riga di modifica di un socio: si apre il dettaglio con «Prima» e «Dopo», i campi cambiati in rosso e verde, numero di riga, evento, IP, inizio dell'hash. Su telefono il dettaglio va a tutta larghezza. **v0.34.1**: aprendo un'altra riga, la precedente si chiude. Un campo cambiato che non si è toccato (es. «luogo_nascita» modificando l'indirizzo) è il luogo ricalcolato dal codice fiscale al salvataggio: succede una volta sola sui soci inseriti senza luogo.
- I4 Filtri: rischio «Medio» dal riquadro degli indicatori; evento «Link privacy inviato»; cerca «%» → nessun risultato; cerca il numero di una riga → la riga. «Togli i filtri».
- I5 CSV e PDF: si scaricano; il PDF ha le colonne di larghezze diverse e la riga «Ultima riga del registro all'estrazione»; nel registro compaiono due righe «Audit Log esportato» (Medio). **v0.34.2**: i file «vuoti» del primo collaudo erano estratti con la ricerca «%» ancora attiva; ora con zero righe i pulsanti sono spenti.
- I6 Verifica integrità → «Verifica adesso»: «Catena integra», righe dell'anno, impronta, «Genera il certificato».
- I7 Genera il certificato: messaggio verde; nello storico la riga con «Da: … (Presidente)» e il PDF (carta intestata, righe per livello, impronta dell'ultima riga, codice del documento). Un secondo certificato: due righe, due PDF diversi. Elenco: fascia verde «Registro integro e certificato». **v0.34.1**: nello storico «Da» con il nome sopra e il ruolo sotto, nessuno scorrimento orizzontale (righe e impronta sotto data e anno); nel PDF il codice del documento a capo, su una riga sola.
- I8 Nuova attestazione: «Scarica la bozza» (PDF con i numeri di oggi); caricare un PDF qualunque con oggetto e data → riga nello storico, «Firmata» e «Relazione» si scaricano; un file .txt dà l'errore accanto al campo e la finestra si riapre. **v0.34.1**: la finestra sta nello schermo (su telefono scorre, i pulsanti restano in fondo) e i campi sono più compatti; la bozza non firmata viene rifiutata («Nel file non c'è una firma digitale…»); la bozza firmata (PDF firmato o file .p7m, firma digitale o CIE) viene accettata. **v0.34.2**: l'oggetto è già compilato.
- I9 Verifica mensile senza aspettare: `php artisan audit:verifica-integrita` → «Catena integra».
- I10 Anomalia simulata (solo sul collaudo, poi si azzera): `UPDATE gl_audit_log SET dettaglio = CONCAT(dettaglio, ' x') WHERE id = (SELECT MIN(id) FROM (SELECT id FROM gl_audit_log) t);` e di nuovo il comando: esce con errore, email «Audit Log: anomalia nella catena di integrità» al Presidente (e al Revisore), fascia rossa nell'elenco, «Genera il certificato» non c'è, la verifica elenca la riga con «Contenuto: l'impronta non si ricalcola».
- I11 Pulizia del collaudo I10: la catena resta rotta (è il suo scopo); va azzerata con il resto dei dati di prova prima dell'apertura (`TRUNCATE gl_audit_log; DELETE FROM gl_audit_log_integrity; DELETE FROM gl_impostazioni WHERE chiave = 'audit_ultima_verifica';` e i PDF in `storage/app/private/audit/`).

## CD di prova (SQL)
Controllo dei soci:
```sql
SELECT id, nome, cognome, email, stato FROM gl_soci
WHERE (nome, cognome) IN (('Andrea','Papini'),('Massimo','Papini'),('Paolo','Zanetti'),('Luigi','Tascio'));
```
Inserimento nel CD (dopo aver verificato che ci siano tutti e quattro):
```sql
INSERT INTO gl_governance_cd (socio_id, carica, data_inizio_mandato, in_carica, created_at, updated_at)
SELECT id,
       CASE WHEN nome = 'Andrea' AND cognome = 'Papini' THEN 'Presidente'
            WHEN nome = 'Paolo' AND cognome = 'Zanetti' THEN 'Segretario e Tesoriere'
            ELSE 'Consigliere' END,
       CURDATE(), 1, NOW(), NOW()
FROM gl_soci
WHERE (nome, cognome) IN (('Andrea','Papini'),('Massimo','Papini'),('Paolo','Zanetti'),('Luigi','Tascio'))
  AND stato = 'attivo';
```
