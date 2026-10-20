# Collaudo Q — Anticipi e note di rimborso

Blocco della lista di collaudo del Gestionale ASD, in un file a sé. Le regole comuni sono in `test-domande-iscrizione.md`, nella stessa cartella. Copia nel progetto: `claude/collaudo-Q-anticipi.md`.

Versione: **v0.43.0** (20/10/2026).
- Scheda: `docs/specifiche/anticipi.md`.
- Analisi: `docs/analisi-moduli-anticipi.md`, parte 2.
- Tavole AN-1…AN-4b: `Tavole-anticipi.pdf`, fuori dal repository.

**Che cosa si prova.**
- La **spesa anticipata**: un socio paga di tasca propria. Si registra dal modulo del movimento (spunta) o dal telefono («Pagato da me»).
- La pagina **Anticipi e note di rimborso**.
- La **nota di rimborso** in tre passi: nota con il PDF, copia firmata, pagamento.
- La **rinuncia al rimborso** (donazione).
- Gli annullamenti, e la chiusura dell'anno che non si blocca più.

**Prima di cominciare (una volta sola).**
1. Installa la v0.43.0 (LEGGIMI):
   - **una migration**;
   - `npm run build` (CSS cambiato);
   - `php artisan optimize:clear` e le tre cache.

   Niente seeder: la migration dà da sola il codice `DONAZIONI` a «Contributi e donazioni da terzi».
2. Il CD di prova deve avere gli utenti collegati ai soci, come dopo il collaudo O-bis:
   - Andrea Papini, Presidente;
   - Paolo Zanetti, Segretario e Tesoriere;
   - Luigi Tascio e Massimo Papini, Consiglieri.
3. Il pagamento si registra solo in un anno aperto dall'avvio della contabilità. Se sul server c'è l'anno contabile **2026 di prova** (blocco J), le date del 2026 vanno bene. Altrimenti le prove Q8–Q12 si fanno in Laragon con l'anno 2026 di prova.
4. Tieni a portata una foto o un PDF qualsiasi da usare come scontrino e come «nota firmata».

Nelle prove le date sono di ottobre 2026: usa date vicine a oggi, mai future.

---

### Q1 — Spesa anticipata dal modulo (AN-1)
**Scopo:** registrare una spesa pagata di tasca propria da un socio, senza conto.
**Chi:** Tesoriere.
**Passi:**
1. Prima Nota › «Nuovo movimento», tipo Uscita.
2. Spunta «Anticipata da un socio (pagata di tasca propria)».
3. Senza scegliere il socio e senza giustificativi premi «Salva».
4. Scegli in «Chi l'ha pagata» **Tascio Luigi · Consigliere**. Poi compila:
   - data di oggi;
   - importo 64,50;
   - categoria «Taglio erba»;
   - descrizione «Benzina per il decespugliatore»;
   - fornitore «Distributore Q8»;
   - tipo di documento «Scontrino»;
   - un giustificativo.

   Premi «Salva».
5. Rifai i passi 1–4 per «Filo per decespugliatore», 12,90 €, «Ferramenta Bianchi», sempre Tascio Luigi.

**Risultato atteso:**
- Con la spunta il riquadro **Conto sparisce**. Compaiono «Chi l'ha pagata» e l'avviso azzurro «Niente conto adesso. La spesa resta fuori dai saldi…».
- Nell'elenco dei soci ci sono prima i membri del CD, nell'ordine delle cariche: Papini Andrea · Presidente, Zanetti Paolo · Segretario e Tesoriere, poi i Consiglieri. Seguono gli altri soci attivi maggiorenni. I minorenni non ci sono.
- Al passo 3, in rosso:
  - «Scegli il socio che ha pagato la spesa.»;
  - «Allega almeno un giustificativo: la nota di rimborso dichiara che i giustificativi allegati sono gli originali.»
- Al passo 4 si apre la scheda del movimento, con:
  - l'avviso giallo «Spesa anticipata da Tascio Luigi. Da rimborsare: fuori dai saldi finché non si paga…»;
  - il badge «Da rimborsare»;
  - Conto «Da rimborsare a Tascio Luigi».

### Q2 — La spesa anticipata in Prima Nota
**Scopo:** controllare che la spesa aperta non tocchi i saldi.
**Chi:** Tesoriere.
**Passi:**
1. Annota i saldi di cassa e banca prima di Q1, oppure confrontali con quelli di adesso.
2. Apri l'elenco della Prima Nota.

**Risultato atteso:**
- Saldo cassa e saldo banca non sono cambiati.
- Il riquadro «Anticipi da rimborsare» dice 77,40 € in più e «2 spese». Cliccandolo si apre la pagina «Anticipi e note di rimborso».
- Le due righe hanno sotto la descrizione «anticipata da Tascio Luigi», Conto «—» e il badge «Da rimborsare».
- In cima c'è il pulsante «Anticipi e note».

### Q3 — «Pagato da me» dal telefono (AN-1b)
**Scopo:** registrare dal telefono uno scontrino pagato di tasca propria.
**Chi:** Consigliere Massimo Papini (dal telefono); poi Tesoriere.
**Passi:**
1. Scontrini › «Nuovo scontrino». Fotografa uno scontrino con la nota «Colla e nastro per le frecce» e premi «Leggi lo scontrino».
2. Al passo 2 controlla totale (12,40), data e fornitore. In «Pagato con» scegli **«Pagato da me»** e premi «Registra».
3. Come Tesoriere apri la scheda dello scontrino.

**Risultato atteso:**
- Al passo 2 «Pagato con» ha tre scelte, una sotto l'altra: Cassa, Banca, «Pagato da me — Di tasca mia: me li rimborsa la Compagnia».
- Sotto c'è la riga «Pagato da me» c'è solo per gli utenti del CD collegati a un socio…».
- La scheda dello scontrino ha:
  - l'avviso giallo «Pagato di tasca propria da Papini Massimo.»;
  - nel sottotitolo «pagato da papini massimo»;
  - in «Conto» la scelta «Pagato da Papini Massimo (da rimborsare)».
- In Prima Nota il movimento è «Da classificare», «Da rimborsare», senza conto.

### Q4 — La pagina «Anticipi e note di rimborso» (AN-3)
**Scopo:** vedere le spese senza nota raggruppate per socio.
**Chi:** Tesoriere.
**Passi:**
1. Prima Nota › «Anticipi e note».
2. Nella card di Papini Massimo spunta la spesa «Colla e nastro per le frecce».
3. Prova anche dal telefono.

**Risultato atteso:**
- Quattro riquadri:
  - «Da rimborsare»: 89,80 € in più, con le spese e i soci;
  - «Note da firmare» e «Note da pagare»: 0, «Nessuna»;
  - «Rimborsato nel 2026».
- «Spese senza nota» ha una card per socio, nell'ordine del CD: «Papini Massimo · Consigliere» e «Tascio Luigi · Consigliere».
- Ogni riga mostra data, descrizione con il fornitore, categoria, giustificativo (link «scontrino») e importo.
- Le spese classificate sono già spuntate; in fondo alla card c'è «Scelte: 2 spese · 77,40 €».
- Al passo 2:
  - la spesa ha il badge «Da classificare»;
  - i due pulsanti si spengono;
  - compare «Prima va scelta la categoria: una spesa «da classificare» non va in una nota. Apri la spesa e classificala.»
- Al passo 3 le tabelle diventano schede, i pulsanti vanno a tutta larghezza e la pagina non scorre di lato.

### Q5 — Correggere una spesa aperta (T4)
**Scopo:** classificare e correggere una spesa che non è ancora in una nota.
**Chi:** Tesoriere.
**Passi:**
1. Apri lo scontrino di Q3 e registra la divisione con la categoria «Acquisto materiali».
2. Apri dalla pagina degli anticipi «Filo per decespugliatore». Premi «Modifica», cambia l'importo in 13,90 e salva; poi rimetti 12,90.

**Risultato atteso:**
- Dopo la divisione la spesa di Papini Massimo resta «Da rimborsare» e anticipata da lui, con la categoria scelta.
- Nella pagina degli anticipi i pulsanti di Papini Massimo si accendono.
- La modifica del filo si salva: la spesa resta anticipata da Tascio Luigi, senza conto.

### Q6 — Passo 1: la nota in contanti e il PDF (AN-2)
**Scopo:** preparare la nota di rimborso di Tascio Luigi.
**Chi:** Tesoriere.
**Passi:**
1. Nella card di Tascio Luigi lascia spuntate le due spese e premi «Prepara la nota di rimborso».
2. Metti come data della nota un giorno **prima** della spesa più recente e premi «Genera la nota (PDF)».
3. Rimetti la data di oggi, lascia «Cassa» e premi di nuovo.
4. Premi «PDF della nota».

**Risultato atteso:**
- La pagina «Nuova nota di rimborso» mostra:
  - i passi «Nota, Firma, Pagamento»;
  - le due spese con il totale 77,40;
  - a destra il numero proposto (circa RIMB-2026-001) e «Visto: il Tesoriere (Paolo Zanetti)».
- Al passo 2: «La data della nota non può venire prima della spesa più recente (…)».
- Al passo 3 si apre la scheda «Nota di rimborso RIMB-2026-001» con:
  - il badge «Emessa · da firmare»;
  - il messaggio «Nota RIMB-2026-001 generata: stampala e falla firmare, poi carica la copia firmata.»;
  - i passi «Nota · …» fatto e «Firma» corrente.
- Il PDF è sulla carta intestata e contiene:
  - Socio «Tascio Luigi · C.F. …», Carica «Consigliere», Rimborso «In contanti, dalla cassa della Compagnia»;
  - le due spese con la categoria tra parentesi;
  - «Totale da rimborsare 77,40» e «Importo: € 77,40 (settantasette/40)»;
  - la dichiarazione del socio;
  - le firme «Il socio Luigi Tascio» e «Visto, il Tesoriere Paolo Zanetti»;
  - il riquadro **«Per quietanza.** Ricevuti in contanti € 77,40 il ___» con la firma del socio.
- Le due spese in Prima Nota dicono «… · RIMB-2026-001» e sono ancora fuori dai saldi.

### Q7 — Nella nota la spesa è bloccata
**Scopo:** controllare che una spesa in una nota non si cambi nei dati che contano.
**Chi:** Tesoriere.
**Passi:**
1. Apri «Benzina per il decespugliatore» in Prima Nota.
2. Premi «Modifica»: cambia la descrizione in «Benzina (miscela)» e salva.
3. Torna sulla scheda.

**Risultato atteso:**
- Nella scheda:
  - l'avviso «Nella nota RIMB-2026-001 (emessa · da firmare)…»;
  - **nessun** pulsante «Elimina».
- Nella modifica:
  - l'avviso «Movimento della nota RIMB-2026-001. Importo, data e conto restano quelli della nota di rimborso…»;
  - data, importo e tipo spenti;
  - niente casella dell'anticipo.
- La descrizione nuova si salva. Il PDF della nota resta con la descrizione vecchia: è la fotografia di quando è stata generata.

### Q8 — Passo 2 (firma) e passo 3 (pagamento) (AN-4)
**Scopo:** chiudere la nota.
**Chi:** Tesoriere; poi controlla l'email il Presidente.
**Passi:**
1. Nella scheda della nota, al passo «2 · Firma», carica un file come copia firmata.
2. Al passo «3 · Pagamento» metti una data **prima** della data della nota e premi «Registra il pagamento».
3. Rimetti la data di oggi e premi di nuovo.
4. Guarda i saldi della Prima Nota e la casella email del Presidente.

**Risultato atteso:**
- Dopo il passo 1:
  - «Copia firmata caricata: ora si registra il pagamento.»;
  - il badge «Firmata · da pagare»;
  - a destra «Nota firmata: RIMB-2026-001-firmata.pdf» (link che apre il file);
  - c'è «Sostituisci la copia firmata».
- «Pagato da» ha **Cassa** scelta e Banca spenta: il conto lo decide la nota.
- Al passo 2: «La data non può venire prima della nota (…)».
- Al passo 3:
  - il messaggio «Pagamento registrato: le spese sono in Prima Nota dalla cassa con la data del …»;
  - il badge «Pagata il … · Cassa».
- In Prima Nota:
  - le due spese hanno la data del pagamento, Conto «Cassa», niente «Da rimborsare»;
  - la scheda dice «Data della spesa: …»;
  - il saldo cassa è sceso di 77,40 €.
- Il Presidente riceve l'email dell'operazione delicata, con «Nota RIMB-2026-001 pagata il …». Il Tesoriere non la riceve.
- Nella pagina degli anticipi: «Rimborsato nel 2026» 77,40 € e la nota «Pagata il … · Cassa».

### Q9 — Annullare il pagamento
**Scopo:** correggere un pagamento registrato per errore.
**Chi:** Tesoriere.
**Passi:**
1. Nella scheda della nota pagata premi «Annulla il pagamento».
2. Scrivi «errore» come motivo e conferma.
3. Scrivi «Pagamento registrato per errore, i soldi non sono ancora usciti» e conferma.
4. Poi registra di nuovo il pagamento con la data di oggi.

**Risultato atteso:**
- Al passo 2 la finestra resta aperta: «Il motivo deve avere almeno 15 caratteri.»
- Dopo il passo 3:
  - «Pagamento annullato: le spese sono di nuovo fuori dai saldi e la nota torna «firmata».»;
  - le spese tornano alla data della spesa, senza conto, «Da rimborsare»;
  - il saldo cassa torna com'era.
- Il Presidente riceve l'email (rischio alto).
- Al passo 4 tutto torna come in Q8.

### Q10 — Nota con il bonifico
**Scopo:** rimborso con bonifico, IBAN controllato, conto fissato.
**Chi:** Tesoriere.
**Passi:**
1. Registra come in Q1 una spesa anticipata da Tascio Luigi: «Cartucce per la stampante», 37,40 €, categoria «Spese ufficio».
2. «Prepara la nota di rimborso», scegli **Banca**, IBAN «IT60 X054 2811 1010 0000 0123 457» (ultima cifra sbagliata), «Genera la nota (PDF)».
3. Correggi l'IBAN in «IT60 X054 2811 1010 0000 0123 456» e genera.
4. Apri il PDF, carica la copia firmata e registra il pagamento.

**Risultato atteso:**
- Con «Banca» compare il campo «IBAN del socio». Al passo 2: «L'IBAN non torna: controlla lettere e cifre (per l'Italia 27 caratteri, IT…).»
- Il PDF ha:
  - Rimborso «Con bonifico bancario»;
  - IBAN a gruppi di quattro, «intestato a Luigi Tascio»;
  - Causale «Rimborso spese RIMB-2026-002»;
  - **niente** riquadro «Per quietanza»;
  - in fondo «Dopo il pagamento: «Pagata il __ con bonifico», a cura del Tesoriere».
- Al passo 3 di AN-4 è scelta Banca, con Cassa spenta. Dopo il pagamento le spese sono in Banca.

### Q11 — Annullare una nota: il numero non torna
**Scopo:** annullare una nota non pagata.
**Chi:** Tesoriere.
**Passi:**
1. Registra due spese anticipate da Papini Andrea, 20,00 € e 45,00 €.
2. Prepara una nota con la sola spesa da 20,00 € (RIMB-2026-003) e carica la copia firmata.
3. Premi «Annulla la nota» e scrivi il motivo «Spesa scritta due volte».
4. In Prima Nota elimina la spesa da 20,00 €.
5. Prepara una nota con la spesa da 45,00 €.

**Risultato atteso:**
- «Annulla la nota» c'è anche quando la nota è firmata.
- Dopo il passo 3:
  - «RIMB-2026-003 annullata: le spese sono tornate tra quelle senza nota.»;
  - la fascia «Annullata il … da …. Motivo: Spesa scritta due volte…»;
  - il PDF ha in cima la scritta rossa **ANNULLATA il …** e in fondo il motivo.
- Al passo 4 la spesa, tornata libera, si elimina.
- La nota nuova è **RIMB-2026-004**: il 003 non si riusa.
- Nell'elenco delle note c'è RIMB-2026-003 «Annullata» con il motivo sotto.

### Q12 — Nota del Tesoriere: visto del Presidente (E2)
**Scopo:** chi ha anticipato non mette il visto a sé stesso.
**Chi:** Tesoriere.
**Passi:**
1. Registra una spesa anticipata da **Zanetti Paolo**: «Toner per la stampante», 38,90 €.
2. Premi «Prepara la nota di rimborso» nella sua card (non generarla ancora).

**Risultato atteso:**
- A destra: «Visto: il Presidente (Andrea Papini).»
- Generando la nota, il PDF ha «Visto, il Presidente Andrea Papini». Per Q13 annulla la nota con un motivo, oppure usa un'altra spesa.

### Q13 — Rinuncia al rimborso (AN-4b)
**Scopo:** il socio dona la spesa invece di farsela rimborsare.
**Chi:** Tesoriere.
**Passi:**
1. Nella card di Zanetti Paolo spunta il toner e premi «Rinuncia al rimborso (donazione)».
2. Premi «Genera la dichiarazione (PDF)» e apri il PDF.
3. Carica la copia firmata.
4. Al passo «3 · Registra la donazione» lascia la data di oggi e premi «Registra la donazione».
5. Guarda la Prima Nota.
6. Premi «Annulla la registrazione» con un motivo, poi registra di nuovo.

**Risultato atteso:**
- Il passo 1 non chiede la modalità.
- Il PDF:
  - si intitola «Dichiarazione di rinuncia al rimborso»;
  - ha Rimborso «Nessuno: l'importo è donato alla Compagnia» e «Totale donato»;
  - dice «dichiara di rinunciare al loro rimborso e di donare la somma indicata alla Compagnia»;
  - non ha la quietanza.
- La rinuncia ha lo stesso numero delle note (una sola serie).
- Al passo 4 la tabella mostra «Uscita · Toner per la stampante» (− 38,90) e «Entrata · Donazione di Paolo Zanetti (rinuncia RIMB-…)» (+ 38,90).
- Dopo il passo 4:
  - il badge «Registrata il …»;
  - in Prima Nota l'uscita del toner in Cassa e l'entrata «Contributi e donazioni da terzi» in Cassa, nella stessa data;
  - il **saldo cassa non cambia**.
- L'entrata della donazione non si elimina da sola: «… si toglie solo dopo aver annullato la nota…».
- Al passo 6 l'entrata sparisce, il toner torna «Da rimborsare» e il saldo resta uguale.

### Q14 — Scontrino in una nota
**Scopo:** uno scontrino «Pagato da me» in una nota non si cambia.
**Chi:** Tesoriere.
**Passi:**
1. Metti in una nota la spesa dello scontrino di Q3 (Papini Massimo).
2. Apri la scheda dello scontrino, cambia il totale e premi «Salva i dati».
3. Prova «Elimina lo scontrino».

**Risultato atteso:**
- La scheda ha l'avviso «Scontrino nella nota di rimborso RIMB-…».
- Al passo 2 il totale non cambia: «Lo scontrino è nella nota …: totale, data e conto si cambiano dopo aver annullato la nota.»
- Al passo 3 lo scontrino resta: «Lo scontrino è nella nota …: si elimina dopo aver annullato la nota.»
- Nel PDF della nota la spesa ha Documento «scontrino SC2026-…».

### Q15 — Sola lettura
**Scopo:** chi consulta vede tutto ma non registra.
**Chi:** Revisore; Consigliere.
**Passi:**
1. Come Revisore apri «Anticipi e note di rimborso», una nota, il suo PDF e la copia firmata.
2. Come Consigliere apri la stessa pagina.

**Risultato atteso:**
- Si vedono riquadri, spese e note, senza caselle e senza pulsanti «Prepara…», «Rinuncia…», «Spesa anticipata».
- Nella scheda della nota ci sono il PDF e la copia firmata, ma niente «Annulla» né moduli dei passi.
- Al passo 3 si legge «Il pagamento lo registrano il Presidente o il Tesoriere.»

### Q16 — Chiusura dell'anno con anticipi aperti (A2)
**Scopo:** gli anticipi aperti non bloccano la chiusura.
**Chi:** Tesoriere.
**Quando:** la lista di chiusura compare solo per un anno finito, quindi dal 2027 (per il 2026 di prova), o in Laragon. La prova automatica `test_chiusura_non_bloccata_dagli_anticipi` lo controlla già.
**Passi:**
1. Con una spesa anticipata aperta dell'anno da chiudere, apri Prima Nota › «Anni e saldi».

**Risultato atteso:**
- La riga «Anticipi da rimborsare» ha l'icona blu, non il triangolo giallo. Dice «1 spesa anticipata ancora da rimborsare (… €): non bloccano la chiusura, entrano nell'anno del rimborso e nel rendiconto sono debiti verso i soci».
- Se il resto è a posto, «Chiudi l'anno» è attivo.

### Q17 — Azzeramento
**Scopo:** le note di prova si cancellano con il resto.
**Quando:** con il blocco P (P4–P6).
**Risultato atteso:**
- Nel riepilogo del comando `gestionale:azzera` compare «Note di rimborso».
- Dopo l'azzeramento:
  - la pagina degli anticipi è vuota;
  - i PDF e le copie firmate in `storage/app/private/prima-nota/rimborsi/` non ci sono più.

---

## Esiti del collaudo
(da compilare)
