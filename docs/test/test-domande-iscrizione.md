# Test domande di iscrizione e voto CD (collaudo v0.30.3, aggiornata alla v0.37.0)

Lista di collaudo manuale su arco.valconi.it. Collaudo della v0.30.3
concluso il 30/09/2026: blocchi A–F superati, pulizia fatta. Collaudo della
v0.32.0 (blocco G, A11–A13) del 05/10/2026: superato, con la sola richiesta
sulla disposizione dei dati fissi (G3), fatta nella v0.32.1 (installata).
Dalla v0.33.0 blocco H (link personale per l'informativa), dalla v0.34.0 blocco I (Audit Log), dalla v0.35.0 blocco J (Prima Nota), dalla v0.36.0 blocco K (Rinnovi quota e Promemoria: superato il 07/10/2026), dalla v0.37.0 blocco L (Tessere CSEN). Copia viva,
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
- K1 Menu: «Rinnovi quota» (Presidente, Tesoriere, Revisore) e «Promemoria» (anche il Segretario, che la vede vuota). Il Revisore non vede «Registra quota» né i pulsanti di invio e annullamento.
- K2 Elenco `/rinnovi` a 1440 e 375 px: anno sociale 2026; avviso rosso «Da dichiarare decaduti» per chi non ha versato (siamo dopo il 30 aprile); indicatori; filtri situazione, categoria, cerca; CSV. Anno 2027: tutti «Da versare entro il 31/01». Clic sulla riga: scheda del socio, riquadro «Quote associative».
- K3 Registra quota per il socio adulto: scelto il socio la pagina si ricarica con anno, importo 70,00, numero della prossima ricevuta (es. 1/2026); bonifico, «Invia la ricevuta» spuntato. Salvato: messaggio con il numero e «inviata a …»; email con il PDF in allegato; in Prima Nota un'entrata «Quota associativa 2026 – …» in Banca, categoria «Quote associative», documento «ricevuta 1/2026». Una seconda quota 2026 dello stesso socio è rifiutata. Data 15/12/2025 (prima del primo anno contabile): ricevuta sì, movimento no, con la nota «movimento nel vecchio gestionale».
- K4 Registra per il minorenne: casella dell'assenso obbligatoria; «Versata da» e codice fiscale del genitore precompilati e obbligatori; avviso «Detrazione per lo sport dei ragazzi: non disponibile» (B.A.S. non iscritta al RASD); email al genitore. Importo cambiato a 25,00 senza note: errore; con una nota: accettato.
- K5 Ricevuta PDF: carta intestata B.A.S., «Quota associativa — anno sociale 2026», n. e data, sede legale e C.F. dell'ente, socio con C.F., per il giovane data e luogo di nascita, «Tiro con l'arco», versata da con C.F., importo in cifre e in lettere (30,00 → «trenta/00»), modalità, validità, dicitura IVA, «Il Tesoriere» con il nome se il CD di prova ha un Tesoriere in carica. Nessun riquadro della detrazione.
- K6 Contanti: il movimento va in Cassa. Socio senza email: «Invia» spento, il messaggio dice di consegnarla.
- K7 Annullamento dalla scheda del socio e dall'elenco: motivazione sotto 15 caratteri → errore nella finestra, che resta aperta; con motivazione: ricevuta «Annullata» con il motivo, PDF con il timbro «ANNULLATA», movimento sparito dalla Prima Nota, socio di nuovo «da versare»; nell'Audit Log «Rinnovo: modifica» con rischio Alto. Nuova registrazione: numero successivo, l'annullato resta nell'elenco.
- K8 Domanda online accolta (blocco C) con quota versata: l'email «Iscrizione completata» ha la ricevuta in allegato e il numero nel riquadro; il rinnovo compare nella scheda del socio con lo stesso numero di serie.
- K9 Promemoria `/promemoria`: funzione «Quote non versate» con tre promemoria (15 dicembre, 1° febbraio, 1° aprile), prossimo invio. Apri «Sollecito: voto sospeso»: destinatari al prossimo invio, anteprima con i segnaposti sostituiti (al genitore per il minore), «Invia una prova a me» (oggetto con «[Prova]»). Giorno 31 con febbraio: errore. Il Revisore vede «Sola lettura».
- K10 Invio senza aspettare la data: creare un promemoria di prova con giorno e mese di oggi e ora già passata (oggi), poi `php artisan promemoria:invia --prova` (dice quanti) e `php artisan promemoria:invia`: le email partono (al genitore per il minore); chi non ha email compare in «Da avvisare a mano»; un secondo lancio non manda nulla. Il promemoria di prova poi si spegne (già inviato: non si elimina).
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
