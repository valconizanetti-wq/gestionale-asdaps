# Collaudo O — Più ruoli per utente, pagina Utenti e importazione dei soci

Blocco della lista di collaudo del Gestionale ASD, in un file a sé (un documento per blocco). Regole comuni (preparazione, reset, CD di prova, cosa portare quando una prova fallisce) in `test-domande-iscrizione.md`, nella stessa cartella. Copia nel progetto: `claude/collaudo-O-utenti-importazione.md`.

Versione: **v0.40.0** (13/10/2026). Dettaglio tecnico: `docs/analisi-ruoli-utenti.md`, `docs/analisi-importazione-soci.md` (§7 e §8 in entrambe: decisioni, realizzazione, revisione). Tavole UT-1…UT-4 e IS-1…IS-3 in `Tavole-utenti-importazione.pdf` (fuori dal repository).

**Che cosa si prova.**
- **Ruoli:** un utente può avere più ruoli. Vede l'unione delle sezioni, e dove un ruolo scrive e l'altro legge soltanto, scrive.
- **Pagina Utenti** (menu in fondo, sotto Impostazioni): Presidente e Segretario la modificano; Tesoriere e Consigliere la consultano; il Revisore non la vede.
  - Il Segretario non dà né toglie il ruolo di Presidente, non modifica l'utente del Presidente e non cambia i propri ruoli.
  - La password non la sceglie più chi crea l'utente: arriva un **link per email** (24 ore, una volta sola). Sulla pagina d'accesso c'è «Password dimenticata?».
- **Avvisi:** le operazioni delicate (ruoli, importazione, schede eliminate, ricevute annullate, anni riaperti…) arrivano per email agli altri fra Presidente, Segretario e Tesoriere che vedono quella parte del gestionale. Non arrivano a chi le ha fatte.
- **Importazione:** Soci › «Importa da Excel» carica il modello compilato. Prima mostra un'anteprima con gli errori riga per riga, poi si conferma.

**Prima di cominciare (una volta sola).**
1. Installazione della v0.40.0 (LEGGIMI): **una migration**, `npm run build`, `php artisan optimize:clear` e le cache. Nessun pacchetto nuovo da Composer. Nessun seeder da rilanciare: la migration copia il ruolo di ogni utente nella tabella nuova e crea la sezione «Utenti» con i suoi permessi.
2. Sul server servono almeno: il **Presidente** (tu, oggi), un utente con un altro ruolo (Tesoriere o Segretario) e un **Consigliere**, ciascuno con una sua email che riesci a leggere. Se ti mancano, li crei proprio in O3.
3. I due file di prova, nello ZIP in `tests/fixtures/`:
   - `soci-buoni.xlsx`: quattro soci inventati, un minorenne e due con la quota fino al 31/12/2027;
   - `soci-errati.xlsx`: quattro righe con errori diversi.

   Hanno codici fiscali inventati ma validi: si cancellano con O14 (comando di azzeramento, blocco P).
4. L'email del server deve funzionare (link della password e avvisi).

---

### O1 — Dopo l'installazione nulla è cambiato
**Scopo:** controllare che la migration abbia copiato i ruoli e che ognuno veda quello che vedeva prima.
**Chi:** ogni utente di prova, uno alla volta.
**Passi:**
1. Entra con ciascun utente.
2. Guarda il menu a sinistra e, in alto a destra, la scritta sotto il nome.
**Risultato atteso:**
- Il menu è quello di prima. In fondo, sotto «Impostazioni», c'è la voce nuova «Utenti»: la vedono Presidente, Segretario, Tesoriere e Consigliere, non il Revisore.
- Sotto il nome compare il ruolo di sempre (es. «PRESIDENTE»).
- **Se «Utenti» non c'è** (successo al collaudo del 14/10/2026): sono le cache di Laravel rifatte con i file vecchi. Dal Terminale `php artisan optimize:clear`, poi le tre cache (`deploy.md` §5); se `grep -c "utenti.index" config/navigazione.php` dà 0, va estratto di nuovo `deploy.zip`, sovrascrivendo.

### O2 — Elenco degli utenti
**Scopo:** controllare la pagina Utenti per chi modifica e per chi consulta.
**Chi:** Presidente, poi Consigliere, poi Revisore.
**Passi:**
1. Come Presidente apri «Utenti».
2. Come Consigliere apri «Utenti».
3. Come Revisore prova a scrivere a mano l'indirizzo `/utenti` nel browser.
**Risultato atteso:**
- **Presidente:**
  - titolo «Utenti del gestionale» e pulsante «+ Nuovo utente»;
  - una riga per utente con nome (il tuo con «(tu)»), email, ruoli come etichette, «Attivo» o «Disattivato» e ultimo accesso («mai» se non è mai entrato);
  - per ogni utente attivo l'icona del lucchetto e la matita; per i disattivati solo la matita;
  - in fondo il riquadro «Come funzionano i ruoli».
- **Consigliere:** la stessa pagina, con l'avviso «Sola lettura.» e senza pulsanti né icone.
- **Revisore:** pagina d'errore 403 («Il tuo ruolo non ha accesso a questa sezione.»).
- Da telefono la tabella diventa una scheda per utente.

### O3 — Nuovo utente con il link per la password
**Scopo:** creare un utente e farlo entrare senza che nessuno scelga la password al posto suo.
**Chi:** Presidente; poi la persona del nuovo utente.
**Passi:**
1. «+ Nuovo utente»: nome `Prova Consigliere`, una tua email che leggi, ruolo **Consigliere**, «Attivo» e «Mandagli subito il link per scegliere la password» spuntati. Premi «Crea l'utente».
2. Apri l'email arrivata e premi «Scegli la password».
3. Scrivi una password di meno di 12 caratteri e premi «Salva e accedi».
4. Scrivi una password di almeno 12 caratteri (es. una frase), ripetila e premi «Salva e accedi».
5. Esci e prova a usare di nuovo lo stesso link.
**Risultato atteso:**
- Al passo 1 torni all'elenco con il messaggio «Utente Prova Consigliere creato: gli è arrivato il link per scegliere la password.».
- L'email ha oggetto «Gestionale: scegli la tua password», il pulsante «Scegli la password» e la frase «Il link vale **24 ore** e si usa una volta sola.».
- La pagina del link (stile della pagina d'accesso) ha l'email già scritta, «Nuova password» con l'occhio per vederla, l'aiuto «Almeno 12 caratteri…» e «Ripeti la password».
- Al passo 3 compare l'errore sotto la password (almeno 12 caratteri).
- Al passo 4 entri direttamente nella Dashboard con «Password salvata. Benvenuto nel gestionale.».
- Al passo 5: «Il link non è più valido (scaduto o già usato): chiedine un altro con «Password dimenticata?».».
- Nell'Audit Log (come Presidente): «UTENTE_RUOLI» (nuovo utente con i ruoli), «UTENTE_LINK_PASSWORD» e «UTENTE_PASSWORD» (la password scelta, senza la password).

### O4 — Password dimenticata
**Scopo:** controllare la richiesta del link dalla pagina d'accesso.
**Chi:** nessuno collegato (finestra anonima).
**Passi:**
1. Nella pagina d'accesso premi «Password dimenticata?».
2. Scrivi un'email che non è di nessun utente e premi «Mandami il link».
3. Scrivi l'email dell'utente di O3 e premi «Mandami il link».
**Risultato atteso:**
- Ai passi 2 e 3 la risposta è **la stessa**: «Se l'email è di un utente del gestionale, tra poco riceverai il link per scegliere la password.». Così la pagina non rivela chi è utente.
- Solo al passo 3 arriva l'email con il link (al passo 2 nessuna email).
- Un utente **disattivato** non riceve il link.
- Più di 5 richieste in un'ora dallo stesso collegamento: «Troppe richieste. Riprova tra N minuti.».

### O5 — Due ruoli: Segretario e Tesoriere
**Scopo:** dare a te (o a un utente di prova) due ruoli e controllare che i permessi si sommino.
**Chi:** Presidente; poi l'utente con i due ruoli.
**Passi:**
1. Come Presidente, matita sull'utente che deve diventare Segretario e Tesoriere: spunta **Segretario** e **Tesoriere**, togli gli altri, «Salva».
2. Entra con quell'utente.
3. Apri Soci, Domande, Rinnovi quota, Prima Nota (prova «Nuovo movimento»), Bilancio se c'è, Impostazioni.
**Risultato atteso:**
- Al passo 1: «Dati di … aggiornati. Ruoli: Segretario e Tesoriere.»; nell'elenco due etichette blu.
- Sotto il nome, in alto a destra: «SEGRETARIO · TESORIERE».
- Vede le sezioni di tutti e due i ruoli: Soci e Domande (Segretario), Rinnovi quota (Tesoriere). La **Prima Nota la modifica** (il Tesoriere scrive, anche se il Segretario la legge soltanto): c'è «Nuovo movimento» e manca l'avviso di sola lettura.
- Impostazioni: in sola lettura, come prima (per entrambi i ruoli è lettura).

### O6 — Una sola email per persona
**Scopo:** controllare che chi ha due ruoli fra i destinatari di un'email ne riceva una sola.
**Chi:** Presidente.
**Prima:** O5 fatto; nello Scadenzario una scadenza di prova con la data **esattamente fra 30 giorni** (il primo promemoria parte 30 giorni prima), così oggi ha qualcuno a cui partire.
**Passi:**
1. Promemoria › «Scadenze dell'associazione», destinatari «Presidente, Tesoriere e Segretario».
2. Guarda il riquadro «Destinatari».
**Risultato atteso:**
- «Oggi lo riceverebbero N destinatari»: l'utente di O5 compare **una volta**, con «(Segretario e Tesoriere)» accanto al nome, non due volte.
- Se il riquadro dice che oggi nessuno lo riceve, manca la scadenza vicina: aggiungila e riapri la pagina. La scadenza di prova si cancella con M12.

### O7 — Combinazioni non ammesse
**Scopo:** controllare le regole sui ruoli.
**Chi:** Presidente.
**Passi:**
1. «+ Nuovo utente»: spunta **Revisore**.
2. Togli Revisore e spunta **Segretario**; guarda la casella Consigliere.
3. Prova a togliere a te stesso il ruolo di Presidente (sei l'unico) e salva.
4. Prova a togliere a te stesso «Attivo».
**Risultato atteso:**
- Passo 1: le altre caselle si spengono e sotto compare «Il Revisore non si unisce ad altri ruoli: l'Organo di controllo è fuori dal CD.».
- Passo 2: Consigliere è spenta (Consigliere è per chi è nel CD senza cariche).
- Passo 3: in cima «Non salvato. Deve restare almeno un Presidente attivo: prima dai il ruolo a un altro utente.»; i ruoli restano quelli di prima.
- Passo 4: per il tuo utente la casella «Attivo» non c'è; al suo posto «È il tuo utente: non puoi disattivarlo.».

### O8 — I limiti del Segretario
**Scopo:** controllare che il Segretario gestisca gli utenti senza poter toccare il Presidente né i propri ruoli.
**Chi:** l'utente Segretario (es. quello di O5).
**Passi:**
1. Apri «Utenti».
2. Guarda la riga del Presidente.
3. Apri la matita sul tuo utente.
4. Apri la matita su `Prova Consigliere` (O3), aggiungi **Tesoriere** togliendo Consigliere e salva.
5. Apri «+ Nuovo utente» e guarda la casella Presidente.
**Risultato atteso:**
- Passo 2: sulla riga del Presidente niente matita né lucchetto.
- Passo 3: tutte le caselle dei ruoli sono spente, con «I tuoi ruoli li cambia solo il Presidente.»; nome ed email si possono cambiare; niente casella «Attivo».
- Passo 4: salvato. **Il Presidente riceve un'email** «Gestionale: ruoli di un utente cambiati (nome del Segretario)» con la riga dell'Audit Log («Ruoli dell'utente n. …: Consigliere → Tesoriere.»). Il Segretario, che l'ha fatto, non la riceve.
- Passo 5: Presidente spenta, con «Il ruolo di Presidente lo dà o lo toglie solo il Presidente.».

### O9 — Disattivare un utente
**Scopo:** controllare che un utente disattivato non entri e non riceva link.
**Chi:** Presidente; poi l'utente di O3.
**Passi:**
1. Matita su `Prova Consigliere`, togli «Attivo», salva.
2. Prova a entrare con quell'utente.
**Risultato atteso:**
- Nell'elenco l'etichetta «Disattivato» e niente lucchetto.
- L'accesso risponde «Email o password non corrette.» (messaggio generico, come per chiunque).
- Gli altri del CD che vedono gli Utenti ricevono l'avviso «utente modificato».

### O10 — Avviso di un'operazione delicata
**Scopo:** controllare gli avvisi per email fuori dagli Utenti.
**Chi:** Presidente; controlla la posta del Segretario e del Tesoriere.
**Passi:**
1. Come Presidente elimina la scheda di un socio di prova (con la motivazione), come in K7.
**Risultato atteso:**
- Il **Segretario** (vede i Soci) riceve «Gestionale: scheda di un socio eliminata (Andrea …)» con «Scheda del socio n. … eliminata (si può ripristinare), con motivazione.» e il pulsante «Apri il gestionale».
- Il Presidente non riceve nulla: l'ha fatto lui (prima della v0.40.0 gli arrivava «[Audit] Operazione ad alto rischio…»).
- Il Tesoriere da solo (senza Soci) non la riceve.

### O11 — Importazione: file con errori
**Scopo:** controllare l'anteprima con gli errori e che niente si registri.
**Chi:** Segretario (o Presidente).
**Passi:**
1. Soci › «Importa da Excel». Premi «Scarica il modello» e controlla che si apra il modello con i fogli «Istruzioni» e «Soci».
2. Scegli `soci-errati.xlsx` e premi «Leggi il file».
3. Leggi l'anteprima.
4. Premi «Carica il file corretto».
**Risultato atteso:**
- Riquadri: «Righe lette 4 · Riga d'esempio scartata», «Pronte 0», «Con avvisi 0», «Con errori 4»; avviso rosso «4 righe con errori. Correggile nel foglio e ricarica il file…».
- Errori mostrati riga per riga (il numero di riga è quello di Excel):
  - riga 3: «Il codice fiscale non è corretto: controlla di averlo scritto bene.»;
  - riga 4: tra gli altri, «Quota valida fino al: deve essere il 31/12 di un anno.», «Fondatore: scrivi «Sì» o «No»…», «Minorenne: del genitore mancano codice fiscale, telefono e email.», «Relazione del genitore: padre, madre o tutore.»;
  - riga 5: provincia, email non scritta bene, «La data di ammissione è nel futuro.», «Codice fiscale ripetuto nel file (anche alla riga 6).»;
  - riga 6: «Data di ammissione: «31/02/2020» non è una data…».
- Il pulsante «Importa» non c'è. Nel libro soci nessun socio nuovo.
- Al passo 4 si torna alla scelta del file; il file caricato non resta sul server.
- Un file che non è il modello (es. un altro Excel): «Il file non è il modello dei soci: manca il foglio «Soci».» oppure «Le colonne non sono quelle del modello…».

### O12 — Importazione riuscita
**Scopo:** importare i soci e controllare i dati, le quote e i passi successivi.
**Chi:** Presidente (così l'avviso arriva al Segretario).
**Passi:**
1. «Importa da Excel», scegli `soci-buoni.xlsx`, «Leggi il file».
2. Spunta «Mostra anche le righe pronte» se compare, poi premi «Importa 4 soci».
3. Premi «Vedi i soci importati» e apri le schede.
4. Rinnovi quota › anno 2027.
5. Ricarica lo stesso file.
**Risultato atteso:**
- Anteprima: «Nessun errore.», «Pronte 4», riga d'esempio scartata. Per ogni riga la nascita ricavata dal codice fiscale (es. «10/09/2012 · Macerata (MC) · F, 14 anni»), il tipo (Ordinario o Minorenne), «Fondatrice» per Bianchi Anna.
- Dopo la conferma: «Importati 4 soci. Il file è stato cancellato dal server…», riepilogo «4 · 3 maggiorenni, 1 minorenne», «Quote del vecchio gestionale dal 2027: 2», e i prossimi passi (link privacy, tessere CSEN, cancellare il foglio).
- Elenco con «Soci importati il …»:
  - Neri Sofia è minorenne, con il genitore Neri Marco («Padre»);
  - Rossi Mario ha il CAP 05014 (lo zero davanti non si è perso);
  - la privacy è «mancante» per tutti.
- Rinnovi quota 2027: Verdi Luca e Neri Sofia «in regola», con la ricevuta «— vecchio gestionale»; niente icone per scaricare o inviare la ricevuta. In Prima Nota nessun movimento nuovo.
- Il Segretario riceve l'avviso «soci importati da Excel». Nell'Audit Log la riga «IMPORT_SOCI» e una per ogni socio.
- Passo 5: tutte le righe in errore con «Già nel libro soci: … Togli la riga.».

### O13 — Annullare una quota del vecchio gestionale
**Scopo:** controllare che una quota importata per errore si possa togliere.
**Chi:** Tesoriere.
**Passi:**
1. Rinnovi quota › 2027, sulla riga di Verdi Luca premi il cestino; motivazione di almeno 15 caratteri; conferma.
**Risultato atteso:**
- La finestra si chiama «Annullare la quota 2027 del vecchio gestionale?» e dice che non ha ricevuta né movimento.
- Messaggio «Quota 2027 del vecchio gestionale annullata.»; Verdi Luca torna «da versare» per il 2027; nella scheda la quota risulta valida fino al 31/12/2026 (quella del vecchio gestionale resta).

### O14 — Azzeramento delle prove del blocco O
Dalla v0.41.0 con il comando `php artisan gestionale:azzera` (blocco P, `collaudo-P-azzeramento.md`), dopo l'Assemblea:
- i quattro soci di `soci-buoni.xlsx` e le loro quote si svuotano con gli altri dati di prova;
- l'utente `Prova Consigliere` resta, disattivato: gli utenti non si toccano (decisione D1);
- i ruoli veri del CD si danno dalla pagina Utenti dopo l'azzeramento (Presidente Andrea Papini; Segretario e Tesoriere Paolo Zanetti; Consiglieri Massimo Papini e Luigi Tascio).

---

## Esiti del collaudo (Paolo, dal 15/10/2026)

Osservazioni riportate prova per prova. Quelle che chiedono una scelta passano da un'analisi breve e da una domanda a Paolo prima del codice.

### O2 — osservazioni (16/10/2026)
- **O2.1** Nome e cognome in due campi distinti, negli utenti e anche nei soci.
- **O2.2** Serve anche un utente **sysadmin** (amministratore di sistema).

### O3 — osservazioni (16/10/2026)
- **O3.1** Il Presidente può essere uno solo: se lo si assegna a un secondo utente, va segnalato l'errore.
- **O3.2** Si sceglie un solo ruolo per utente; l'unica coppia ammessa è **Segretario + Tesoriere**.
- **O3.3** L'utente si sceglie dall'elenco dei soci: i membri del CD non possono essere esterni alla Compagnia.
- **O3.4** Link già usato: il messaggio «Il link non è più valido…» deve comparire subito sulla pagina a cui porta il link, non solo dopo aver scritto la nuova password.
- **O3.5** Manca l'occhio per vedere la password anche in «Ripeti la password».

### O8 — osservazioni (16/10/2026)
- **O8.1** Un ruolo non può stare su due utenti diversi, tranne **Consigliere** (insieme a O3.1: un solo Presidente, e così un solo Segretario, Tesoriere e Revisore).

### O9 — osservazioni (16/10/2026)
- **O9.1** Per un utente disattivato l'accesso deve dire «Utente non attivo nel gestionale», non «Email o password non corrette.». Da valutare: il messaggio generico era voluto (non rivela a chi prova quali email esistono). Si può dare il messaggio nuovo solo quando la password è giusta.

### O10 — osservazioni (16/10/2026)
- **O10.1** Eliminata la scheda di un socio, l'avviso al Segretario **non è arrivato**. In locale il test (`UtentiTest`, SOCIO_DELETED) passa: da verificare sul server con il registro delle email (`gl_email_log`, tipo `avviso_operazione`) e lo stato del Segretario (attivo, email, ruolo).

### O12 — osservazioni (16/10/2026)
- **O12.1** Al Segretario non è arrivato l'avviso «soci importati da Excel». Stessa strada di O10.1 (avviso verso il Segretario di un'operazione del Presidente).

Collaudo O concluso da Paolo il 16/10/2026. Analisi di tutte le osservazioni: `docs/analisi-collaudo-O.md`.

### Fuori elenco — scheda del socio (16/10/2026)
- **O-extra.1** Nuovo socio con il codice fiscale sbagliato: anche dopo la correzione restava la segnalazione d'errore; spariva solo ricaricando la pagina. Riprodotto: succede dopo un salvataggio con il codice sbagliato, quando l'errore del server resta fino al salvataggio successivo. **Corretto** per la v0.42.0 (`soci.md` §7, `SocioFormTest`). Da ricontrollare nel collaudo O-bis.

Fuori collaudo: il 16/10/2026 Paolo ha perso la password del Presidente sul server; reimpostata a mano dal database (nessun difetto del codice).
