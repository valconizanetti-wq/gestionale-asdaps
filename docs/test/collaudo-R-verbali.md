# Collaudo R — Verbali, riunioni del CD e consultazioni telematiche

Blocco della lista di collaudo del Gestionale ASD, in un file a sé. Le regole comuni sono in `test-domande-iscrizione.md`, nella stessa cartella. Copia nel progetto: `claude/collaudo-R-verbali.md`.

Versione: **v0.44.0** (21/10/2026).
- Scheda: `docs/specifiche/verbali.md`.
- Analisi: `docs/analisi-verbali-assemblee.md` (risposte V1–V18).
- Tavole VB-1…VB-5, CD-5, CT-1…CT-3: `Tavole-verbali.pdf`, fuori dal repository.

**Che cosa si prova.**
- I **libri dei verbali** (CD e Assemblee), con il numero nell'anno.
- La **riunione del CD**: bozza, convocazione con i termini, rinvio, annullamento, presenze con il quorum.
- Il **verbale per punti** con il voto di ciascuno e l'esito calcolato.
- **Definitivo**, **copia firmata digitalmente**, **rettifica**.
- La **consultazione telematica**: bozza, avvio del Presidente, voto dal link, chiusura, verbale.
- I verbali delle **domande** nel libro, i verbali **registrati da fuori**, la **chiusura dell'anno**, il **verbale della nomina** nel CD.
- La voce **Anticipi** nel menu (rimasta dalla v0.43.0).

**Regola delle votazioni** che le prove controllano: la maggioranza si conta tra favorevoli e contrari; gli astenuti restano fuori; in parità prevale il Presidente, se ha votato; senza favorevoli né contrari la delibera non passa; «non partecipa» (conflitto di interessi) non vota; l'esclusione di un socio passa con i favorevoli almeno pari alla maggioranza dei componenti.

**Prima di cominciare (una volta sola).**
1. Installa la v0.44.0 (LEGGIMI):
   - `composer install` non serve (nessun pacchetto PHP nuovo);
   - **`npm install`** (arriva l'editor Jodit 4.17.1) e **`npm run build`**;
   - **una migration** (`php artisan migrate`);
   - `php artisan optimize:clear` e le tre cache;
   - il **cron** c'è già: dalla v0.44.0 lancia anche `consultazioni:scadenze` ogni 15 minuti.
2. Il CD di prova, con gli utenti collegati ai soci (come dopo il collaudo O-bis):
   - Andrea Papini, Presidente;
   - Paolo Zanetti, Segretario e Tesoriere;
   - Luigi Tascio e Massimo Papini, Consiglieri.

   Tutti con l'email nella scheda del socio (`valconi@usa.net` va bene per tutti: l'oggetto delle email comincia con il nome del destinatario).
3. Serve un **PDF firmato digitalmente** per le prove R12, R13 e R21. Si fa con il programma di firma che usi (CIE, kit di firma): firma in formato **PAdES** (PDF) un PDF qualsiasi, prima con una firma sola, poi una seconda volta (due firme). Tieni anche un PDF **non** firmato.
4. Tieni a portata due PDF qualsiasi da usare come documenti (preventivo, rendiconto).

Le date: dove serve «il giorno della riunione» o «la scadenza», le prove dicono la riga SQL da lanciare in phpMyAdmin. N = numero della riunione o della consultazione, si legge nell'indirizzo della pagina (`/verbali/riunioni/N`, `/verbali/consultazioni/N`).

---

### R1 — Menu e permessi
**Scopo:** controllare le voci del menu e chi può scrivere.
**Chi:** Segretario, poi Consigliere (Luigi Tascio), poi Revisore.
**Passi:**
1. Entra come Segretario. Guarda il menu a sinistra.
2. Apri **Prima Nota**, poi **Anticipi**, poi **Verbali**.
3. Entra come Consigliere. Apri **Verbali**.
4. Nella barra degli indirizzi apri `/verbali/riunioni/nuova`.
5. Entra come Revisore e apri **Verbali**.

**Risultato atteso:**
- Nel gruppo Contabilità, sotto «Prima Nota», c'è la voce **«Anticipi»**. Su Prima Nota si accende solo «Prima Nota»; sugli anticipi solo «Anticipi»; su Verbali solo «Verbali».
- La pagina Verbali ha il titolo «Libri dei **verbali**», le schede «Consiglio Direttivo» e «Assemblee» e, per il Segretario, i pulsanti «Registra un verbale già redatto», «+ Consultazione», «+ Riunione del CD».
- Il Consigliere vede la stessa pagina con l'avviso di sola lettura e senza pulsanti; al passo 4 la pagina «403».
- Il Revisore vede la pagina in sola lettura.

### R2 — Nuova riunione del CD (bozza)
**Scopo:** preparare la riunione con i controlli dei campi.
**Chi:** Segretario.
**Passi:**
1. Verbali › «+ Riunione del CD».
2. Svuota il primo punto dell'ordine del giorno e il luogo, poi premi «Salva in bozza».
3. Compila:
   - data: fra **5 giorni**, ora 21:00;
   - luogo «Sede della Compagnia»;
   - spunta «Anche in videoconferenza» e scrivi un link qualsiasi in «Collegamento»;
   - punti: «Acquisto di sei paglioni», poi «Aggiungi un punto» → «Calendario delle gare», poi «Aggiungi un punto» → «Varie ed eventuali»;
   - un documento PDF.

   Premi «Salva in bozza».
4. Premi «Modifica», togli il punto «Calendario delle gare» con il cestino e salva.

**Risultato atteso:**
- Al passo 2 in rosso: «Scrivi il luogo, o spunta la videoconferenza.» e l'avviso «Riunione non salvata.»
- Al passo 3 si apre la scheda «Riunione del **CD · gg/mm/aaaa**» con il badge «Bozza», i passi Bozza · Convocata · Presenze · Verbale, l'ordine del giorno a destra, il documento in «Documenti», con il cestino.
- Nel riquadro «Convocazione» la frase «La convocazione parte per email ai **4 membri in carica**…» e l'elenco dei membri con l'email.
- Al passo 4 restano due punti, numerati 1 e 2.
- In Verbali, scheda Consiglio Direttivo, sotto «In corso»: «Riunione del gg/mm/aaaa · ore 21:00 · Sede della Compagnia · videoconferenza · 2 punti» con il badge «Bozza».

### R3 — Termini della convocazione (art. 16.1)
**Scopo:** con meno di 3 giorni si convoca solo come urgente; con meno di 24 ore mai.
**Chi:** Segretario.
**Passi:**
1. Nuova riunione con data **domani**, ora 21:00, luogo e un punto. Salva.
2. Guarda il riquadro «Convocazione».
3. «Modifica»: spunta «Convocazione urgente» senza motivo, salva.
4. Scrivi il motivo «Scadenza del bando regionale», salva.
5. «Modifica»: data **oggi**, ora fra un'ora; salva e guarda il riquadro.
6. Rimetti la data di domani e salva.

**Risultato atteso:**
- Al passo 2 l'avviso giallo «Non si può ancora convocare. Mancano meno di 3 giorni alla riunione: si convoca solo come urgente, con il motivo (art. 16.1).» e nessun pulsante «Invia la convocazione».
- Al passo 3 in rosso «Scrivi il motivo dell'urgenza.»
- Al passo 4 il badge «Urgente» e il pulsante «Invia la convocazione».
- Al passo 5 l'avviso «La convocazione deve partire almeno 24 ore prima della riunione (art. 16.1).»
- Al passo 6 torna il pulsante. Non convocarla: si annulla in R6.

### R4 — Prova e convocazione
**Scopo:** mandare la prova solo a sé e poi la convocazione ai membri.
**Chi:** Segretario.
**Prima:** la riunione di R2 (fra 5 giorni).
**Passi:**
1. Nella scheda premi «Invia una prova a me». Apri l'email.
2. Premi «Invia la convocazione». Apri le email.
3. Premi «Modifica» (se c'è) o prova ad aprire `/verbali/riunioni/N/modifica`.

**Risultato atteso:**
- Al passo 1 il messaggio verde «Prova inviata a …» e una sola email, con l'oggetto che comincia «[PROVA] Paolo Zanetti · Convocazione del Consiglio Direttivo, …» e il riquadro «Prova. Questa email è arrivata solo a te…». La riunione resta in bozza.
- Al passo 2 «Convocazione inviata a 4 membri.», badge «Convocata», passo 2 acceso con la data. Ogni email ha: «Convocazione», quando, dove, «In videoconferenza» con il collegamento, l'ordine del giorno numerato, «Documenti (1): …» e il pulsante «Apri la riunione nel gestionale».
- Al passo 3 si torna alla scheda con «Dopo la convocazione la riunione non si modifica: rinviala e riconvocala.»
- In Audit Log l'evento «SEDUTA_CONVOCATA».

### R5 — Rinvio e nuova convocazione
**Scopo:** rinviare una riunione convocata, con il motivo, e riconvocarla.
**Chi:** Segretario.
**Prima:** convoca come in R4 una seconda riunione (fra 5 giorni, un punto).
**Passi:**
1. Nella scheda premi «Rinvia». Scrivi «Manca» e premi «Rinvia».
2. Scrivi «Manca il preventivo del fornitore», lascia la spunta «Avvisa i membri per email» e premi «Rinvia».
3. Premi «Riconvoca».
4. Cambia la data (fra 6 giorni), salva, «Invia la convocazione».

**Risultato atteso:**
- Al passo 1 la finestra resta aperta con «Il motivo deve avere almeno 10 caratteri.»
- Al passo 2: «Riunione rinviata: avviso inviato a 4 membri. Con «Riconvoca» si sceglie la nuova data.»; l'avviso «Rinviata il … Motivo: Manca il preventivo del fornitore…»; le email «… · Consiglio Direttivo: la riunione del … è rinviata» con il motivo.
- Al passo 3 si apre la modifica con «La riunione è tornata in bozza: scegli la nuova data e invia di nuovo la convocazione.»
- Al passo 4 nuova convocazione con la nuova data. In Audit Log «SEDUTA_RINVIATA» (rischio medio).

### R6 — Annullamento
**Scopo:** annullare una riunione non tenuta.
**Chi:** Segretario.
**Prima:** la riunione urgente di R3; convocala.
**Passi:**
1. Premi «Annulla la riunione», scrivi «Riunione non più necessaria» e conferma.

**Risultato atteso:**
- Badge «Annullata», avviso «Annullata il … Motivo: Riunione non più necessaria», nessun pulsante.
- Non compare più sotto «In corso».
- Agli altri utenti del CD arriva l'email delle operazioni delicate (rischio alto, evento «SEDUTA_ANNULLATA»).

### R7 — Presenze e quorum
**Scopo:** registrare le presenze dal giorno della riunione, con il quorum, chi presiede e chi verbalizza.
**Chi:** Segretario.
**Prima:** la riunione di R4 (convocata, fra 5 giorni).
**Passi:**
1. Apri la scheda: guarda il riquadro «Presenze».
2. In phpMyAdmin porta la riunione a oggi: `UPDATE gl_assemblee SET data = CURDATE() WHERE id = N;` e ricarica.
3. Segna Andrea Papini e Paolo Zanetti «In sede», gli altri due «Assente». Guarda la riga del quorum.
4. Segna Luigi Tascio «In videoconferenza». Spunta «Giustificato» per Massimo Papini.
5. In «Segretario verbalizzante» scegli Massimo Papini (assente): non si può. Lascia «Presiede: Papini Andrea», «Segretario verbalizzante: Zanetti Paolo» e premi «Registra le presenze e scrivi il verbale».

**Risultato atteso:**
- Al passo 1 «Le presenze si registrano dal giorno della riunione, gg/mm/aaaa.»
- Al passo 3 in rosso «2 presenti su 4 · non costituito: servono almeno 3 presenti, non si delibera».
- Al passo 4 in verde «3 presenti su 4 · validamente costituito (servono 3, art. 16.3)».
- Al passo 5 il nome di chi è assente nell'elenco è spento. Si apre «Verbale della **riunione**» con «Presenze registrate. Ora scrivi il verbale, punto per punto.», i badge «Bozza» e «3 presenti su 4 · validamente costituito», una card per punto dell'ordine del giorno.
- In Verbali, sotto «In corso», la riunione ha il badge «Presenze registrate»; nel libro del CD c'è il verbale «Riunione del Consiglio Direttivo del …», N. «—», «Bozza».

### R8 — Verbale per punti, voti ed esito
**Scopo:** scrivere discussione e delibera con il voto di ciascuno e vedere l'esito calcolato.
**Chi:** Segretario.
**Prima:** R7.
**Passi:**
1. Punto 1: in «Discussione» scrivi «Il Presidente illustra il preventivo di Arco Sport», con «Arco Sport» in grassetto (pulsante B).
2. Spunta «Su questo punto il Consiglio delibera». Tipo «Delibera». Testo: «Il Consiglio Direttivo delibera di acquistare sei paglioni (Allegato 1).»
3. Voti: Andrea Papini **Favorevole**, Paolo Zanetti **Contrario**, Luigi Tascio **Astenuto**. Guarda il conto sopra la tabella.
4. Premi «Salva».
5. Cambia: Tascio **Contrario**. Salva.
6. Cambia: Tascio **Non partecipa**. Salva.
7. Punto 2: scrivi nella discussione «Nessun intervento.» Ora di chiusura 22:30. Premi «Salva e torna alla scheda».

**Risultato atteso:**
- L'editor ha la barra corta (paragrafo, grassetto, corsivo, sottolineato, elenchi, rientri, link, tabella, linea, annulla, ripeti, pulisci) e **nessun** pulsante per il codice HTML.
- Al passo 3 il conto dice «1 favorevole · 1 contrario · 1 astenuto».
- Al passo 4 sotto la tabella, in verde: «Esito salvato. Approvata con il voto del Presidente: 1 favorevole, 1 contrario, 1 astenuto; in parità prevale il Presidente (art. 16.3).»
- Al passo 5 in rosso: «Respinta: 1 favorevole, 2 contrari.»
- Al passo 6: «Approvata con il voto del Presidente: 1 favorevole, 1 contrario, 1 non partecipa; …».
- Al passo 7 la scheda del verbale con il testo completo: premessa con data, ora, luogo e «videoconferenza», ordine del giorno, tabella delle presenze (Tascio «In videoconferenza», Massimo Papini «Assente (giustificato)»), «Presiede Andrea Papini; verbalizza Paolo Zanetti. Sono presenti 3 componenti su 4: il Consiglio è validamente costituito…», la delibera con la tabella dei voti e l'esito in grassetto, «… la riunione si chiude alle ore 22:30.»
- Nel riquadro «Rendi definitivo» nessuna mancanza e il numero proposto («numero 1» se è il primo del CD dell'anno).

### R9 — Consiglio non costituito
**Scopo:** con il CD non costituito non si delibera.
**Chi:** Segretario.
**Prima:** una nuova riunione convocata (come R4), portata a oggi con l'SQL di R7.
**Passi:**
1. Presenze: solo Andrea Papini e Paolo Zanetti presenti, gli altri assenti senza giustificazione. Registra.
2. Guarda il punto 1.

**Risultato atteso:**
- L'avviso giallo «Il Consiglio non è validamente costituito. Servono almeno 3 presenti su 4 (art. 16.3): si verbalizza la discussione, ma non si delibera.»
- La spunta «Su questo punto il Consiglio delibera» è spenta.
- Il PDF di anteprima dice «il Consiglio non è validamente costituito e non può deliberare (art. 16.3)».

### R10 — Esclusione di un socio (art. 9.1 c)
**Scopo:** l'esclusione passa solo con la maggioranza dei componenti.
**Chi:** Segretario.
**Prima:** una riunione con tutti e 4 presenti (come R7, tutti «In sede»).
**Passi:**
1. Punto 1: delibera di tipo «Esclusione di un socio», testo qualsiasi. Voti: Andrea Papini e Paolo Zanetti favorevoli, gli altri astenuti. Salva.
2. Cambia: Luigi Tascio favorevole. Salva.

**Risultato atteso:**
- Al passo 1: «Respinta: 2 favorevoli, 0 contrari, 2 astenuti. Servono almeno 3 favorevoli su 4 componenti (art. 9.1 c).»
- Al passo 2: «Approvata: 3 favorevoli, 0 contrari, 1 astenuto. Servono almeno 3 favorevoli su 4 componenti (art. 9.1 c).»
- Questa riunione serve solo per la prova: lasciala in bozza.

### R11 — Rendi definitivo e PDF
**Scopo:** dare il numero e salvare il PDF che non cambia più.
**Chi:** Segretario, poi Revisore.
**Prima:** il verbale di R8.
**Passi:**
1. Nella scheda del verbale premi «Anteprima PDF».
2. Premi «Rendi definitivo» e conferma nella finestra.
3. Prova ad aprire `/verbali/N/scrivi` (N del verbale).
4. Entra come Revisore e apri il PDF.

**Risultato atteso:**
- Al passo 1 il PDF sulla carta intestata con la scritta «BOZZA · senza numero, non è il verbale definitivo», il titolo «Verbale del Consiglio Direttivo», il testo come nella scheda e in fondo le righe «Il presidente della seduta Andrea Papini» e «Il segretario verbalizzante Paolo Zanetti».
- La finestra dice «Il verbale prende il numero **2026/1** …» e «Da qui non si modifica più…».
- Dopo: «Verbale definitivo: è il n. 2026/1 del libro. Ora fallo firmare digitalmente e carica la copia firmata.»; titolo «Verbale **CD 2026/1**», badge «Definitivo · da firmare», passo 2 acceso; nella colonna Dati «Impronta del PDF».
- Il PDF ora non ha più la scritta BOZZA e dice «Numero 2026/1 del libro, reso definitivo il …».
- Al passo 3 si torna alla scheda (niente modifica).
- Nel libro: N. «2026/1», «Definitivo · da firmare». Il riquadro «Da firmare» vale 1.
- Gli altri del CD ricevono l'email delle operazioni delicate (evento «VERBALE_DEFINITIVO», rischio alto).

### R12 — Copia firmata digitalmente
**Scopo:** accettare solo la firma digitale, con le regole delle due firme (V14).
**Chi:** Segretario.
**Prima:** R11; i tre PDF del punto 3 della preparazione.
**Passi:**
1. Nel riquadro «Copia firmata» carica il PDF **non firmato**.
2. Carica il PDF con **una firma**, senza scegliere «Chi ha firmato».
3. Ricarica lo stesso file scegliendo «Il presidente e il segretario».
4. Ricarica scegliendo «Solo il segretario verbalizzante».
5. Apri «Sostituisci la copia firmata», carica il PDF con **due firme** senza motivo.
6. Rifallo con il motivo «Aggiunta la firma del Presidente».

**Risultato atteso:**
- Al passo 1: «Copia non caricata. Il file non contiene una firma digitale: carica il PDF firmato con la firma digitale (CIE o kit di firma) o il file .p7m.»
- Ai passi 2 e 3: «Nel file c'è una sola firma digitale: si accetta solo se è del segretario verbalizzante. Confermalo, oppure fai firmare anche il presidente della seduta.»
- Al passo 4: «Copia firmata caricata: il verbale è firmato.»; badge «Firmato»; l'avviso giallo «Manca la firma del presidente della seduta.»; Firma «1 firma digitale (PAdES)», «Firmatari letti nel file» con il nome scritto dal programma di firma (o «non scritti nel file»). Nel libro la colonna «Copia firmata» ha la stessa avvertenza in rosso.
- Al passo 5: «Per sostituire la copia firmata scrivi il motivo (almeno 15 caratteri).»
- Al passo 6: «2 firme digitali (PAdES)», l'avviso giallo sparisce. In Audit Log «VERBALE_FIRMATO» e «VERBALE_FIRMATO_SOSTITUITO» (alto).
- «Copia firmata» in alto apre il file caricato.

### R13 — Rettifica
**Scopo:** correggere un verbale definitivo senza toccarlo.
**Chi:** Segretario.
**Prima:** R12.
**Passi:**
1. Nella scheda apri «Aggiungi una rettifica». Testo «Al punto 1 leggasi: sette paglioni.», motivo «Errore». Premi «Aggiungi la rettifica».
2. Motivo «Errore di trascrizione del numero». Riprova.
3. Apri «PDF» della rettifica.
4. Carica come copia firmata della rettifica il PDF con una firma.
5. Riapri il PDF definitivo del verbale.

**Risultato atteso:**
- Al passo 1 la sezione resta aperta con «Il motivo deve avere almeno 15 caratteri.»
- Al passo 2: «Rettifica n. 1 aggiunta in fondo al verbale. Falla firmare e carica la copia firmata.»; il badge «Con rettifica»; nel riquadro «Rettifiche» data, motivo e testo.
- Al passo 3 il PDF «Rettifica n. 1 al verbale CD 2026/1 …» con il motivo, il testo e le due righe di firma.
- Al passo 4 «Firmata digitalmente (PAdES), caricata il …» e il pulsante «Firmata».
- Al passo 5 il PDF del verbale è identico a prima (la rettifica è un documento a parte). Nel libro, sotto il titolo, «· con rettifica».

### R14 — Consultazione telematica: bozza e avvio
**Scopo:** la prepara il Segretario, la avvia solo il Presidente (V16).
**Chi:** Segretario, poi Presidente.
**Passi:**
1. Come Segretario: Verbali › «+ Consultazione». Oggetto «Quota per la gara regionale», testo «Il Consiglio delibera di contribuire con 200 euro alle iscrizioni.», termine fra **2 giorni**, un documento PDF. Salva.
2. Nella scheda guarda il riquadro «Avvio».
3. «Modifica»: termine fra **4 giorni**, salva.
4. Entra come Presidente, apri la consultazione da «In corso» e premi «Avvia la consultazione».

**Risultato atteso:**
- Nel modulo l'avviso «Non si usa per il **rendiconto** né per l'**esclusione di un socio**…».
- Al passo 1 «Consultazione salvata in bozza. La avvia il Presidente.»
- Al passo 2 l'avviso «Termine troppo vicino. Dall'avvio servono almeno 3 giorni…» e, per il Segretario, la frase «La avvia il Presidente (art. 16.4) o, in sua sostituzione, il Vicepresidente…» senza pulsante.
- Al passo 4: «Consultazione avviata: link personale inviato a 4 componenti.»; badge «In corso»; passo «Voto · dal gg/mm»; la tabella dei voti con tutti «non ha votato»; «Esito fin qui» con «Votanti 0 su 4, Ne servono 3».
- Ogni componente riceve «… · Consiglio Direttivo: consultazione telematica · Quota per la gara regionale» con il termine e il pulsante «Apri la pagina della consultazione».
- (Se c'è un Vicepresidente nel CD di prova: lui vede la spunta obbligatoria «Avvio in sostituzione del Presidente, assente o impedito (art. 18.3)»; avviata da lui, il badge «Avviata dal Vicepresidente».)

### R15 — Voto dal link (telefono)
**Scopo:** votare dalla pagina pubblica.
**Chi:** dal telefono, con i link delle email (non serve essere collegati).
**Prima:** R14.
**Passi:**
1. Apri il link di Andrea Papini. Apri il documento.
2. Senza scegliere premi «Registra il voto».
3. Scegli «Favorevole» e registra.
4. Ricarica la pagina, poi riapri il link.
5. Con il link di Paolo Zanetti: «Contrario», nota «Costa troppo».
6. Dal computer (Segretario) apri la scheda della consultazione.

**Risultato atteso:**
- Pagina con il tema del sito: «Consiglio Direttivo · consultazione telematica», titolo uguale all'oggetto, «Ciao Andrea, …», «Proposta di delibera» con il testo, il documento che si apre, «Vota entro …», quattro scelte (Favorevole, Contrario, Astenuto, «Ne discutiamo in riunione»), la nota facoltativa.
- Al passo 2 il browser chiede di scegliere.
- Al passo 3 e 4 «Voto registrato», «Grazie Andrea…», il badge «Favorevole», «La consultazione è aperta fino a …». Il voto non si cambia.
- Al passo 6: Papini «Favorevole» e Zanetti «Contrario» con data, ora e il codice del collegamento («…xxxx»), la nota sotto il nome; «Se si chiudesse adesso: Non valida: hanno votato 2 su 4 componenti, ne servivano almeno 3 (art. 16.4 c).»

### R16 — Chiusura quando hanno votato tutti
**Scopo:** chiusura automatica, esito, verbale ed email.
**Chi:** dal link; poi Segretario.
**Prima:** R15.
**Passi:**
1. Con i link di Luigi Tascio e Massimo Papini vota «Astenuto».
2. Come Segretario apri la consultazione, poi «Verbale».
3. Rendi definitivo il verbale e apri il PDF.

**Risultato atteso:**
- Badge «Approvata»; «Esito»: «Approvata con il voto del Presidente: 1 favorevole, 1 contrario, 2 astenuti; in parità prevale il Presidente (art. 16.3). Votanti 4 su 4 componenti.»
- Ogni componente riceve «Consiglio Direttivo: consultazione esito · Quota per la gara regionale» con l'esito.
- Il verbale «Consultazione telematica: Quota per la gara regionale» è nel libro del CD in bozza, con il documento; il testo ha la premessa (avvio, termine, chiusura), la proposta, la tabella dei voti con data, ora e «link personale …xxxx», l'esito.
- Al passo 3 prende il numero successivo (es. 2026/2); presidente e segretario della seduta sono Andrea Papini e Paolo Zanetti.

### R17 — Un terzo chiede la riunione
**Scopo:** la consultazione si ferma se almeno un terzo chiede di discuterne.
**Chi:** Presidente; poi dal link.
**Passi:**
1. Prepara e avvia un'altra consultazione (termine fra 4 giorni).
2. Con il link di Luigi Tascio scegli «Ne discutiamo in riunione».
3. Con il link di Massimo Papini lo stesso.

**Risultato atteso:**
- Dopo il passo 2 è ancora «In corso» («Chiedono la riunione 1, Con 2 si ferma»).
- Dopo il passo 3 badge «Da discutere in riunione», esito «Da discutere in riunione: 2 su 4 componenti lo hanno chiesto (almeno un terzo, art. 16.4 d). La consultazione si ferma.»; email dell'esito; verbale in bozza nel libro.

### R18 — Scadenza con pochi votanti
**Scopo:** alla scadenza si chiude da sola; con pochi votanti è «non valida».
**Chi:** Presidente, dal link, poi in phpMyAdmin.
**Passi:**
1. Avvia un'altra consultazione; vota solo con il link di Andrea Papini («Favorevole»).
2. In phpMyAdmin: `UPDATE gl_consultazioni SET termine = NOW() - INTERVAL 1 MINUTE WHERE id = N;` poi sul server `php artisan consultazioni:scadenze` (o aspetta il cron, al più 15 minuti).
3. Apri il link di Paolo Zanetti.

**Risultato atteso:**
- Il comando scrive «Consultazioni chiuse: 1».
- Badge «Non valida», esito «Non valida: hanno votato 1 su 4 componenti, ne servivano almeno 3 (art. 16.4 c).»
- Al passo 3 la pagina «Consultazione chiusa»: «La consultazione su … si è chiusa prima del tuo voto, che quindi non è stato registrato.» con l'esito.

### R19 — Sollecito e ritiro
**Scopo:** sollecitare chi non ha votato e ritirare la consultazione.
**Chi:** Segretario, poi Presidente.
**Passi:**
1. Avvia un'altra consultazione (Presidente). Come Segretario premi «Sollecita chi non ha votato».
2. Come Segretario cerca il pulsante «Ritira la consultazione».
3. Come Presidente premi «Ritira la consultazione», motivo «Il fornitore ha ritirato l'offerta».

**Risultato atteso:**
- Al passo 1 «Sollecito inviato a 4 componenti.»; le email «… la consultazione è ancora aperta …» con lo stesso link; in tabella «non ha votato · sollecitato il gg/mm».
- Al passo 2 il Segretario non ha il riquadro «Ritiro».
- Al passo 3: «Consultazione ritirata: i componenti sono avvisati per email.»; avviso «Ritirata il … Motivo: … I voti non contano; non c'è verbale.»; email «consultazione ritirata». Evento «CONSULTAZIONE_RITIRATA» (rischio alto).

### R20 — Verbali delle domande nel libro
**Scopo:** i verbali delle domande decise si rendono definitivi insieme (V11).
**Chi:** Segretario.
**Prima:** almeno due domande **vere** (non di prova) con il voto del CD chiuso (approvate o rifiutate) e il verbale in bozza. Se non ci sono, salta la prova e annotalo.
**Passi:**
1. Apri Verbali, scheda Consiglio Direttivo.
2. Premi «Rendi definitivi i N verbali».
3. Da una domanda apri «Verbale».

**Risultato atteso:**
- L'avviso giallo «N verbali di domande sono chiusi. …» con il pulsante.
- Dopo: «N verbali delle domande sono definitivi, con i loro numeri.» Nel libro hanno i numeri nell'ordine di chiusura del voto. Le domande di prova non compaiono nel libro.
- Al passo 3 la pagina del verbale della domanda ha il badge del numero («CD 2026/…») e il pulsante «Apri nel libro dei verbali».

### R21 — Verbale già redatto (Assemblea) e chiusura dell'anno
**Scopo:** registrare da fuori un verbale firmato, con la delibera sul rendiconto.
**Chi:** Segretario, poi Tesoriere.
**Passi:**
1. Verbali › scheda Assemblee › «Registra un verbale già redatto».
2. Libro «Assemblee», «Ordinaria», data di ieri, presidente «Andrea Papini», segretario «Paolo Zanetti», presenti 18, titolo «Assemblea ordinaria di prova», delibere «Approvato il rendiconto» (elenco puntato), spunta «Questa Assemblea approva il rendiconto di un anno» con anno **2025**, numero vuoto, il PDF **non firmato**. Premi «Registra nel libro».
3. Rifai con il PDF con due firme (ricarica il file).
4. Rifai ancora con lo stesso numero scritto a mano (quello appena preso).
5. Come Tesoriere apri Prima Nota › Anni contabili, se c'è un anno 2025 da chiudere.

**Risultato atteso:**
- Al passo 2 «Il file non contiene una firma digitale…».
- Al passo 3 «Verbale registrato nel libro con il n. 2026/1.»; scheda con badge «Firmato» e «Registrato da fuori», «Dati del verbale» con presidente, segretario, presenti, «Approva il rendiconto dell'anno 2025», «Delibere in breve». Nessun «PDF definitivo» (c'è solo la copia firmata).
- Al passo 4 «Il numero 2026/1 è già usato nel libro: il primo libero è 2.»
- Al passo 5 il verbale compare nell'elenco «n. 2026/1 del gg/mm/aaaa — Assemblea ordinaria di prova» solo per l'anno 2025 (il gestionale propone solo i verbali firmati con quella delibera). Non chiudere l'anno.

### R22 — Verbale della nomina nel Consiglio Direttivo
**Scopo:** collegare un nuovo membro al verbale da cui viene la nomina.
**Chi:** Presidente.
**Passi:**
1. Consiglio Direttivo › «Aggiungi un membro». Guarda i campi.
2. Annulla senza salvare (la composizione del CD di prova non va cambiata).

**Risultato atteso:**
- C'è il campo «Verbale della nomina», con i verbali definitivi o firmati dell'ultimo anno («CD 2026/1 del … — …», «Assemblee 2026/1 del … — …») e «Nessuno». Sotto «Nominato da» l'aiuto «Con il verbale scelto, si scrive da solo.»

### R23 — Registro e avvisi
**Scopo:** controllare l'Audit Log del blocco.
**Chi:** Presidente.
**Passi:**
1. Apri Audit Log e filtra per gli eventi del giorno.

**Risultato atteso:**
- Ci sono: «SEDUTA_CONVOCATA», «SEDUTA_PRESENZE», «SEDUTA_RINVIATA», «SEDUTA_ANNULLATA», «VERBALE_DEFINITIVO», «VERBALE_FIRMATO», «VERBALE_FIRMATO_SOSTITUITO», «VERBALE_RETTIFICATO», «VERBALE_RETTIFICA_FIRMATA», «VERBALE_REGISTRATO_DA_FUORI», «CONSULTAZIONE_AVVIATA», «CONSULTAZIONE_CHIUSA», «CONSULTAZIONE_RITIRATA».
- Nel dettaglio non ci sono nomi né motivi (stanno nei valori).
- La verifica della catena dà esito positivo.

### R24 — Azzeramento
**Scopo:** i dati di prova del blocco si cancellano con l'azzeramento.
**Chi:** sul server, come nel blocco P.
**Passi:**
1. `php artisan gestionale:azzera` (senza `--esegui` è una prova a vuoto) e leggi il riepilogo.

**Risultato atteso:**
- Tra le tabelle da svuotare: «Verbali», «Riunioni del CD», «Consultazioni del CD», «Documenti delle sedute e dei verbali» e le altre tabelle nuove; tra le cartelle `verbali`. Nulla resta fuori (il comando non segnala tabelle sconosciute).
