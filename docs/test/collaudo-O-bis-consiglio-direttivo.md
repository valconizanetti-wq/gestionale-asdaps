# Collaudo O-bis — Consiglio Direttivo, utenti e correzioni del collaudo O

Blocco della lista di collaudo del Gestionale ASD, in un file a sé. Regole comuni in `test-domande-iscrizione.md`, nella stessa cartella. Copia nel progetto: `claude/collaudo-O-bis-consiglio-direttivo.md`.

Versione: **v0.42.0** (17/10/2026). Dettaglio: `docs/analisi-collaudo-O.md` (§10–§16). Tavole CD-1…CD-4, UT-8, UT-9, ER-1 in `Tavole-consiglio-direttivo.pdf` (fuori dal repository).

**Che cosa si prova.**
- La pagina **Consiglio Direttivo** (menu Governance): si scelgono i soci con la carica, e da lì nascono gli utenti del CD.
- La pagina **Utenti**:
  - solo gli esterni (Revisore, Amministratore), con cognome e nome separati;
  - l'Amministratore di sistema in più su un utente del CD.
- Le correzioni piccole:
  - link della password già usato (O3.4);
  - occhio su «Ripeti la password» (O3.5);
  - utente disattivato (O9.1);
  - errori dei campi (ER-1);
  - codice fiscale nella scheda del socio (O-extra.1).

**Prima di cominciare (una volta sola).**
1. Installazione della v0.42.0 (LEGGIMI): **una migration**, `npm run build` (CSS e JS cambiati), `php artisan optimize:clear` e le tre cache.
   - La migration collega da sola ogni utente al socio del CD di prova che ha la stessa email.
   - Niente seeder.
2. Per le prove servono:
   - almeno tre soci attivi **con l'email**;
   - un socio attivo **senza** email.

   Usa quelli di prova o `soci-buoni.xlsx`. Se un'email è anche di un utente, l'utente si collega a quel socio.
3. Lascia attivo un solo utente con il ruolo di Revisore.

---

### OB1 — La pagina Consiglio Direttivo
**Scopo:** vedere la pagina nuova e il CD di prova.
**Chi:** Presidente; poi Consigliere.
**Passi:**
1. Come Presidente apri Governance › «Consiglio Direttivo».
2. Come Consigliere apri la stessa pagina.

**Risultato atteso:**
- Titolo «Consiglio Direttivo», pulsante «+ Aggiungi un membro», tabella «In carica» con socio (e la sua email), carica, «Dal» e «Utente del gestionale» («Attivo · ultimo accesso …», «Attivo · mai entrato», «Disattivato» o «Nessun utente»).
- I membri di prova inseriti con SQL ci sono, con la carica. Chi ha la stessa email di un utente risulta collegato.
- Sulla riga del Presidente niente icone. Sulle altre, la matita («Cambia carica») e l'icona d'uscita («Togli dal CD»).
- Con meno di tre membri: avviso «Il CD ha N membri. Lo statuto ne chiede da tre a cinque…».
- Consigliere: «Sola lettura.», niente pulsanti né icone.
- Da telefono la tabella diventa una scheda per membro.

### OB2 — Aggiungere un membro
**Scopo:** far nascere l'utente dal CD.
**Chi:** Presidente.
**Passi:**
1. Se il CD ha già cinque membri, prima togline uno (OB4).
2. «+ Aggiungi un membro»: scegli un socio senza utente, carica fra quelle proposte (es. Consigliere), «In carica dal» oggi, «Nominato da» `Prova collaudo O-bis`, «Mandagli il link…» spuntato. Premi «Aggiungi al CD».
3. Ripeti con il socio **senza email**.

**Risultato atteso:**
- Nella finestra:
  - il menu «Socio» elenca solo i soci attivi non ancora nel CD, in ordine di cognome;
  - il menu «Carica» propone solo le cariche libere;
  - sotto compare «Email del socio: … Diventerà utente con il ruolo …».
- Passo 2: messaggio «Cognome Nome è nel Consiglio Direttivo come Consigliere: creato l'utente e inviato il link per la password.».
  - Arriva l'email «Gestionale: scegli la tua password».
  - In Utenti il nuovo utente ha cognome e nome della scheda e «Da: Consiglio Direttivo».
- Passo 3:
  - la finestra si riapre con l'errore «… non ha l'email nella scheda: aggiungila prima…»;
  - già prima di premere, sotto il menu: «Il socio non ha l'email nella scheda: aggiungila prima.».
- Gli altri del CD che vedono la pagina ricevono l'avviso dell'operazione. Nell'Audit Log c'è «CD_MEMBRO_AGGIUNTO».

### OB3 — Cambiare carica e passare la Presidenza
**Scopo:** il passaggio di una carica in un'unica operazione.
**Chi:** Presidente; poi Segretario.
**Passi:**
1. Come Segretario apri la matita su un Consigliere e guarda il menu «Nuova carica».
2. Come Segretario apri la matita sulla tua riga.
3. Come Presidente apri la matita su un Consigliere e scegli **Presidente**.
4. Torna a «Consigliere» e scegli **Vicepresidente** (se libero), «Dal» oggi, motivazione `Prova del collaudo O-bis`, «Cambia carica».

**Risultato atteso:**
- Passo 1: niente «Presidente» fra le cariche: la carica di Presidente la gestiscono solo il Presidente e l'Amministratore.
- Passo 2: sulla tua riga niente matita. La tua carica la cambia il Presidente.
- Passo 3:
  - avviso giallo «La carica di Presidente è di …. Si passa in un'unica operazione.»;
  - campo «Chi la lascia resta nel CD come» (Consigliere / Esce dal CD).
  - **Non confermare**: chiudi con Annulla. Il passaggio vero si prova in Laragon (OB8).
- Passo 4:
  - messaggio «… ora è Vicepresidente.»;
  - in Utenti il suo ruolo è «Vicepresidente»;
  - entrando con quell'utente vede Soci, Domande, Impostazioni, Audit Log e Consiglio Direttivo in sola lettura, più gli Scontrini.
- Audit Log: «CD_CARICA».

### OB4 — Togliere un membro
**Scopo:** l'uscita dal CD.
**Chi:** Presidente.
**Passi:**
1. Icona d'uscita sul membro aggiunto in OB2: data oggi, motivo «Dimissioni», nota a piacere, «Togli dal CD».
2. Come Presidente prova a registrare la cessazione di un socio che è ancora nel CD (scheda del socio › «Registra cessazione»).

**Risultato atteso:**
- La finestra dice «Il suo utente perde il ruolo di Consigliere e **si disattiva**. Resta nello storico del CD.».
- Dopo: il membro non è più fra quelli «In carica» e compare in «Usciti dal CD» («… · Consigliere dal … al … · dimissioni»). In Utenti il suo utente è «Disattivato», con «Da: uscito dal CD».
- Passo 2: «Il socio ricopre la carica di … nel Consiglio Direttivo: toglilo prima dal CD (pagina Consiglio Direttivo), poi registra la cessazione.».
- Sulla riga del Presidente l'icona d'uscita non c'è.

### OB5 — Pagina Utenti: esterni e Amministratore
**Scopo:** la pagina Utenti rivista.
**Chi:** Presidente.
**Passi:**
1. Apri «Utenti».
2. «+ Nuovo utente esterno»: cognome `Prova`, nome `Revisore`, una tua email (Gmail con il «+», es. `tuonome+revisore@gmail.com`), ruolo **Revisore**, «Crea l'utente».
3. Matita sul tuo utente (Segretario e Tesoriere): spunta «Amministratore di sistema», «Salva».
4. Matita sull'utente del Presidente: guarda la casella Amministratore.

**Risultato atteso:**
- Elenco:
  - in ordine di cognome;
  - colonna «Da»: «Consiglio Direttivo», «esterno», «uscito dal CD», oppure «Non collegato al CD» per un utente con ruoli del CD che non è nel CD;
  - in alto, «Consiglio Direttivo» e «+ Nuovo utente esterno».
- Passo 2:
  - se c'è già un Revisore attivo: «Non salvato. Il ruolo di Revisore è già di …: uno solo fra gli utenti attivi.» (disattiva l'altro e riprova);
  - altrimenti l'utente nasce con il link.
- Passo 3:
  - se lo fai come Presidente: salvato, con i ruoli «Segretario, Tesoriere e Amministratore»;
  - se lo fai con il tuo utente da Segretario: «I tuoi ruoli li può cambiare solo il Presidente.».
- Passo 4: la casella «Amministratore di sistema» è spenta, con «Non con il Presidente.».
- Nel modulo di un utente del CD c'è il riquadro del socio («Cognome e nome dalla scheda del socio · apri la scheda»). I ruoli si vedono come «Ruoli dal Consiglio Direttivo», con il link alla pagina.

### OB6 — Accesso, link e password
**Scopo:** O3.4, O3.5, O9.1.
**Chi:** nessuno collegato (finestra anonima).
**Passi:**
1. Riapri il link di una password già scelta (es. quello di OB2, dopo averlo usato).
2. Apri un link nuovo («Password dimenticata?») e prova l'occhio su «Nuova password» e su «Ripeti la password».
3. Entra con l'utente disattivato in OB4: prima con una password sbagliata, poi con quella giusta.

**Risultato atteso:**
- Passo 1: **subito**, senza scrivere niente:
  - riquadro rosso «Il link non è più valido (scaduto o già usato): chiedine un altro con «Password dimenticata?».»;
  - pulsante «Chiedi un altro link» e niente modulo.
- Passo 2: ogni campo ha il suo occhio e mostra o nasconde solo sé stesso.
- Passo 3:
  - con la password sbagliata: «Email o password non corrette.»;
  - con quella giusta: «Utente non attivo nel gestionale: rivolgiti al Presidente.»;
  - in tutti e due i casi non si entra.

### OB7 — Errori dei campi
**Scopo:** ER-1 e O-extra.1.
**Chi:** Presidente o Segretario.
**Passi:**
1. Soci › «Nuovo socio»: compila solo il nome e un codice fiscale sbagliato (cambia l'ultima lettera di uno giusto), premi «Salva».
2. Nel riquadro in cima premi il link «Cognome».
3. Correggi l'ultima lettera del codice fiscale.

**Risultato atteso:**
- Passo 1:
  - in cima «Controlla i dati. N campi da correggere:», con l'elenco numerato dei campi (nome del campo sottolineato, poi il messaggio);
  - la pagina scorre al primo campo sbagliato e ci mette il cursore;
  - ogni errore è sotto il suo campo, in una striscia rosa con la «!»; etichetta del campo rossa, bordo più spesso;
  - l'errore del codice fiscale compare **una volta sola**.
- Passo 2: la pagina va al campo Cognome e ci mette il cursore.
- Passo 3:
  - l'errore del codice fiscale e il bordo rosso spariscono **appena modifichi**;
  - con il codice giusto si compilano data, luogo di nascita e sesso;
  - non serve ricaricare la pagina.
- Da telefono lo stesso, con l'elenco che va a capo.

### OB8 — Passaggio della Presidenza (Laragon)
**Scopo:** provare il passaggio vero senza toccare il server.
**Chi:** Presidente in Laragon (`asd_demo`).
**Passi:**
1. Matita su un Consigliere, «Nuova carica» Presidente, «Chi la lascia resta nel CD come» Consigliere, motivazione, «Cambia carica».
2. Esci ed entra con il nuovo Presidente; rimetti la carica al primo con lo stesso passaggio.

**Risultato atteso:**
- Dopo il passo 1 il vecchio Presidente è Consigliere: resta sulla pagina del CD in sola lettura e non vede più «Aggiungi un membro».
- In Utenti i ruoli sono scambiati. Nell'Audit Log c'è «CD_CARICA» con «Presidente → Consigliere» per il membro che lascia.
- Se al passo 1 avessi scelto «Esce dal CD» per te stesso: torni alla pagina d'accesso con il messaggio «… Il tuo utente non ha più ruoli ed è stato disattivato.».

### OB9 — Azzeramento (dopo l'Assemblea)
Con `php artisan gestionale:azzera` (blocco P), quando arriva il momento:
- si svuota anche il CD di prova; gli utenti restano, scollegati dai soci;
- poi l'ordine di `deploy.md` §5.1: soci veri, CD dalla pagina, Amministratore sul tuo utente.
