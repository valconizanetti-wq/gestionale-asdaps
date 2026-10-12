# Test domande di iscrizione e voto CD (collaudo v0.30.3, aggiornata alla v0.39.0 e v0.38.1)

Lista di collaudo manuale su arco.valconi.it. Collaudo della v0.30.3
concluso il 30/09/2026: blocchi A–F superati, pulizia fatta. Collaudo della
v0.32.0 (blocco G, A11–A13) del 05/10/2026: superato, con la sola richiesta
sulla disposizione dei dati fissi (G3), fatta nella v0.32.1 (installata).
Dalla v0.33.0 blocco H (link personale per l'informativa), dalla v0.34.0 blocco I (Audit Log), dalla v0.35.0 blocco J (Prima Nota), dalla v0.36.0 blocco K (Rinnovi quota e Promemoria: superato il 07/10/2026, dopo le correzioni della v0.37.1 e v0.37.2), dalla v0.37.0 blocco L (Tessere CSEN: superato il 07/10/2026, con la v0.37.2 installata), dalla v0.38.0 blocco M (Scadenze dell'associazione e promemoria «N giorni prima»; primo giro del 12/10/2026, correzioni nella v0.38.1: voci «v0.38.1»), dalla v0.39.0 blocco N (Scontrini dal telefono con OCR). Copia viva,
con le spunte, nel documento Claude «Test domande di iscrizione e voto CD»;
questa copia nel repository e `claude/test-domande-iscrizione.md` nel
progetto vanno tenute uguali.
**Come sono scritte le prove (dal 12/10/2026, richiesta di Paolo).** Dal blocco M in poi ogni prova ha: lo **scopo** (che cosa si controlla), **chi** deve entrare (ruolo), che cosa preparare **prima**, i **passi** numerati da eseguire e il **risultato atteso**, con le scritte esatte che devono comparire. I blocchi più vecchi (A–L, già superati) restano nella forma breve. Le prove nuove si scrivono sempre in forma estesa.
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

## J. Prima Nota (v0.35.0, correzioni v0.35.1)
Dettaglio in `docs/specifiche/prima-nota.md`, tavole PN-1…PN-9. Da Presidente (o Tesoriere), poi da Revisore se c'è. **Solo movimenti di prova**: tutto il blocco va azzerato con J12 prima di scrivere i saldi veri del 2027.
- J1 Menu «Prima Nota»: Presidente e Tesoriere vedono «Nuovo movimento» e «Giroconto»; il Revisore vede tutto (anche esportazioni e giustificativi) ma nessun pulsante di modifica, con la fascia «sola lettura»; il segretario, se non ha la sezione, riceve 403 su `/prima-nota`.
- J2 Categorie e tag (`/prima-nota/categorie`): 33 categorie, 23 attive; «Uscite da classificare» con «Di sistema» e senza pulsanti; «Disavanzo/Attivo anno precedente» con «Solo bilancio»; «Gare, manifestazioni e iniziative» (entrata) e «Contributi da Esercenti…» con natura «Da verificare». Nuova categoria di prova in uscita: va in fondo, freccia su la sposta, matita cambia nome e natura, «Disattiva»/«Attiva», cestino la elimina (c'è solo se mai usata). Lo stesso nome di una categoria esistente nello stesso tipo dà errore. **v0.35.1**: l'errore compare dentro la finestra, che resta aperta con il nome e la natura scritti (vale anche per la modifica di una categoria e la rinomina di un tag).
- J3 Anni contabili (`/prima-nota/anni`): se ci sono movimenti 2026 delle domande di prova, il 2026 è il primo anno e chiede i saldi di apertura (vedi J12). Scrivere saldi di prova (es. cassa «150,00», banca «2.340,50», provenienza «Prova collaudo»): il riquadro li mostra.
- J4 Nuovo movimento (uscita, cassa): importo «1.450» → salvato 1.450,00 €; importo «12,5» → 12,50 €; importo «1,450» → errore «ambiguo»; categoria solo tra le uscite attive (cambiando tipo in «Entrata» la tendina cambia); fornitore nuovo scritto a mano, tag «Tettoia», un giustificativo JPG o PDF dal telefono (foto). Salvato: scheda del movimento con giustificativo che si apre, fornitore e tag. «Salva e inserisci un altro» riapre il modulo vuoto con tipo, conto e data rimasti. **v0.35.1**:
  - campo dei giustificativi come la tavola: riquadro tratteggiato, «Aggiungi file» più volte, un'etichetta per file con nome, peso e × per toglierlo; su telefono le etichette una sotto l'altra;
  - con due file scelti e importo «1,450» (errore): i file restano come «già caricato»; corretto l'importo e salvato, ci sono tutti e due;
  - nella scheda i giustificativi hanno il nome codificato `PN2026-<n. movimento>-01.jpg`, non quello originale, anche nel file scaricato;
  - anteprima ridotta dell'immagine (PDF con icona); clic: finestra con l'immagine grande o il PDF, «Apri in una nuova scheda», «Scarica» (su telefono il PDF si apre con i pulsanti);
  - pulsante «Nuovo movimento» anche nella scheda;
  - nell'elenco il clic in un punto qualunque della riga apre il movimento (Ctrl+clic in una nuova scheda); i link nella riga, come il tag, funzionano come prima.
- J5 Errori: senza categoria o con descrizione vuota il movimento non si salva e **non** resta il fornitore nuovo (nessun «fantasma» nel suggerimento del campo); file .txt rifiutato.
- J6 Giroconto da cassa a banca: nell'elenco una riga «Cassa → Banca» senza segno; i saldi di cassa e banca cambiano, entrate e uscite no; con il filtro «Cassa» si vede la riga in uscita. Modifica dell'importo: cambiano entrambe le righe. Eliminazione: spariscono entrambe. Il saldo può andare sotto zero se le uscite superano apertura ed entrate (corretto). **v0.35.1**: «Cassa → Banca» sempre su una riga.
- J7 Elenco a 1440 e 375 px: 5 indicatori (cassa, banca, entrate, uscite, anticipi), filtri anno/conto/tipo/categoria/tag/cerca, totali del filtro in fondo; su telefono una scheda per movimento con «Importo» col segno (+ verde, − rosso), nessuna riga vuota. Il tag «Tettoia» in Categorie e tag mostra movimenti, totale e anno; il clic filtra l'elenco.
- J8 Esportazioni: CSV (apre in Excel con importi all'italiana; una descrizione di prova che comincia con «=» resta testo) e registro PDF A4 orizzontale con gli stessi filtri; nell'Audit Log due righe «Prima Nota esportata».
- J9 Audit Log: le righe dei movimenti, del giroconto, della categoria e del tag di prova ci sono, con i valori prima e dopo nelle modifiche; l'eliminazione di un movimento è «Alto». **v0.35.1**: ogni operazione è **una riga sola**: giroconto creato, modificato o eliminato = una riga «Prima Nota» con «giroconto: Cassa → Banca» nei valori; eliminazione di un movimento con giustificativi = una riga, con i nomi dei giustificativi nei valori «Prima». Moduli con nomi leggibili («Giustificativo», «Anno contabile», «Categoria di Prima Nota»). Nel dettaglio, sotto il riepilogo, l'«Hash SHA-256» per intero.
- J10 Domanda di iscrizione accettata con quota versata (blocco C): nella Prima Nota un'entrata in categoria «Quote associative», non eliminabile da qui («collegato alla quota»). La quota di un socio inserito a mano non ha ancora un modulo (Rinnovi quota, da fare).
- J11 Chiusura (solo come prova di blocco): nel 2026 la lista di controllo segna «L'anno è finito» in rosso e il pulsante di chiusura è spento. **v0.35.1**: al posto di data e riferimento scritti a mano, l'elenco dei verbali dell'Assemblea dell'anno in corso; oggi è vuoto (riquadro «Nessun verbale da scegliere») perché il modulo Verbali non c'è ancora. La chiusura vera, con le correzioni motivate e la riapertura dal Presidente, è coperta dai test automatici (`PrimaNotaTest`); si collauda dal 2028 sul 2027.
- J12 Azzeramento della Prima Nota di prova (prima dell'apertura del 2027 e dopo ogni giro):
```sql
DELETE FROM gl_prima_nota_documenti;
UPDATE gl_prima_nota SET giroconto_id = NULL;
DELETE FROM gl_prima_nota;
DELETE FROM gl_anni_contabili;
DELETE FROM gl_tag;
DELETE FROM gl_soggetti WHERE created_at >= '2026-10-06';  -- solo i fornitori nati nel collaudo
ALTER TABLE gl_prima_nota AUTO_INCREMENT = 1;
```
Poi `rm -rf storage/app/private/prima-nota storage/app/private/tmp/prima-nota`; la categoria di prova di J2 si elimina dalla sua pagina (se è rimasta). Solo dopo: in `/prima-nota/anni` il primo anno sarà il 2027 e lì si scrivono i saldi di cassa e banca al 31/12/2026 di `gestionale-acv`, con il rendiconto approvato come documento.

## K. Rinnovi quota e Promemoria (v0.36.0)
Dettaglio in `docs/specifiche/rinnovi-quota.md` e `promemoria.md`, tavole RQ-1…RQ-6, PR-1, PR-2. Da Presidente o Tesoriere, poi da Revisore se c'è. Prima: due o tre soci di prova attivi, uno adulto con email, uno minorenne di 14–17 anni con genitore ed email del genitore, uno senza email. **Dati di prova**: si azzerano con K11. Le quote si registrano con data di quest'anno (2026): la Prima Nota di prova del 2026 c'è già (J), quindi entrano anche in Prima Nota. Dopo l'azzeramento (J12, K11) e fino al 31/12/2026 una quota si registra con la ricevuta ma senza movimento (contabilità dal 2027: impostazione `contabilita_anno_avvio`).
- K1 Menu: «Rinnovi quota» (Presidente, Tesoriere, Revisore) e «Promemoria» (anche il Segretario, che la vedeva vuota; dalla v0.38.0 vede «Scadenze dell'associazione» e «Tessere in scadenza», non le quote). Il Revisore non vede «Registra quota» né i pulsanti di invio e annullamento.
- K2 Elenco `/rinnovi` a 1440 e 375 px: anno sociale 2026; avviso rosso «Da dichiarare decaduti» per chi non ha versato (siamo dopo il 30 aprile); indicatori; filtri situazione, categoria, cerca; CSV. Anno 2027: tutti «Da versare entro il 31/01». Clic sulla riga: scheda del socio, riquadro «Quote associative».
- K3 Registra quota per il socio adulto (nella scheda e nell'elenco, accanto alla modalità, il conto: «Bonifico · Banca», «Contanti · Cassa»): scelto il socio la pagina si ricarica con anno, importo 70,00, numero della prossima ricevuta (es. 1/2026); bonifico, «Invia la ricevuta» spuntato. Salvato: messaggio con il numero e «inviata a …»; email con il PDF in allegato; in Prima Nota un'entrata «Quota associativa 2026 – …» in Banca, categoria «Quote associative», documento «ricevuta 1/2026». Una seconda quota 2026 dello stesso socio è rifiutata. Data 15/12/2025 (prima del primo anno contabile): ricevuta sì, movimento no, con la nota «movimento nel vecchio gestionale».
- K4 Registra per il minorenne: casella dell'assenso obbligatoria; «Versata da» e codice fiscale del genitore precompilati e obbligatori; avviso «Detrazione per lo sport dei ragazzi: non disponibile» (B.A.S. non iscritta al RASD); email al genitore. Importo cambiato a 25,00 senza note: errore; con una nota: accettato.
- K5 Ricevuta PDF: carta intestata B.A.S., «Quota associativa — anno sociale 2026», n. e data, sede legale e C.F. dell'ente, socio con C.F., per il giovane data e luogo di nascita, «Tiro con l'arco», versata da con C.F., importo in cifre e in lettere (30,00 → «trenta/00»), modalità, validità, dicitura IVA, «Il Tesoriere» con il nome se il CD di prova ha un Tesoriere in carica. Nessun riquadro della detrazione.
- K6 Contanti: il movimento va in Cassa. Socio senza email: «Invia» spento, il messaggio dice di consegnarla.
- K7 Annullamento dalla scheda del socio e dall'elenco: motivazione sotto 15 caratteri → errore nella finestra, che resta aperta; con motivazione: ricevuta «Annullata» con il motivo, PDF con il timbro «ANNULLATA», movimento sparito dalla Prima Nota, socio di nuovo «da versare»; nell'Audit Log «Rinnovo: modifica» con rischio Alto. Nuova registrazione: numero successivo, l'annullato resta nell'elenco.
- K8 Domanda online accolta (blocco C) con quota versata. **Serve una domanda vera, non di prova**: con le iscrizioni chiuse una domanda mandata da collegati è «di prova» e non si iscrive (nel riquadro «Quota e iscrizione» lo dice un avviso, v0.37.1). Aprire le iscrizioni (Impostazioni › Ente), mandare la domanda da una finestra anonima, farla accogliere, poi nella scheda della domanda «Quota versata: iscrivi nel libro soci» con data e modalità; richiudere le iscrizioni alla fine. l'email «Iscrizione completata» ha la ricevuta in allegato e il numero nel riquadro; il rinnovo compare nella scheda del socio con lo stesso numero di serie.
- K9 Promemoria `/promemoria`: funzione «Quote non versate» con tre promemoria nell'ordine dell'anno sociale (15 dicembre, 1° febbraio, 1° aprile), prossimo invio. Apri «Sollecito: voto sospeso» (clic sul nome, sulla riga o sulla freccia a destra): destinatari al prossimo invio, anteprima con i segnaposti sostituiti (al genitore per il minore), «Invia una prova a me» (oggetto con «[Prova]»). Giorno 31 con febbraio: errore. Il Revisore vede «Sola lettura».
- K10 Invio senza aspettare la data: creare un promemoria di prova con giorno e mese di oggi e ora già passata (oggi), poi `php artisan promemoria:invia --prova` (dice quanti) e `php artisan promemoria:invia`: le email partono (al genitore per il minore); chi non ha email compare in «Da avvisare a mano»; un secondo lancio non manda nulla. Il promemoria di prova poi si spegne (già inviato: non si elimina).
- K12 (v0.37.1) Elimina scheda (Presidente, «Zona riservata»): senza motivazione o sotto 15 caratteri → errore nella finestra, che resta aperta; socio con una quota valida, movimenti o tessere → rifiutata con il motivo («usa Registra cessazione»); socio inserito per errore → eliminata, nell'Audit Log una riga «SOCIO_DELETED» con la motivazione nei valori e l'email al Presidente. Soci › Filtri › Stato «Schede eliminate» (solo Presidente): ricerca per codice fiscale, «Ripristina» riporta la scheda; con quel filtro niente CSV né PDF.
- K14 (v0.37.2) Su telefono il pulsante del menu (tre righe) è a destra, dopo «Esci»; il menu entra da destra.
- K13 (v0.37.1) Passando il mouse sui pulsanti con la sola icona (scarica, invia, annulla, apri, matita, cestino…) compare la descrizione.
- K11 Azzeramento prima dell'apertura (insieme a J12, prima dei saldi 2027):
```sql
DELETE FROM gl_promemoria_invii;
UPDATE gl_prima_nota SET rinnovo_id = NULL;
DELETE FROM gl_rinnovi;
UPDATE gl_soci SET quota_scadenza = NULL;
ALTER TABLE gl_rinnovi AUTO_INCREMENT = 1;
```
Poi `rm -rf storage/app/private/ricevute`. I promemoria di prova si eliminano dalla loro pagina dopo aver svuotato `gl_promemoria_invii`; i tre pronti restano (o si ricreano con `php artisan db:seed --class=PromemoriaSeeder --force` se la funzione non ne ha più).

## L. Tessere CSEN (v0.37.0)
Dettaglio in `docs/specifiche/tessere.md`, tavole TS-1…TS-5. Da Tesoriere (menu nuovo «Tesseramento»), poi da Presidente o Segretario (scheda del socio) e da Revisore. Prima: `php artisan db:seed --class=TesseraTipoSeeder --force` fatto (LEGGIMI), qualche socio di prova attivo, il file CSV esportato dal portale CSEN. **Dati di prova**: si azzerano con L12. Fino al 31/12/2026 la quota è «nel vecchio gestionale» (nessun blocco); per provare «Rinnova lo stesso» serve una quota di prova del 2026 mancante in un anno contabile aperto, quindi la regola si vede bene dal 2027 (anche nei test automatici).
- L1 Menu «Tesseramento» per Tesoriere, Presidente, Segretario, Revisore. Il Revisore vede «Sola lettura» e nessun pulsante (niente Nuovo pacchetto, Emetti, Rinnova, matita, cestino, spunta del portale).
- L2 Pagina `/tessere` a 1440 e 375 px (nessuno scorrimento orizzontale): sottotitolo, avviso giallo con i soci senza tessera valida («Mostra» porta all'elenco), cinque indicatori, «Da rinnovare» con «Mai tesserato» per chi non ha tessere, riquadro «Disponibili» con «Silver Base · 4,00 € a tessera», pacchetti, «Tessere emesse» con ricerca e filtro.
- L3 Carica tessere già emesse: scegli il CSV del CSEN, «Leggi il file». Anteprima: indicatori (da caricare, già nel gestionale, socio non trovato, da correggere), una riga per tessera con socio abbinato per codice fiscale, numero, tipo, rilascio, scadenza (un anno meno un giorno). Telefono ed email del file non compaiono. «Annulla» torna alla scelta del file.
- L4 «Carica N tessere»: messaggio con il numero e «Tipi nuovi senza prezzo: Gold Base»; nel riquadro Disponibili «Gold Base · prezzo da scrivere». Elenco: «dall'elenco CSEN», «✓ Inserita». Nell'Audit Log una sola riga «TESSERE_CARICATE». Ricaricare lo stesso file: tutte «Già nel gestionale», nessun pulsante «Carica». Sul server non resta il file (`ls storage/app/private/tmp/tessere`: vuota).
- L5 Tipi: matita su Gold Base, prezzo (es. 8,00), «In uso» spuntato → salvato. «Nuovo tipo» con un nome già presente: errore nella finestra, che resta aperta.
- L6 Nuovo pacchetto: tipo Silver Base, prezzo 4,00 proposto, dal `27B1024418` al `27B1024447` (quantità 30, totale 120,00 €). Con data 2026 e «Registra l'uscita» spuntata: errore «prima dell'avvio della contabilità»; tolta la spunta: registrato, «senza movimento», nell'elenco «vecchio gestionale». Un secondo pacchetto che riprende anche un solo numero: errore di sovrapposizione. «Al numero» con un prefisso diverso: errore.
- L7 Emetti dalla riga «Mai tesserato»: titolo «Emetti la tessera a …», tipo Silver Base, numero proposto `27B1024418` («Primo libero del pacchetto …»), emessa oggi, scade tra un anno meno un giorno, riquadro «Quota 2026 nel vecchio gestionale», pulsante «Emetti la tessera 27B1024418». Emessa: messaggio con le date e «Ricordati di inserirla sul portale CSEN»; il prossimo numero proposto è `27B1024419`.
- L7b (v0.37.2) «Emetti tessera» in alto, scegliendo un socio con la tessera valida ancora a lungo (es. Papini Andrea, fino al 08/01/2027): riquadro «Rinnovo non ancora possibile» con la data del rinnovo e da quando si registra; pulsante spento. Cambiando la data oltre i 60 giorni: messaggio che spiega la regola.
- L8 Controlli: numero già usato (anche scritto solo con le cifre) → errore nella finestra; numero fuori dai pacchetti → «registra prima il pacchetto»; prefisso diverso da quello del pacchetto → errore; solo cifre di un numero libero (es. `1024430`) → emessa come `27B1024430`. Seconda tessera allo stesso socio con data dentro la validità della prima → errore con la data da cui può partire.
- L9 Rinnovo. Le tessere del CSV scadono da gennaio 2027: per provarlo ora, avvicinare la scadenza di una tessera caricata (poi si azzera con L12):
```sql
UPDATE gl_tesseramenti SET data_rilascio = DATE_SUB(CURDATE(), INTERVAL 350 DAY), scadenza = DATE_ADD(CURDATE(), INTERVAL 14 DAY)
WHERE numero_tessera_csen = '26B2749167';
```
  Nella pagina Tessere la riga è «tra N giorni · data»; «Rinnova» propone il giorno dopo la scadenza e «Come la tessera precedente.». Dopo il rinnovo la vecchia tessera nella scheda è «Rinnovata» e il socio esce da «Da rinnovare».
- L10 Scheda del socio (Presidente o Segretario): badge «Tessera CSEN: …» nell'intestazione; riquadro «Tessera CSEN» con lo storico (numero, pacchetto o «dall'elenco CSEN», tipo, date, stato); pulsante «Emetti»/«Rinnova» solo se manca, è scaduta o scade entro 30 giorni: apre la pagina Tessere con la finestra già aperta e dopo l'emissione si torna alla scheda. Socio senza tessera: avviso rosso. Elenco soci: colonna «Tessera CSEN» con lo stesso badge.
- L11 Rinnovi quota: colonna «Tessera CSEN» e, sotto l'elenco, «Quota netta 2026: incassati − tessere CSEN». Registrare la quota di un socio senza tessera valida: dopo il salvataggio l'avviso «… non ha una tessera valida» con «Emetti la tessera». Pacchetti: matita → correzione; con tessere emesse serve la motivazione (Audit Log «TESSERE_PACCHETTO_CORRETTO»), l'intervallo che esclude un numero usato è rifiutato; senza tessere c'è «Elimina il pacchetto». Cestino su una tessera: motivazione sotto 15 caratteri → errore nella finestra; con motivazione la tessera sparisce e il numero torna proposto.
- L12 Azzeramento prima dell'apertura (con J12 e K11; le tessere vere si ricaricano dal CSV dopo l'azzeramento dei soci):
```sql
DELETE FROM gl_tesseramenti_qualifiche;
UPDATE gl_tesseramenti SET precedente_id = NULL;
DELETE FROM gl_tesseramenti;
DELETE FROM gl_tessere_pacchetti;
ALTER TABLE gl_tesseramenti AUTO_INCREMENT = 1;
ALTER TABLE gl_tessere_pacchetti AUTO_INCREMENT = 1;
```
I tipi restano (Silver Base, Gold Base con i loro prezzi).

## M. Scadenze dell'associazione e promemoria «N giorni prima» (v0.38.0, correzioni v0.38.1)

Riscritto per esteso il 12/10/2026, su richiesta di Paolo. Dettaglio tecnico in `docs/specifiche/scadenzario.md` e `promemoria.md`, tavole SC-1…SC-3 e PR-3.

**Che cosa si prova.** Lo Scadenzario (menu *Controllo › Scadenzario*) tiene le scadenze dell'associazione: quelle ricorrenti ogni anno (affiliazione CSEN, polizza RC, approvazione del rendiconto) e quelle una tantum. Quando una scadenza è stata pagata o fatta si preme «Rinnovata» (o «Fatta»): il gestionale registra il pagamento, se serve mette l'uscita in Prima Nota e sposta la scadenza all'anno dopo. I promemoria mandano un'email qualche giorno prima di ogni scadenza.

**Prima di cominciare (una volta sola).**
1. Sul server devono essere stati lanciati i seeder della v0.38.0: `ScadenzarioSeeder`, `PrimaNotaCategoriaSeeder`, `SezioneSeeder`, `PromemoriaSeeder`. Se nello Scadenzario non vedi le tre scadenze pronte, lanciali (`php artisan db:seed --class=… --force`).
2. Servono quattro utenti attivi, uno per ruolo: **Tesoriere**, **Presidente**, **Segretario**, **Revisore**. Senza un Tesoriere attivo i promemoria «Solo il Tesoriere» non partono (è stata la causa del problema di M11 del 12/10/2026).
3. Per vedere le uscite in Prima Nota serve l'anno contabile 2026 di prova (blocco J). Senza, fino al 31/12/2026 i pagamenti non vanno in Prima Nota (la contabilità vera parte dal 2027) e la spunta «Registra l'uscita» non è proposta: è normale.
4. Tutto quello che si fa in questo blocco è di prova e si cancella con M12.

### M1 — Chi vede lo Scadenzario e chi lo modifica
**Scopo:** controllare che ogni ruolo veda le voci di menu giuste e possa fare solo ciò che gli spetta.
**Passi:** entra con un ruolo alla volta e guarda la barra laterale, poi apri *Controllo › Scadenzario*.
**Risultato atteso:**
- **Tesoriere e Presidente:** nel menu c'è «Scadenzario» (gruppo *Controllo*) e «Impostazioni» (in fondo). Nello Scadenzario ci sono il pulsante «Nuova scadenza» in alto a destra e, su ogni riga, «Rinnovata» (o «Fatta») e la matita.
- **Segretario:** come sopra; in più, dalla v0.39.0, vede anche «Prima Nota» e «Scontrini» nel gruppo *Contabilità*.
- **Revisore:** vede «Scadenzario» ma con l'avviso azzurro «Sola lettura» e **senza** i pulsanti «Nuova scadenza», «Rinnovata», «Fatta». Nel menu non c'è «Impostazioni»; se scrivi a mano l'indirizzo `/impostazioni` compare l'errore 403 (accesso negato).

### M2 — La pagina dello Scadenzario, da computer e da telefono
**Scopo:** controllare che la pagina mostri le tre scadenze pronte con le informazioni giuste e che si legga bene anche sul telefono.
**Chi:** Tesoriere.
**Passi:**
1. Apri *Controllo › Scadenzario* dal computer (finestra larga).
2. Riapri la stessa pagina dal telefono (o restringi la finestra a circa 375 px).
**Risultato atteso:**
- Un riquadro «Ricorrenti ogni anno» con tre righe:
  - **Affiliazione CSEN** — data 31/12/2026, categoria «Affiliazione EPS»;
  - **Approvazione del rendiconto** — data 30/04/2027, categoria «Assemblea»;
  - **Polizza RC** — data 31/07/2027, categoria «Polizze Assicurative».
- Per ogni riga: lo stato in grigio «tra N giorni» (quanti giorni mancano), «Ultimo pagamento» con un trattino «—» (non è ancora stato registrato nulla), «Preavviso» «30 giorni prima», il pulsante «Rinnovata» (per il rendiconto «Fatta», perché non è un pagamento) e la matita.
- Sotto la tabella: una nota che spiega i colori degli stati e un link alla pagina «Promemoria».
- Dal telefono: niente scorrimento orizzontale (la pagina non va spostata col dito a destra e sinistra), i pulsanti si toccano bene.

### M3 — Scheda della Polizza RC e finestra «Rinnovata»
**Scopo:** controllare la scheda di una scadenza e l'aspetto della finestra «Rinnovata».
**Chi:** Tesoriere.
**Passi:**
1. Nello Scadenzario premi la **matita** sulla riga «Polizza RC»: si apre la scheda della scadenza.
2. Se sulla polizza vera il giorno di scadenza non è il 31/07, correggi il campo «Data» e premi «Salva».
3. Torna allo Scadenzario e premi «Rinnovata» sulla Polizza RC, solo per guardare la finestra (poi «Annulla»). Ripeti dal telefono.
**Risultato atteso:**
- Nella scheda: sotto il titolo il badge colorato dello stato; a destra il riquadro «Storico» con la scritta «Nessuna registrazione.»; **non** c'è il pulsante per eliminare la scadenza (la polizza è usata dal gestionale, quindi si modifica ma non si elimina; lo dice una nota sotto il modulo).
- Dopo «Salva», la nuova data compare nella scheda e nello Scadenzario.
- **v0.38.1:** nella finestra «Rinnovata» il riquadro grigio «Categoria» (che non si modifica) è alto esattamente come il campo «Prossima scadenza» accanto, sia dal computer sia dal telefono.

### M4 — Registrare il rinnovo della Polizza RC
**Scopo:** registrare un pagamento con «Rinnovata» e verificare che finisca in Prima Nota e che la scadenza passi all'anno dopo.
**Chi:** Tesoriere.
**Prima:** tieni a portata di mano un PDF qualunque da usare come quietanza.
**Passi:**
1. Nello Scadenzario premi «Rinnovata» sulla riga «Polizza RC».
2. Guarda la finestra «Polizza RC rinnovata» senza toccare nulla.
3. Prova a scrivere in «Pagata il» una data futura (es. domani).
4. Rimetti la data di oggi. Lascia spuntata «Registra l'uscita in Prima Nota» e prova a premere «Registra» con l'importo vuoto.
5. Scrivi un importo (es. `250,00`), allega il PDF come giustificativo e premi «Registra».
6. Apri la Prima Nota e cerca l'uscita appena creata; poi torna allo Scadenzario.
**Risultato atteso:**
- Al passo 2 la finestra propone: data di oggi; importo vuoto con sotto «Primo pagamento registrato qui.»; conto «Banca»; categoria «Polizze Assicurative» (fissa); prossima scadenza un anno dopo quella di adesso, con sotto «Un anno dopo; si può cambiare.».
- Al passo 3 il campo non accetta una data futura.
- Al passo 4 il gestionale chiede l'importo (con la spunta l'importo è obbligatorio).
- Al passo 5 compare il messaggio verde «… rinnovata: prossima scadenza il …, uscita n. … in Prima Nota».
- In Prima Nota c'è un'uscita «Polizza RC, scadenza del …», soggetto «Unipol, tramite CSEN», con il giustificativo dal nome `PN2026-<numero>-01.pdf`.
- Nello Scadenzario la riga della polizza ha la data spostata di un anno e, in «Ultimo pagamento», «Pagata il … · 250,00 €» con il link all'uscita.
- **Se la spunta «Registra l'uscita» non c'è o è spenta** (manca l'anno contabile 2026 di prova): toglierla è l'unica possibilità; il pagamento si registra lo stesso e l'importo resta nello storico della scadenza, senza uscita.

### M5 — Errori nella finestra «Rinnovata»
**Scopo:** controllare che i casi sbagliati vengano fermati e che dopo un errore non si perda quanto scritto.
**Chi:** Tesoriere.
**Passi e risultati attesi:**
1. **Doppio rinnovo.** Apri lo Scadenzario in due schede del browser. In tutte e due premi «Rinnovata» sulla stessa scadenza. Registra nella prima scheda, poi prova a registrare anche nella seconda. → Nella seconda compare «già rinnovata (ora scade il …): ricarica la pagina» e la data **non** si sposta di due anni.
2. **Prossima scadenza uguale a quella di adesso.** Nella finestra metti in «Prossima scadenza» la stessa data della scadenza attuale e premi «Registra». → Errore nella finestra.
3. **I dati restano dopo un errore.** Scrivi l'importo `12,50`, allega un file e provoca un errore (es. quello del punto 2). → La finestra si riapre da sola con l'importo scritto all'italiana («12,50», non «12.50») e il file indicato come «già caricato».

### M6 — Annullare l'ultima registrazione della Polizza RC
**Scopo:** correggere un rinnovo registrato per errore.
**Chi:** Tesoriere.
**Prima:** la Polizza RC deve avere il rinnovo registrato in M4.
**Passi:**
1. Apri la scheda della Polizza RC (matita).
2. Guarda il riquadro «Storico».
3. Premi «Annulla l'ultima registrazione», scrivi una motivazione corta (meno di 15 caratteri, es. `errore`) e conferma con «Annulla la registrazione».
4. Scrivi una motivazione vera (es. `Registrata sulla scadenza sbagliata`) e conferma.
5. Controlla la Prima Nota e l'Audit Log.
6. Rifai M4, così la polizza ha di nuovo un rinnovo per le prove seguenti.
**Risultato atteso:**
- Al passo 2 lo storico mostra «Pagata il …», «scadenza del … → …» (dalla data vecchia alla nuova), chi l'ha registrata e il link «uscita n. …».
- Al passo 3 la finestra resta aperta con l'errore sulla motivazione.
- Al passo 4: messaggio «Ultima registrazione annullata: «Polizza RC» torna a scadere il …»; la scadenza torna alla data di prima.
- In Prima Nota l'uscita e il suo giustificativo non ci sono più.
- Nell'Audit Log una riga «SCADENZA_RINNOVO_ANNULLATO»; aprendola, la motivazione è nei valori «Dopo» e **non** nel testo del dettaglio.
- **v0.38.1:** in tutta la scheda e nella finestra la dicitura è «Annulla l'ultima registrazione» (non più «rinnovo»).

### M7 — «Fatta» sull'Approvazione del rendiconto
**Scopo:** registrare un adempimento (una scadenza senza pagamento) e poi annullarlo.
**Chi:** Tesoriere o Presidente.
**Passi:**
1. Nello Scadenzario premi «Fatta» sulla riga «Approvazione del rendiconto».
2. Premi «Registra».
3. Apri la scheda (matita) e annulla l'ultima registrazione come in M6.
**Risultato atteso:**
- La finestra ha solo la data, la prossima scadenza e le note: niente importo, conto o uscita in Prima Nota.
- Dopo «Registra» la scadenza passa al 30/04/2028.
- Dopo l'annullamento torna al 30/04/2027.

### M8 — Scadenza una tantum creata dal Segretario
**Scopo:** creare una scadenza che non si ripete, segnarla come fatta, ed eliminarne una.
**Chi:** Segretario.
**Passi:**
1. Nello Scadenzario premi «Nuova scadenza».
2. Compila: Titolo `Prova una tantum`; Ripetizione «una tantum»; Data fra 10 giorni; Preavviso (giorni) `30`; nessuna «Pagamento: categoria dell'uscita». Premi «Aggiungi la scadenza».
3. Guarda lo Scadenzario e la Dashboard.
4. Premi «Fatta» sulla nuova scadenza e registra.
5. Apri la scheda della scadenza.
6. Crea un'altra scadenza una tantum qualsiasi e, senza segnarla fatta, aprila e premi «Elimina la scadenza».
7. Infine apri «Rinnovata» su una scadenza con pagamento (es. Polizza RC), solo per guardare.
**Risultato atteso:**
- Al passo 3 la scadenza compare in un riquadro «Una tantum», con lo stato giallo «in scadenza» (mancano 10 giorni, meno del preavviso di 30), e compare anche nella Dashboard.
- Dopo «Fatta» lo stato diventa «Fatta il …» e il pulsante «Fatta» sparisce.
- Nella scheda **non** c'è «Elimina la scadenza» (ha una registrazione), mentre c'è «Annulla l'ultima registrazione»: serve a riaprire la scadenza se «Fatta» è stato premuto per errore (v0.38.1: dicitura generica, perché una una tantum non è un rinnovo).
- Al passo 6 la scadenza senza registrazioni si elimina dopo la conferma «Eliminare la scadenza?».
- Al passo 7 la spunta «Registra l'uscita in Prima Nota» è spenta, con sotto la spiegazione che l'uscita la registra il Tesoriere: il Segretario può segnare il rinnovo, ma senza uscita.

### M9 — Riquadri Affiliazione e Polizza in Impostazioni › Ente
**Scopo:** controllare i due riquadri che riassumono affiliazione e polizza, e chi li può modificare.
**Passi:**
1. Entra come **Tesoriere** e apri *Impostazioni › Ente*.
2. Entra come **Presidente**, stessa pagina; premi «Modifica» sul riquadro «Affiliazione all'EPS», poi su «Polizza RC».
3. Riapri la pagina dal telefono.
**Risultato atteso:**
- **Tesoriere:** avviso «Sola lettura»; due riquadri affiancati, «Affiliazione all'EPS» e «Polizza RC». In ciascuno: «Stato» con il badge verde **«Valida»** (v0.38.1: solo lo stato, senza «tra N giorni»; la data è nella riga «Scadenza»), la scadenza, l'ultimo rinnovo (se c'è, «movimento PN» porta all'uscita in Prima Nota) e il pulsante «Vai alla scadenza». Nessun pulsante «Modifica».
- **Presidente:** su ciascun riquadro c'è «Modifica». La finestra ha in alto l'avviso «La scadenza non si scrive qui.» (la data si cambia solo dallo Scadenzario). Scrivi codice di affiliazione, comitato/referente e contatto (affiliazione) e numero di polizza (polizza), salva: i dati compaiono nei riquadri. Nell'Audit Log una riga «PROFILO_ENTE_MODIFICATO».
- **Telefono:** dentro ogni riquadro i dati restano su due colonne; i pulsanti sono a tutta larghezza.

### M10 — Affiliazione scaduta o in scadenza
**Scopo:** controllare gli avvisi quando l'affiliazione CSEN è scaduta o sta per scadere. L'avviso **non** blocca le tessere (decisione di Paolo dell'11/10/2026).
**Chi:** Tesoriere (e uno sguardo da Presidente).
**Passi:**
1. In phpMyAdmin rendi l'affiliazione scaduta da 3 giorni:
   ```sql
   UPDATE gl_scadenzario SET data_scadenza = DATE_SUB(CURDATE(), INTERVAL 3 DAY) WHERE chiave = 'affiliazione_eps';
   ```
2. Guarda: Dashboard, Scadenzario, *Impostazioni › Ente*, pagina Tessere.
3. Portala a 10 giorni da oggi:
   ```sql
   UPDATE gl_scadenzario SET data_scadenza = DATE_ADD(CURDATE(), INTERVAL 10 DAY) WHERE chiave = 'affiliazione_eps';
   ```
4. Guarda di nuovo Dashboard, Scadenzario e *Impostazioni › Ente*.
5. Rimetti la data vera:
   ```sql
   UPDATE gl_scadenzario SET data_scadenza = '2026-12-31' WHERE chiave = 'affiliazione_eps';
   ```
**Risultato atteso:**
- Al passo 2: avviso **rosso** «Affiliazione CSEN scaduta il …» nella Dashboard (con il pulsante «Vai alla scadenza»), nello Scadenzario (con «Rinnovata»), in *Impostazioni › Ente* (badge «Scaduta») e nella pagina Tessere. Le tessere si possono comunque emettere.
- Al passo 4: avviso **giallo** «Affiliazione CSEN: tra 10 giorni» in Dashboard e Scadenzario; nel riquadro di *Impostazioni › Ente* il badge **«In scadenza»** (v0.38.1).
- Al passo 5 tutto torna normale (nessun avviso).

### M11 — Promemoria delle scadenze e delle tessere
**Scopo:** controllare la pagina dei promemoria, l'email di prova e l'invio vero.
**Chi:** Tesoriere (o Presidente).
**Prima:** un utente **Tesoriere attivo** (vedi «Prima di cominciare»).
**Parte 1 — la pagina.**
1. Apri *Governance › Promemoria*.
   - Atteso: due funzioni nuove. «Scadenze dell'associazione» con due promemoria («Scadenza in arrivo», 30 giorni prima; «Ultimi giorni», 7 giorni prima), destinatari Presidente e Tesoriere, e il prossimo invio con la scadenza più vicina (es. «01/12/2026 09:00 · Affiliazione CSEN»). «Tessere in scadenza» con «Tessere da rinnovare», 15 giorni prima, «Solo il Tesoriere».
2. Apri «Scadenza in arrivo».
   - Atteso: «Data fissa ogni anno» spenta (questa funzione lavora solo «prima o dopo una data»); Giorni `30`, «Prima della data»; a destra il riquadro «Destinatari» e l'anteprima dell'email con i valori al posto dei segnaposti.
3. Premi «Invia una prova a me».
   - Atteso: arriva un'email con oggetto che comincia con «[Prova]» e i dati veri della prima scadenza.
4. **v0.38.1** — apri «Tessere da rinnovare» quando nessuna tessera scade fra 15 giorni.
   - Atteso: riquadro «Anteprima con dati d'esempio» con nomi inventati (Rossi Mario, Verdi Anna) e una nota che lo spiega. «Invia una prova a me» manda un'email con oggetto «[Prova, dati d'esempio] …» e testo leggibile: **mai** `[saluto]`, `[data]`, `[elenco]`.
   - Se nessun utente attivo ha il ruolo scelto in «Destinatari», compare l'avviso giallo «Nessun utente attivo con il ruolo scelto.».

**Parte 2 — invio vero della scadenza, senza aspettare.** Da fare dopo le 9:00.
1. Porta la Polizza RC a 30 giorni da oggi:
   ```sql
   UPDATE gl_scadenzario SET data_scadenza = DATE_ADD(CURDATE(), INTERVAL 30 DAY) WHERE chiave = 'polizza_rc';
   ```
2. Nel Terminale di cPanel, dentro `~/gestionale-asd`: `php artisan promemoria:invia`
3. Lancia lo stesso comando una seconda volta.
4. Riapri «Scadenza in arrivo» e guarda lo «Storico».
- Atteso: al passo 2 il comando scrive quante email ha inviato e arrivano due email, una al Presidente e una al Tesoriere. Al passo 3 non parte nulla e il comando scrive «Nessun promemoria da inviare adesso…» (v0.38.1: prima non scriveva niente). Nello storico la riga «scadenza del …».

**Parte 3 — invio vero delle tessere.**
1. Scegli il numero di una tessera caricata dal CSV nel blocco L, di un socio attivo, e portala a 15 giorni da oggi:
   ```sql
   UPDATE gl_tesseramenti SET scadenza = DATE_ADD(CURDATE(), INTERVAL 15 DAY) WHERE numero_tessera_csen = 'NUMERO';
   ```
2. Lancia `php artisan promemoria:invia`.
- Atteso: un'email al Tesoriere con l'elenco delle tessere in scadenza e, per ciascun socio, la situazione della quota.
- **Se non arriva nulla:** lancia `php artisan promemoria:invia --prova`. Per ogni promemoria dice quanti avviserebbe adesso, quanti destinatari ci sono in tutto e quando parte il prossimo invio, oppure il motivo: «Nessun utente attivo con il ruolo scelto» o «Nessun destinatario per nessuna data». La tessera deve essere di un socio con stato «attivo», avere la data di rilascio ed essere l'ultima tessera di quel socio.

**Alla fine:** rimetti la data vera della Polizza RC (con il giorno giusto) e quella della tessera modificata.

### M12 — Azzeramento prima dell'apertura
**Quando:** solo alla fine del collaudo, prima dell'apertura del 2027, insieme a J12, K11, L12 e N12. I rinnovi annullati in M6 non lasciano nulla.
**Passi:**
1. In phpMyAdmin:
   ```sql
   DELETE FROM gl_scadenzario_rinnovi;
   DELETE FROM gl_scadenzario WHERE chiave IS NULL;
   ALTER TABLE gl_scadenzario_rinnovi AUTO_INCREMENT = 1;
   ```
   (La seconda riga cancella le scadenze create a mano nel collaudo; le tre pronte hanno una chiave e restano.)
2. Nello Scadenzario controlla le date delle tre scadenze pronte: affiliazione 31/12, polizza con il giorno vero, rendiconto 30/04.
**Attenzione:** non registrare con «Rinnovata» i pagamenti già fatti per le scadenze in corso, perché «Rinnovata» sposta la data all'anno dopo. Il primo «Rinnovata» vero sarà, per esempio, l'affiliazione 2027 a dicembre: l'importo scritto quella volta verrà poi proposto negli anni seguenti.

## N. Scontrini dal telefono con OCR (v0.39.0)

Riscritto per esteso il 12/10/2026, su richiesta di Paolo. Dettaglio tecnico in `docs/specifiche/scontrini.md`, tavole SN-1…SN-8.

**Che cosa si prova.** I membri del Consiglio Direttivo fotografano uno scontrino dal telefono e scrivono che cosa hanno comprato. Il gestionale manda la foto al servizio OCR.space, che ne «legge» il testo, e propone totale, data, fornitore e righe. Chi carica controlla, corregge e registra: lo scontrino entra in Prima Nota come un'uscita in «Uscite da classificare». Poi il Tesoriere (o il Presidente) lo verifica dalla scheda: assegna le categorie e, se le righe sono di categorie diverse, lo divide in più movimenti. Chi ha caricato lo scontrino non può più modificarlo.

**Prima di cominciare (una volta sola).**
1. Installazione della v0.39.0 completa (LEGGIMI): migration, `RuoloSeeder`, `SezioneSeeder`; nel `.env` del server `OCR_SPACE_KEY=` con la chiave di `gestionale-acv`, poi `php artisan config:cache`; in cPanel estensioni PHP **gd** ed **exif** attive.
2. In locale con Avast: eccezione per `https://api.ocr.space/*`.
3. Un utente **Consigliere** di prova (da tinker, `'ruolo' => 'consigliere'`), più gli utenti Tesoriere, Segretario e Revisore.
4. L'anno contabile 2026 di prova (blocco J): la contabilità vera parte dal 2027, quindi senza l'anno di prova gli scontrini del 2026 non si registrano.
5. Alcuni scontrini veri a portata di mano: uno di un negozio mai usato, uno di ferramenta con articoli diversi, uno di un supermercato con molte righe (es. Eurospin).
6. Le prove con la fotocamera vanno fatte dal **telefono vero**; le altre anche dal computer. Tutto si cancella con N12.

### N1 — Chi vede gli scontrini
**Scopo:** controllare menu e permessi dei ruoli coinvolti.
**Passi:** entra con un ruolo alla volta.
**Risultato atteso:**
- **Consigliere:** nel menu solo Dashboard e, sotto *Contabilità*, «Prima Nota» e «Scontrini». In Prima Nota un avviso «Il tuo ruolo (Consigliere) vede la Prima Nota e carica gli scontrini…»; non ci sono «Nuovo movimento» né «Giroconto», c'è «Nuovo scontrino»; nessun invito a scrivere i saldi di apertura.
- **Segretario:** come il Consigliere, più le sue sezioni di sempre.
- **Revisore:** vede «Scontrini» in sola lettura, senza «Nuovo scontrino».

### N2 — Passo 1: la foto dal telefono
**Scopo:** controllare la scelta della foto e della nota.
**Chi:** Consigliere, dal telefono.
**Passi:**
1. Apri «Scontrini» e premi «Nuovo scontrino».
2. Premi «Fotografa lo scontrino» e scatta.
3. Prova a premere «Leggi lo scontrino» senza scrivere la nota.
4. Prova invece con un file: un PDF o una foto dalla galleria.
5. (Solo iPhone) Prova con una foto in formato HEIC.
**Risultato atteso:**
- Al passo 2 si apre la fotocamera posteriore; dopo lo scatto la foto compare nel riquadro con nome e peso del file.
- «Leggi lo scontrino» resta spento finché mancano foto o nota; con la nota vuota compare l'errore «Scrivi che cosa hai comprato».
- Al passo 4 un PDF o una foto della galleria vanno bene.
- Al passo 5 compare un messaggio che spiega come passare al formato compatibile: *Impostazioni › Fotocamera › Formati › Più compatibile*.

### N3 — Lettura della foto e passo 2
**Scopo:** controllare che i dati letti siano proposti correttamente e si possano correggere.
**Chi:** Consigliere, dal telefono.
**Prima:** lo scontrino di un negozio mai registrato.
**Passi:**
1. Foto, nota (es. `Colla per le frecce`), «Leggi lo scontrino».
2. Guarda la pagina del passo 2.
3. Nel riquadro «Fornitore» scegli «+ Nuovo fornitore» e correggi i dati letti (es. «IL FORVAIO» → «Il Fornaio»).
4. Cambia una cifra della partita IVA e prova a registrare; poi rimettila giusta.
**Risultato atteso:**
- Per 2–5 secondi compare «Sto leggendo lo scontrino…».
- Poi il passo 2 con l'avviso «Letti dalla foto: controllali.»: totale e data letti (sotto il totale la riga da cui è stato preso, es. «Dalla riga «TOTALE COMPLESSIVO»»); «Pagato con» proposto (contanti → Cassa, carta → Banca); «Righe lette: N» con l'elenco delle righe.
- Fornitore: «Nessun fornitore simile»; con «+ Nuovo fornitore» la ragione sociale, la partita IVA, l'indirizzo e il comune sono già compilati con quanto letto, da correggere se serve.
- Con una cifra sbagliata nella partita IVA: errore «la cifra di controllo è sbagliata».

### N4 — Registrare lo scontrino
**Scopo:** controllare cosa succede dopo «Registra», per chi carica e in Prima Nota.
**Chi:** Consigliere; poi Tesoriere per la Prima Nota.
**Passi:**
1. Dal passo 2 di N3 premi «Registra».
2. Apri lo scontrino appena registrato.
3. Come Tesoriere apri la Prima Nota e il movimento dello scontrino.
**Risultato atteso:**
- Si arriva a «I miei scontrini» con il messaggio «Scontrino n. 1 registrato · 14,50 € · … · banca. È in Prima Nota su «Uscite da classificare»: lo verifica il Tesoriere» (importo e conto quelli dello scontrino); lo scontrino ha lo stato «Da verificare».
- La scheda è in sola lettura, con la nota «Verificano, dividono e correggono gli scontrini il Presidente e il Tesoriere.».
- In Prima Nota: il movimento ha l'icona dello scontrino, la categoria «Da classificare» e il documento «Scontrino SC2026-001». Nella scheda del movimento un avviso con il link alla scheda dello scontrino, e tra i giustificativi la foto `SC2026-001.jpg`.

### N5 — Fornitore già conosciuto
**Scopo:** controllare che il gestionale riconosca un fornitore già in elenco.
**Chi:** Consigliere.
**Passi e risultati attesi:**
1. Carica un secondo scontrino **dello stesso negozio** di N3. → Nel riquadro Fornitore: «È la partita IVA di un fornitore in elenco», fornitore già scelto con «· già in elenco».
2. Carica lo scontrino di un fornitore che esiste già in elenco **senza partita IVA** e con lo stesso nome (es. creato a mano dalla Prima Nota). → «C'è già un fornitore con questo nome» e «Le aggiungo la partita IVA letta»; dopo «Registra», nella pagina Fornitori quel fornitore ha la partita IVA.
3. In un nuovo scontrino premi «Scegli dalla lista dei fornitori». → Si apre una finestra con la ricerca (per nome o partita IVA); toccando un fornitore diventa la scelta, con «· scelto dalla lista».

### N6 — Foto non letta e controlli sulla data
**Scopo:** controllare che si possa registrare anche quando la lettura non riesce, e che le date sbagliate siano fermate.
**Chi:** Consigliere (per il punto 1 serve accesso al `.env`).
**Passi e risultati attesi:**
1. Svuota temporaneamente `OCR_SPACE_KEY` nel `.env` (poi `php artisan config:cache`), oppure stacca la rete, e leggi uno scontrino. → Avviso rosso «Foto non letta.», campi vuoti: si registra scrivendo tutto a mano. **Ricordati di rimettere la chiave** e rifare `config:cache`.
2. Scrivi una data futura. → Errore.
3. Scrivi una data del 2025. → Errore «prima dell'avvio della contabilità…».
4. Premi «Annulla» oppure «Rifai la foto». → Si torna al passo 1 e non viene registrato nulla.

### N7 — Elenco degli scontrini (Tesoriere)
**Scopo:** controllare la pagina d'elenco, da computer e da telefono.
**Chi:** Tesoriere.
**Passi:** apri *Contabilità › Scontrini* dal computer, poi dal telefono; prova i riquadri e i filtri.
**Risultato atteso:**
- In alto quattro riquadri: «Da verificare», «Da quadrare», «Verificati», «Spesa 2026»; cliccandoli l'elenco si filtra.
- Un avviso «Anno 2026: N scontrini da sistemare prima della chiusura».
- Filtri per stato, per chi ha caricato («Caricato da») e per testo; le miniature delle foto.
- In fondo: «Lettura automatica delle foto: attiva; ultima lettura il …».
- Dal telefono nessuno scorrimento orizzontale.

### N8 — Verificare e dividere uno scontrino di ferramenta
**Scopo:** dividere uno scontrino con articoli di categorie diverse in più movimenti di Prima Nota.
**Chi:** Tesoriere.
**Prima:** uno scontrino di ferramenta registrato (N4) con almeno tre articoli diversi.
**Passi:**
1. Apri la scheda dello scontrino dall'elenco.
2. Guarda la foto (si ingrandisce), «Testo letto dalla foto», le righe e la barra di quadratura.
3. Con «+ Aggiungi una riga» prepara tre righe con importi e categorie diverse, e una riga di sconto con importo negativo (`-1,00`).
4. Lascia le righe che non tornano col totale, poi premi «Aggiungi la differenza».
5. Leggi il riquadro «Divisione: un movimento per categoria» e premi «Registra la divisione».
6. Controlla la Prima Nota e l'Audit Log.
**Risultato atteso:**
- Ogni riga ha «Scegli…» come categoria finché non la scegli; la barra dice «Torna: righe … su …» quando la somma delle righe è uguale al totale.
- Finché le righe non tornano al centesimo la barra è rossa («Non torna: righe …»), e «Registra la divisione» resta spento finché le righe non tornano **e** non hanno tutte la categoria.
- «Aggiungi la differenza» aggiunge la riga che manca per far tornare il totale.
- Il piano mostra un movimento per categoria: «n. …» per quello già esistente che viene riusato, «nuovo» per gli altri, e la frase «Sostituisce il movimento n. … «Uscite da classificare»».
- Dopo la registrazione: messaggio «Divisione registrata: 3 movimenti in Prima Nota», stato «Verificato»; in Prima Nota tre movimenti con le descrizioni delle righe; nell'Audit Log una riga «SCONTRINO_DIVISO».

### N9 — Supermercato con righe che non tornano
**Scopo:** gestire uno scontrino lungo dove le righe lette non coincidono col totale.
**Chi:** Tesoriere.
**Prima:** uno scontrino con molte righe (es. Eurospin) registrato dal Consigliere.
**Passi:**
1. Apri la scheda.
2. Premi «Riprendi le N righe lette».
3. Seleziona due righe e premi «Unisci le righe selezionate».
4. Usa «Tutte nella stessa categoria…» e registra la divisione.
**Risultato atteso:**
- Al passo 1 c'è una sola riga col totale (le righe lette non tornavano) e il pulsante «Riprendi le N righe lette».
- Al passo 3 le due righe diventano una sola con la somma degli importi.
- Al passo 4, con tutte le righe in una categoria: «1 movimento in Prima Nota, 2 tolti».

### N10 — Correggere i dati dopo la verifica
**Scopo:** controllare cosa succede cambiando i dati di uno scontrino già verificato, e i blocchi dalla Prima Nota.
**Chi:** Tesoriere.
**Passi e risultati attesi:**
1. Su uno scontrino con **un solo** movimento cambia il totale e premi «Salva i dati». → Il nuovo totale passa anche al movimento in Prima Nota.
2. Sullo scontrino diviso in N8 cambia il totale e salva. → Messaggio «Il totale non torna più con i movimenti: rifai la divisione», stato «Da quadrare».
3. Apri *Prima Nota › Anni e saldi*. → Nella lista di controllo della chiusura la voce «Nessuno scontrino da quadrare» **non** è spuntata.
4. Rifai la divisione dello scontrino. → Stato di nuovo «Verificato».
5. Prova a mettere una data di un altro anno. → Non è accettata: la data resta nell'anno dello scontrino.
6. Dalla Prima Nota apri un movimento nato da uno scontrino e premi «Modifica». → Importo, data e conto sono spenti, con un avviso che rimanda alla scheda dello scontrino. Cambia la categoria e salva: nella scheda dello scontrino cambia la categoria della riga (e lo stato, se serve).

### N11 — Foto aggiuntive, eliminazione e Fornitori
**Scopo:** provare le altre azioni della scheda e la pagina Fornitori.
**Chi:** Tesoriere; alla fine il Consigliere.
**Passi e risultati attesi:**
1. Nella scheda di uno scontrino premi «Aggiungi una foto», scegli un PDF e premi «Carica». → Compare nell'elenco dei file come `SC2026-00N-02.pdf`.
2. Premi «Elimina lo scontrino» e conferma nella finestra «Eliminare lo scontrino?». → Spariscono lo scontrino, i suoi movimenti in Prima Nota e le foto; nell'Audit Log «SCONTRINO_ELIMINATO» con rischio Alto e l'elenco dei movimenti tolti.
3. Apri la pagina Fornitori (dal pulsante «Fornitori» in Scontrini) e crea a mano due fornitori: `FERRAMENTA X S.N.C.` e `Ferramenta X snc`. → Compaiono in «Possibili doppioni».
4. Premi «Unisci» e scegli quale dei due resta. → I movimenti dell'altro passano a quello che resta; nell'Audit Log «FORNITORI_UNITI».
5. Con la matita cambia il nome di un fornitore usato. → Il nome cambia anche nei suoi movimenti.
6. Togli la spunta «In elenco» a un fornitore. → Non compare più tra le scelte del passo 2 degli scontrini né nel campo Fornitore della Prima Nota.
7. Prova a eliminare un fornitore usato e uno mai usato. → Si può eliminare solo quello mai usato.
8. Entra come Consigliere e apri Fornitori. → La pagina si vede, ma senza pulsanti.

### N12 — Azzeramento prima dell'apertura
**Quando:** solo alla fine del collaudo, insieme a J12, K11, L12 e M12.
**Passi:** per ogni scontrino di prova «Elimina lo scontrino» dalla scheda, oppure in phpMyAdmin:
```sql
DELETE FROM gl_prima_nota WHERE scontrino_id IS NOT NULL;
DELETE FROM gl_prima_nota_scontrini;
ALTER TABLE gl_prima_nota_scontrini AUTO_INCREMENT = 1;
ALTER TABLE gl_prima_nota_scontrini_righe AUTO_INCREMENT = 1;
ALTER TABLE gl_prima_nota_scontrini_file AUTO_INCREMENT = 1;
```
Poi cancella la cartella `storage/app/private/scontrini`. I fornitori di prova si eliminano dalla pagina Fornitori (quelli veri restano).

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
