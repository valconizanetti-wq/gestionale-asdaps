# Collaudo P — Azzeramento dei dati di prova

Blocco della lista di collaudo del Gestionale ASD, in un file a sé (un documento per blocco). Regole comuni (preparazione, CD di prova, cosa portare quando una prova fallisce) in `test-domande-iscrizione.md`, nella stessa cartella. Copia nel progetto: `claude/collaudo-P-azzeramento.md`.

Versione: **v0.41.0** (14/10/2026). Dettaglio tecnico: `docs/analisi-azzeramento.md` (tavole AZ-1…AZ-3 in §3.7, decisioni in §7, realizzazione e revisione in §8). Uso sul server: `docs/deploy.md` §5.1.

**Che cosa si prova.** Il comando `php artisan gestionale:azzera` prende il posto delle pulizie a mano J12, K11, L12, M12, N12 e O14.
- **Senza `--esegui`** fa una prova a vuoto: elenca che cosa toglierebbe e non cambia niente.
- **Con `--esegui`**, dopo aver chiesto di scrivere `AZZERA`:
  1. fa una copia di sicurezza del database;
  2. svuota i dati di prova e i loro file;
  3. rimette impostazioni e scadenze pronte;
  4. fa ripartire l'Audit Log.
- **Restano:** utenti, permessi, profilo dell'ente, categorie, tipi di tessera e promemoria.
- **Si rifiuta** se trova dati veri: un anno contabile chiuso, oppure movimenti o ricevute del 2027.

**Dove si prova.** P1–P3 non cambiano niente e si fanno anche sul server. **P4 e P5 cancellano davvero i dati di prova.**
- Si provano in **Laragon**, su `asd_demo` o su una copia del database del server.
- Sul server si fa P6, una volta sola: dopo l'Assemblea (circa il 16/11/2026), quando il collaudo è finito.

**Prima di cominciare (una volta sola).**
1. Installazione della v0.41.0 (LEGGIMI): nessuna migration, nessun pacchetto nuovo, `php artisan optimize:clear` e le cache.
2. Un terminale nella cartella del gestionale: Cmder in Laragon, oppure il Terminale di cPanel in `~/gestionale-asd`.
3. Per P4 in Laragon conviene avere dati di prova di tutti i tipi: soci, una domanda, quote, movimenti, uno scontrino, tessere, una scadenza creata a mano. Quelli dei blocchi J…O vanno bene.

---

### P1 — Prova a vuoto
**Scopo:** vedere che cosa farebbe il comando, senza cambiare niente.
**Chi:** chi ha il Terminale (Paolo).
**Passi:**
1. Nell'Audit Log annota il numero dell'ultima riga e quante righe ci sono.
2. Lancia `php artisan gestionale:azzera`.
3. Ricarica l'Audit Log e un paio di pagine (Soci, Prima Nota).
**Risultato atteso:**
- Il titolo è «AZZERAMENTO DEI DATI DI PROVA — prova a vuoto (non cambia niente)», con il nome del database e l'ora.
- **«Si svuotano (righe):»** elenca solo le tabelle con dati, con nomi leggibili («Soci», «Movimenti di Prima Nota», «Audit Log», «Registro delle email», «Sessioni aperte»…). Le altre sono contate in «Altre N tabelle già vuote».
- Se ci sono scadenze create a mano, la riga «Scadenze create a mano» le conta.
- **«Si tengono:»**
  - gli utenti con i loro ruoli (es. «Paolo Zanetti – Segretario e Tesoriere»), con «(disattivato)» per chi lo è;
  - quanti promemoria ci sono;
  - le tre scadenze pronte con la data di oggi → la data dopo l'azzeramento.
- «File da cancellare: N (… kB/MB) in domande, soci, ricevute, prima-nota, scontrini, audit, tmp».
- In fondo: «Per azzerare davvero: php artisan gestionale:azzera --esegui».
- Nel gestionale **non è cambiato niente**: stesso numero di righe nell'Audit Log, stessi soci e movimenti.

### P2 — Conferma sbagliata
**Scopo:** un `--esegui` lanciato per sbaglio non fa danni.
**Chi:** Paolo.
**Passi:**
1. Lancia `php artisan gestionale:azzera --esegui`.
2. Alla domanda «Scrivi AZZERA per continuare» scrivi `azzera` (minuscolo) e premi Invio.
**Risultato atteso:**
- Prima della domanda c'è lo stesso riepilogo di P1 e l'avviso «ATTENZIONE: i dati elencati sopra si cancellano…».
- Dopo la risposta: «Niente fatto: conferma non data.».
- Nessuna copia di sicurezza in `storage/app/private/backup`, nessun dato cambiato, il gestionale resta in linea.

### P3 — Rifiuto con dati veri
**Scopo:** dopo l'apertura il comando non deve partire.
**Chi:** Paolo, **in Laragon**.
**Passi:**
1. In Prima Nota registra un movimento di prova con **data 2027** (es. 02/01/2027, 1,00 €). Se non si può perché l'anno 2027 non è aperto, usa HeidiSQL:
   ```sql
   UPDATE gl_prima_nota SET data = '2027-01-02' WHERE id = (SELECT id FROM (SELECT MAX(id) id FROM gl_prima_nota) t);
   ```
2. Lancia `php artisan gestionale:azzera`.
3. Rimetti il movimento com'era: eliminalo, oppure rimetti la data di prima con lo stesso SQL.
**Risultato atteso:**
- Al passo 2 si legge solo «Non si può azzerare: in Prima Nota ci sono movimenti dal 2027, l'anno d'avvio della contabilità: sono dati veri.». Non compare nessun riepilogo e non cambia niente.
- Lo stesso succede con `--esegui`: nessuna domanda e nessuna copia.
- Dopo il passo 3 la prova a vuoto torna a funzionare.

### P4 — Azzeramento vero
**Scopo:** controllare tutto il lavoro del comando.
**Chi:** Paolo, **in Laragon** (su `asd_demo` o su una copia del database del server).
**Passi:**
1. Entra nel gestionale in una finestra del browser e lasciala aperta.
2. Lancia `php artisan gestionale:azzera --esegui` e scrivi `AZZERA`.
3. Ricarica la finestra del browser.
4. Entra di nuovo e guarda: Soci, Domande, Rinnovi quota, Prima Nota (elenco e Anni contabili), Scontrini, Fornitori, Tesseramento, Scadenzario, Promemoria, Audit Log, Utenti, Impostazioni › Ente.
5. Nell'Audit Log apri la riga n. 1 e lancia la verifica d'integrità (pulsante della pagina, oppure `php artisan audit:verifica-integrita`).
6. Controlla la cartella `storage/app/private`.
7. Registra un socio di prova, poi cancellalo.
**Risultato atteso:**
- **Al passo 2**, nell'ordine:
  - «✔ Copia di sicurezza: storage/app/private/backup/azzeramento-AAAAMMGG-HHMMSS.sql.gz (… kB)»;
  - «✔ Gestionale in manutenzione»;
  - «✔ Tabelle svuotate: N righe in M tabelle»;
  - «✔ File cancellati: …»;
  - «✔ Impostazioni rimesse, scadenze pronte alla prossima data»;
  - «✔ Audit Log ripartito: riga n. 1 «Azzeramento dei dati di prova»»;
  - «✔ Gestionale di nuovo in linea»;
  - poi i passi successivi e l'invito a cancellare la copia dopo il controllo.
- **Passo 3:** si torna alla pagina d'accesso (le sessioni sono state svuotate).
- **Passo 4:**
  - Soci, Domande, Rinnovi, Prima Nota, Scontrini, Fornitori e Tesseramento sono vuoti;
  - Anni contabili non ha anni;
  - nel Tesseramento i tipi ci sono ancora, con i loro prezzi;
  - lo Scadenzario ha solo le tre scadenze pronte, non completate, con le date annunciate in P1;
  - i Promemoria ci sono tutti, con i testi di prima;
  - Utenti è come prima, ruoli compresi;
  - in Impostazioni › Ente le iscrizioni sono chiuse;
  - la scadenza dell'affiliazione nel profilo dell'ente è quella dello Scadenzario.
- **Passo 5:**
  - l'Audit Log ha **una riga sola**: «AZZERAMENTO_DATI_PROVA», rischio Alto, utente «sistema (gestionale:azzera)»;
  - il dettaglio riporta righe, tabelle, file e il nome della copia; nei valori «Prima» ci sono le righe tolte per tabella;
  - la verifica dà **nessuna anomalia**.
- **Avviso:** il Presidente riceve l'email dell'operazione delicata (in Laragon, se la posta va nel log: `storage/logs/laravel.log`).
- **Passo 6:** restano solo `backup` e le cartelle che il comando non conosce. Non ci sono più `domande`, `soci`, `ricevute`, `prima-nota`, `scontrini`, `audit` e `tmp`.
- **Passo 7:** la scheda del socio si crea normalmente. Dopo la cancellazione, l'Audit Log ha le sue righe dopo la n. 1.

### P5 — Ripristino dalla copia
**Scopo:** essere sicuri che la copia di sicurezza riporti indietro.
**Chi:** Paolo, **in Laragon**, subito dopo P4.
**Passi:**
1. Decomprimi `storage/app/private/backup/azzeramento-….sql.gz` (7-Zip › Estrai qui): ottieni un file `.sql`.
2. In HeidiSQL crea un database vuoto (es. `asd_ripristino`) e caricaci il file (*File › Esegui file SQL*). Con phpMyAdmin: *Importa*.
3. Confronta qualche tabella con quello che c'era prima di P4: soci, movimenti, Audit Log.
4. Cancella il database `asd_ripristino` e il file `.sql`.
**Risultato atteso:**
- L'importazione va a buon fine senza errori.
- Ci sono tutte le tabelle e le righe di prima dell'azzeramento.
- **Nota:** i file (PDF, foto, giustificativi) non sono nella copia del database. Sul server li hanno i backup giornalieri di SupportHost.

### P6 — Sul server, dopo l'Assemblea (una volta sola)
**Scopo:** azzerare i dati di prova prima di caricare quelli veri.
**Chi:** Paolo. Avvisa prima il CD: per qualche secondo il gestionale è in manutenzione e poi tutti devono rientrare.
**Passi:**
1. Lancia `php artisan gestionale:azzera` (P1) e leggi bene il riepilogo, soprattutto la lista degli utenti.
2. Lancia `php artisan gestionale:azzera --esegui` e scrivi `AZZERA`.
3. Ripeti i controlli di P4, passi 3–6.
4. Quando tutto va bene, cancella la copia di sicurezza dal File Manager (`storage/app/private/backup`): contiene i dati di prova e le password cifrate degli utenti.
5. Prosegui nell'ordine: importazione dei soci (blocco O), CD vero dalla pagina Utenti, tessere dal file del CSEN, saldi di apertura 2027.
**Risultato atteso:**
- Come P4.
- L'utente di prova del blocco O resta, disattivato (decisione D1).
- I ruoli veri del CD si sistemano dalla pagina Utenti:
  - Presidente: Andrea Papini;
  - Segretario e Tesoriere: Paolo Zanetti;
  - Consiglieri: Massimo Papini e Luigi Tascio.
