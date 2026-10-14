# Collaudo M — Scadenze dell'associazione e promemoria

Blocco della lista di collaudo del Gestionale ASD, in un file a sé (decisione di Paolo, 12/10/2026: un documento per blocco, perché la lista unica cresceva troppo). Regole comuni (preparazione, reset, CD di prova, cosa portare quando una prova fallisce) in `test-domande-iscrizione.md`, nella stessa cartella. Copia nel progetto: `claude/collaudo-M-scadenzario.md`.

## Scadenze dell'associazione e promemoria «N giorni prima» (v0.38.0, correzioni v0.38.1)

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
**Quando:** solo alla fine del collaudo, prima dell'apertura del 2027.
**Passi:** dalla v0.41.0 si fa con il comando `php artisan gestionale:azzera` (blocco P, `collaudo-P-azzeramento.md`). Svuota i rinnovi dello Scadenzario e le scadenze create a mano, e rimette le tre scadenze pronte alla prossima data vera (affiliazione 31/12, polizza con il giorno vero, rendiconto 30/04). La prova a vuoto mostra le date prima e dopo.
**Attenzione:** non registrare con «Rinnovata» i pagamenti già fatti per le scadenze in corso, perché «Rinnovata» sposta la data all'anno dopo. Il primo «Rinnovata» vero sarà, per esempio, l'affiliazione 2027 a dicembre: l'importo scritto quella volta verrà poi proposto negli anni seguenti.
