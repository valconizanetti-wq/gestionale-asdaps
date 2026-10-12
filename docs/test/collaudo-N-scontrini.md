# Collaudo N — Scontrini dal telefono con OCR

Blocco della lista di collaudo del Gestionale ASD, in un file a sé (decisione di Paolo, 12/10/2026: un documento per blocco, perché la lista unica cresceva troppo). Regole comuni (preparazione, reset, CD di prova, cosa portare quando una prova fallisce) in `test-domande-iscrizione.md`, nella stessa cartella. Copia nel progetto: `claude/collaudo-N-scontrini.md`.

## Scontrini dal telefono con OCR (v0.39.0)

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
- **v0.39.1:** dopo «Leggi lo scontrino» con una foto del telefono non deve più comparire la pagina d'errore 500 («imagesx(): … GdImage, false given», primo giro del 12/10/2026). Se la lettura non riesce compare al massimo l'avviso «Foto non letta.» e si scrive a mano.
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
