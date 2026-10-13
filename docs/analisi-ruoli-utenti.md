# Più ruoli per utente e gestione degli utenti — analisi (13/10/2026)

Analisi prima del codice (Fase 0 del piano, `piano-2027.md` §2). Tavole
UT-1…UT-4 consegnate a parte (`Tavole-utenti-importazione.pdf`, non nel
repository).

## 1. Cosa ha detto Paolo (13/10/2026)

- Paolo è **Segretario e Tesoriere** con una sola email. Lo statuto lo
  ammette: art. **15.2** («Le funzioni di Segretario e Tesoriere possono
  essere attribuite alla stessa persona»; il bootstrap citava per errore
  l'art. 16.2).
- Serve per il modello unico, quindi anche per il Dante.
- I permessi sono l'unione dei ruoli: dove un ruolo scrive e l'altro legge
  soltanto, **vince la scrittura**.
- Le email «al ruolo» (tessere al Tesoriere, promemoria, Scadenzario, Audit
  Log) vanno lette dalla nuova tabella.
- Serve la gestione degli utenti, con i test.

## 2. Situazione di oggi (letta nel codice)

- `gl_users.ruolo`: una colonna sola, chiave esterna su `gl_ruoli.codice`.
- `User::permessiSezioni()` legge `gl_ruoli_sezioni` con
  `where ruolo_codice = $this->ruolo`. Tutti i controlli d'accesso passano
  da lì (`puoAccedere`, `puoModificare`, `soloLettura`, middleware
  `permesso:`).
- **Non c'è una pagina degli utenti.** Gli utenti si creano con tinker
  (`deploy.md` §3.8 e §7) o con SQL, come il CD di prova. Il Dante ha
  Configurazioni › Utenti (crea, modifica, reimposta la password, tutto
  nell'Audit Log), ma con un ruolo solo.
- Non c'è neanche «password dimenticata»: oggi una password persa si
  rimette con tinker.
- Il voto del CD **non dipende dagli utenti**: si convocano i membri in
  carica di `gl_governance_cd` (soci), con l'email del socio. Il lavoro sui
  ruoli non lo tocca.

Usi del ruolo nel codice (31 righe fuori dai test):

| Dove | Uso | Cosa diventa |
|---|---|---|
| `User::permessiSezioni()` | permessi del ruolo | unione dei ruoli: per ogni sezione sola lettura solo se **tutti** i ruoli la danno in lettura (`MIN(sola_lettura)`) |
| `User::ruolo()` (relazione) | etichetta | `ruoli()` (`belongsToMany` sulla tabella ponte) |
| `SociController` (396, 501), `DomandeController` (101, 232), `AnniContabiliController` 163, `ImpostazioniEnteController` 311 | «solo il Presidente» | `haRuolo('presidente')` |
| viste `soci/index`, `soci/_form`, `soci/show` (2), `prima-nota/anni` | «solo il Presidente» | `haRuolo('presidente')` |
| `AuditLogController` 343 | Presidente o Revisore | `haRuolo('presidente', 'revisore')` |
| `AuditLogController` 193 | firma del certificato «(Revisore)» o «(Presidente)» | Revisore se ha quel ruolo (non può averne altri, §3.3) |
| `AuditObserver` 142 | email al Presidente | primo utente attivo con il ruolo `presidente` |
| `VerificaIntegritaAudit` 65 | email a Presidente e Revisori | utenti attivi con uno dei due ruoli, **una sola volta per persona** |
| `Promemoria\Interni` 40–41 | destinatari interni | come sopra; ordine: prima chi ha il ruolo che viene prima nella scelta |
| `TessereInScadenza` 110, `ScadenzeAssociazione` 97 | etichetta «Paolo Zanetti (Tesoriere)» | tutti i ruoli: «Paolo Zanetti (Segretario e Tesoriere)» |
| `layouts/app` 50 | ruolo sotto il nome | «Segretario · Tesoriere» |
| `prima-nota/index` 42 | «Il tuo ruolo (Segretario) vede…» | appare solo a chi non modifica la Prima Nota: «I tuoi ruoli (…)» |
| `RiskMatrix` 17, 40 | cambio di `ruolo` = rischio HIGH | il cambio dei ruoli si registra a mano nell'Audit Log, HIGH, con prima e dopo (la tabella ponte non passa dall'osservatore) |
| `DatabaseSeeder`, `UserFactory`, 13 file di test | creano utenti con `ruolo` | stato di factory `conRuoli(...)`; seeder con la tabella ponte |
| `GdprIncaricato.ruolo` | testo libero del registro GDPR | **non c'entra**, resta com'è |

## 3. Scelte proposte

### 3.1 Tabella ponte

- `gl_users_ruoli`: `user_id` (FK `gl_users`, cancellazione a cascata),
  `ruolo_codice` (FK `gl_ruoli.codice`), unica sulla coppia. Il codice e non
  l'id, come `gl_ruoli_sezioni` (`permessi.md` §4).
- La migration copia in `gl_users_ruoli` il ruolo di ogni utente.
- **Colonna `gl_users.ruolo`**: proposta di **toglierla**. Con due fonti
  (colonna e tabella) prima o poi divergono. Il `down()` la ricrea con il
  primo ruolo di ciascuno. Alternativa: tenerla come «ruolo principale» per
  l'ordine di visualizzazione. Lo sconsiglio, perché l'ordine si ricava da
  `gl_ruoli.ordine`. **Da decidere (D1).**

### 3.2 Model `User`

- `ruoli()`: `belongsToMany(Ruolo::class, 'gl_users_ruoli', 'user_id',
  'ruolo_codice', 'id', 'codice')`, ordinata per `gl_ruoli.ordine`.
- `haRuolo(string ...$codici): bool`: vero se l'utente ha almeno uno dei
  ruoli. I codici si caricano una volta per richiesta, come i permessi.
- `codiciRuoli(): array`, `etichettaRuoli(): string` («Segretario e
  Tesoriere»; con tre ruoli: «Presidente, Segretario e Tesoriere»).
- `permessiSezioni()`: una sola query con `GROUP BY` sezione e
  `MIN(sola_lettura)`. Il risultato non cambia per chi ha un ruolo solo.
- Scope `conRuolo(string ...$codici)` per le email «al ruolo»:
  `whereHas('ruoli', …)`. Un utente con due ruoli esce **una volta**.

### 3.3 Combinazioni ammesse

Due regole fisse, valide per ogni ente:

1. **Revisore da solo.** L'Organo di controllo è scelto «comunque al di
   fuori del Consiglio Direttivo» (statuto art. 20.1; per le APS lo stesso
   vale per il Codice del Terzo settore, art. 30). Chi è Revisore non può
   avere altri ruoli.
2. **Consigliere solo senza cariche.** Il ruolo Consigliere serve ai membri
   del CD senza carica (v0.39.0) e i suoi permessi sono già compresi in
   quelli di Presidente, Segretario e Tesoriere: insieme a loro non
   aggiunge niente e confonderebbe.

Tutto il resto è libero, compreso Presidente e Tesoriere (lo statuto non lo
vieta). Vincoli di gestione:

- deve restare **almeno un Presidente attivo**: non si toglie il ruolo né si
  disattiva l'ultimo;
- il Presidente non disattiva sé stesso;
- almeno un ruolo per ogni utente.

### 3.4 Pagina Impostazioni › Utenti (tavole UT-1…UT-3)

- Nuova **sezione `utenti`** in `gl_sezioni` (oggi la gestione degli utenti
  «ricade in Impostazioni», `permessi.md` §2), data al solo **Presidente**:
  contiene le email di tutti e i ruoli decidono l'accesso ai dati.
  Tesoriere e Segretario continuano a vedere Impostazioni › Ente in sola
  lettura. Voce «Utenti» in fondo al menu, sotto Impostazioni: il menu la
  mostra già solo a chi ha la sezione (`config/navigazione.php`). Il
  permesso si può allargare dal seeder senza toccare il codice.
- **Elenco** (UT-1): nome, email, ruoli come badge, stato (attivo o
  disattivato), ultimo accesso; pulsanti Modifica e Password. Su telefono
  diventa a schede.
- **Nuovo / Modifica** (UT-2): nome, email, ruoli con caselle (le
  combinazioni non ammesse spente con la spiegazione accanto), «Attivo».
- **Nessuna eliminazione**: un utente si disattiva. I suoi movimenti,
  scontrini, voci di audit e così via restano collegati a lui.
- **Audit Log**: creazione, modifica (prima e dopo, ruoli compresi) e
  password, livello HIGH per ruoli, attivazione ed email (come
  `RiskMatrix`), senza la password ovviamente.

### 3.5 Password (UT-3, da decidere: D2)

- **(a) Password provvisoria**: il Presidente la scrive alla creazione (e
  con «Password»); l'utente la deve cambiare al primo accesso. Semplice,
  ma il Presidente conosce la password e deve comunicarla a voce o per
  messaggio.
- **(b) Link per email** (consigliata): alla creazione, e con «Invia il link
  per la password», l'utente riceve un link monouso (valido 24 ore) per
  scegliersi la password. Con lo stesso meccanismo, sulla pagina di accesso
  compare «Password dimenticata?». Nessuno conosce la password altrui e
  sparisce il tinker per le password perse. Usa il servizio di Laravel
  (`Password::broker`, tabella `gl_password_reset_tokens`) con l'email
  nello stile del sito. Un poco più di lavoro: una pagina pubblica in più,
  con limite di tentativi.

### 3.6 Email «al ruolo»

- `Interni::utenti()`, `VerificaIntegritaAudit`, `AuditObserver`: scope
  `conRuolo`, un messaggio per persona. Paolo, Segretario e Tesoriere, con
  «Presidente, Tesoriere e Segretario» riceve **una** email, non due.
- Etichette dell'anteprima dei promemoria con tutti i ruoli.
- L'avviso «nessun utente ha il ruolo scelto» (v0.38.1) legge la tabella
  ponte.

### 3.7 Test

- `UserFactory`: stato `conRuoli('segretario', 'tesoriere')`; il default
  resta Segretario. I 13 file di test che passano `'ruolo' => …` si
  adeguano.
- Test nuovi (`UtentiTest`, `RuoliMultipliTest`):
  - unione dei permessi (la scrittura prevale) e utente con un ruolo solo
    invariato;
  - Segretario e Tesoriere: vede Rinnovi quota e modifica la Prima Nota;
  - una email sola con due ruoli che corrispondono alla scelta;
  - combinazioni rifiutate (Revisore con altro, Consigliere con cariche);
  - ultimo Presidente non disattivabile né privato del ruolo;
  - la pagina Utenti è chiusa a chi non è Presidente;
  - Audit Log della modifica dei ruoli con prima e dopo;
  - migration: un utente con la vecchia colonna finisce con lo stesso ruolo
    nella tabella ponte.

## 4. Effetti fuori dal codice

- `deploy.md` §3.8 e §7: il primo utente si crea ancora con tinker, ma con
  i ruoli (`$u->ruoli()->sync([...])`); poi tutto dalla pagina Utenti.
- Sul server, dopo la migration, il CD di prova resta com'è: un ruolo per
  utente. Paolo potrà dare a sé il secondo ruolo dalla pagina.
- `permessi.md` (§2, §4, §7), `ui.md`, `MANUALE-ASD.md`,
  `da-riportare-gestionale-aps.md` (il Dante ha `gs_users.ruolo` con
  Configurazioni › Utenti: alla migrazione nel modello unico i ruoli
  passano nella tabella ponte).
- Collaudo: blocco **O** in `docs/test/collaudo-O-utenti-importazione.md`.

## 5. Osservazione fuori campo

`gl_governance_cd.carica` è un testo solo per membro: per Paolo sarà
«Segretario e Tesoriere» in una riga. Il voto del CD funziona comunque
(conta il membro, non la carica). Va ripresa con Governance CD (Fase 1).

## 6. Da decidere

- **D1**: togliere `gl_users.ruolo` (consigliato) o tenerla come ruolo
  principale.
- **D2**: password provvisoria (a) o link per email con «Password
  dimenticata?» (b, consigliato).
- **D3**: pagina Utenti solo per il Presidente (consigliato) o anche in
  lettura per Segretario e Tesoriere.
