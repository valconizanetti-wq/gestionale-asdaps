# Analisi delle osservazioni del collaudo O (16/10/2026)

Osservazioni di Paolo sul blocco O (`docs/test/collaudo-O-utenti-importazione.md`, sezione «Esiti del collaudo»). Per ognuna: com'è oggi il codice, la proposta e le scelte da fare. Nessun codice prima delle risposte (regola di Paolo). Versione prevista: **v0.42.0**, con una migration.

Riferimenti dello statuto (testo pubblicato 1.0):
- art. 15.1: CD da tre a cinque membri, compreso il Presidente;
- art. 15.2: il CD nomina al proprio interno **Vicepresidente**, Segretario e Tesoriere; Segretario e Tesoriere possono essere la stessa persona;
- art. 20.1: Organo di controllo **monocratico o collegiale di tre membri**, anche **non soci**, comunque fuori dal CD.

---

## 1. Avvisi non arrivati al Segretario (O10.1, O12.1)

**Com'è oggi.** I due casi hanno la stessa strada:
- l'eliminazione della scheda (`SOCIO_DELETED`) e l'importazione (`IMPORT_SOCI`) scrivono una riga HIGH nell'Audit Log;
- `AvvisiOperazioni` manda l'email a Presidente, Segretario e Tesoriere attivi che vedono i Soci, escluso chi ha fatto l'operazione;
- in locale il test dell'eliminazione passa, e il Segretario riceve l'avviso.

Paolo non segnala problemi su O8 e O9, dove l'avviso andava al Presidente. Il difetto quindi colpisce l'avviso **verso il Segretario**, quando l'operazione la fa il Presidente.

**Cause possibili sul server**, da controllare prima del codice:
1. L'utente con il ruolo di Segretario coincide con quello del Presidente. Per esempio, in O5 i due ruoli sono stati dati all'utente con cui fai il collaudo come Presidente. Chi fa l'operazione è escluso, quindi non riceve nulla. È coerente con quello che hai visto.
2. Il Segretario non è attivo, oppure non ha l'email.
3. L'invio è fallito: la riga «fallita» è in `gl_email_log`, l'errore in `storage/logs/laravel.log`.

**Controllo da fare in phpMyAdmin:**
```sql
SELECT u.id, u.name, u.email, u.attivo, GROUP_CONCAT(r.ruolo_codice) AS ruoli
FROM gl_users u LEFT JOIN gl_users_ruoli r ON r.user_id = u.id
GROUP BY u.id ORDER BY u.id;

SELECT created_at, destinatario_email, esito, errore
FROM gl_email_log WHERE tipo_email = 'avviso_operazione'
ORDER BY id DESC LIMIT 10;
```

**Se è la causa 1**, il codice è corretto: si ripete O10 con due utenti distinti. Con la regola nuova del §3 (un solo Presidente, Segretario e Tesoriere su un'altra persona) il caso non potrà più capitare.

**Se è la causa 3**, è un difetto della posta: lo si corregge e si aggiunge un test.

## 2. Nome e cognome separati (O2.1)

**Com'è oggi.**
- Utenti: un solo campo «Nome e cognome» (`gl_users.name`).
- Soci: nome e cognome **sono già separati**, nella scheda, nella domanda online e nell'importazione.
- Un campo unico resta solo per il **genitore o tutore**: `gl_soci.tutore_nome`, `gl_domande.genitore_nome` e la colonna del genitore nel modello Excel.

**Proposta.**
- Utenti: due campi, `nome` e `cognome`. La migration divide i nomi già presenti alla prima parola: «Prova Consigliere» → nome «Prova», cognome «Consigliere». La divisione si controlla nella pagina Utenti.
- Il nome intero resta per email, elenchi e Audit Log, ricavato dai due campi: l'ordine dell'elenco diventa cognome e nome.
- Per gli utenti collegati a un socio (§4) nome e cognome vengono dalla scheda del socio.
- Genitore o tutore: due campi anche lì, in scheda, domanda, ricevute, verbale e modello Excel (colonne «Cognome genitore» e «Nome genitore»). La migration divide i dati presenti come per gli utenti.

**Scelta S1.** «Anche in soci» intendevi il genitore o tutore, visto che il socio li ha già separati? Se sì, si fa anche il genitore nella v0.42.0. È la parte più estesa: tocca domanda online, ricevuta, verbale e importazione.

## 3. Un ruolo per persona, ruoli unici (O3.1, O3.2, O8.1)

**Com'è oggi.**
- Più ruoli per utente, con questi limiti: il Revisore sta da solo; il Consigliere non si unisce alle cariche.
- Presidente con Segretario, o Presidente con Tesoriere, oggi sono ammessi.
- Nessun limite al numero di persone con lo stesso ruolo. Resta solo «almeno un Presidente attivo».

**Proposta, che segue lo statuto.**
- **Un solo ruolo per utente.** L'unica coppia ammessa è Segretario + Tesoriere (art. 15.2). Nel modulo, al posto delle caselle, si sceglie un ruolo, con la casella «anche Tesoriere» accanto a Segretario e «anche Segretario» accanto a Tesoriere.
- **Ruoli unici fra gli utenti attivi:** Presidente, Segretario, Tesoriere. Consigliere ripetibile. Revisore fino a **tre** (art. 20.1, organo collegiale).
- Un ruolo unico assegnato a un secondo utente dà un errore che dice chi lo ha già: «Il ruolo di Presidente è già di Andrea Papini: toglilo prima a lui, oppure usa «Passa il ruolo».».
- Gli utenti disattivati non contano.
- **Passaggio del ruolo.** Con un solo Presidente e la regola «deve restarne almeno uno», cambiare Presidente diventa impossibile: non lo si può togliere al vecchio prima di darlo al nuovo. Proposta: sull'utente che riceve il ruolo, «Passa il ruolo» (solo il Presidente, per i ruoli unici). Toglie il ruolo all'altro e lo dà a questo in un'unica operazione, con la conferma e una riga di Audit Log. Il vecchio Presidente resta utente come Consigliere, o con il ruolo che si sceglie.
- Il Segretario continua a non toccare il ruolo di Presidente.

**Scelta S2.** Si aggiunge il ruolo di **Vicepresidente**, previsto dallo statuto (art. 15.2) e oggi assente? Proposta: unico, membro del CD, con i permessi del Consigliere più quelli del Presidente quando lo sostituisce. Per ora solo quelli del Consigliere: la sostituzione si gestirà con il modulo Governance CD (Fase 1).

**Scelta S3.** Revisore fino a tre (organo collegiale), oppure uno solo?

## 4. Utenti del CD scelti fra i soci (O3.3)

**Com'è oggi.** L'utente è indipendente dai soci: nome ed email si scrivono a mano.

**Proposta.**
- Colonna nuova `gl_users.socio_id`.
- Per Presidente, Vicepresidente, Segretario, Tesoriere e Consigliere il socio è **obbligatorio** e deve essere un socio attivo, non cessato né decaduto.
- Nel modulo si cerca il socio per cognome o codice fiscale. Nome, cognome ed email si prendono dalla scheda. L'email si può cambiare per l'utente: è quella con cui entra.
- Revisore (anche non socio, art. 20.1) e amministratore di sistema (§5): socio facoltativo, nome e cognome scritti a mano.
- Un socio ha al massimo un utente.
- **Se il socio cessa o decade**, l'utente si disattiva da solo, con una riga di Audit Log e l'avviso. Si collega alla cessazione nella scheda e alle decadenze del 1° maggio.
- **Utenti già presenti** sul server (di prova, non soci): la migration li lascia senza socio. Il collegamento serve solo quando si salva un utente con un ruolo del CD. Non blocca l'accesso, ma la pagina Utenti mostra «non collegato a un socio».
- **Ordine dopo l'Assemblea** (piano §2, punto 5): azzeramento → importazione dei soci veri → collegamento di ogni utente del CD al suo socio, con i ruoli veri. È l'ordine già previsto; diventa obbligatorio.

## 5. Amministratore di sistema (O2.2)

**Com'è oggi.** Non esiste. Le operazioni tecniche (comandi, cache, database) si fanno dal Terminale di cPanel, fuori dal gestionale.

**Proposta di partenza**, da confermare:
- ruolo `sysadmin` «Amministratore di sistema», unico, non membro del CD, socio facoltativo;
- vede **tutte** le sezioni in **sola lettura**, per l'assistenza, e la pagina Utenti in scrittura;
- sulla pagina Utenti può dare e togliere tutti i ruoli, compreso Presidente. È il rimedio quando il Presidente perde l'accesso, come è successo il 16/10/2026;
- ogni sua modifica degli utenti avvisa il Presidente;
- non riceve promemoria, avvisi delle operazioni né voti del CD; non compare nei verbali;
- l'informativa privacy lo copre già come incaricato tecnico. Lettera d'incarico con il modulo GDPR.

**Scelta S4.**
- (a) Come sopra: legge tutto, scrive solo Utenti.
- (b) Scrive dappertutto, come superutente.
- (c) Solo Utenti e Impostazioni, senza vedere i dati dei soci.

Per la privacy suggerisco (a) o (c): un superutente che scrive tutto non serve a un'associazione e tocca dati che il suo ruolo non richiede.

**Scelta S5.** Chi sarà? Se sei tu, avrai due utenti con due email diverse: Segretario e Tesoriere con il tuo socio, e Amministratore. Oppure si ammette la coppia Segretario + Tesoriere + Amministratore sullo stesso utente.

## 6. Accesso di un utente disattivato (O9.1)

**Com'è oggi.** «Email o password non corrette.» per tutti. È voluto: chi tenta a caso non scopre quali email sono di un utente.

**Proposta.** «Utente non attivo nel gestionale: rivolgiti al Presidente.» compare **solo se email e password sono giuste**. Chi non conosce la password continua a vedere il messaggio generico, quindi non si rivela nulla a un estraneo. Il tentativo non apre la sessione.

**Scelta S6.** Va bene così, oppure il messaggio va mostrato sempre, anche con la password sbagliata?

## 7. Link della password già usato (O3.4)

**Com'è oggi.** La pagina del link (`PasswordController::scegli`) mostra sempre il modulo. Il token si controlla solo al salvataggio.

**Proposta, senza scelte.** All'apertura la pagina controlla il link (token ed email). Se è scaduto o già usato, mostra subito «Il link non è più valido (scaduto o già usato): chiedine un altro con «Password dimenticata?».», con il pulsante per la pagina e senza modulo. Il controllo al salvataggio resta.

## 8. Occhio anche su «Ripeti la password» (O3.5)

**Proposta, senza scelte.** Stesso pulsante del campo «Nuova password», nella pagina del link. Si controlla anche ogni altra pagina con due campi password.

---

## 9. Riepilogo delle scelte

| | Domanda | Proposta |
|---|---|---|
| S1 | «Anche in soci» = genitore o tutore? | Sì, nella v0.42.0 |
| S2 | Aggiungere il Vicepresidente? | Sì, unico, permessi del Consigliere |
| S3 | Revisori: fino a tre o uno? | Fino a tre (art. 20.1) |
| S4 | Poteri dell'amministratore di sistema | (a) legge tutto, scrive Utenti |
| S5 | Chi è l'amministratore, e con quale utente | Da dire |
| S6 | Messaggio per l'utente disattivato | Solo con la password giusta |

Prima delle scelte serve anche il controllo del §1 (le due query).

Dopo le risposte:
1. tavola **UT-5**: modulo utente con socio, ruolo unico e «Passa il ruolo»;
2. codice;
3. tre giri di revisione;
4. collaudo **O-bis** in un file nuovo.

Le regole nuove su ruoli e soci vanno anche in `da-riportare-gestionale-aps.md`, adattate al Dante.

---

## 10. Risposte di Paolo (16/10/2026)

- **§1 controllo**: la query è stata lanciata sul database `uiynixlg_acv`, che è quello del vecchio plugin `gestionale-acv`, non del gestionale. Da rifare sul database scritto in `DB_DATABASE` nel `.env` del gestionale.
- **S1**: segnalazione errata. I soci restano come sono; nome e cognome separati solo negli utenti.
- **S2**: sì al Vicepresidente. Permessi da definire (§11).
- **S3**: un solo Revisore, ruolo unico. Per ora non serve, ma la regola si mette.
- **S4**: (a). L'amministratore di sistema legge tutto e scrive solo Utenti.
- **S5**: l'amministratore è Paolo. Il ruolo si **somma agli altri, tranne Presidente**. Esempio: Segretario + Tesoriere + Amministratore sullo stesso utente.
- **S6**: messaggio «Utente non attivo» solo con email e password giuste.

Effetti sulle regole del §3:
- combinazioni ammesse:
  - un ruolo solo;
  - Segretario + Tesoriere;
  - ciascuno dei due, o la coppia, con in più l'Amministratore;
  - Vicepresidente o Consigliere con in più l'Amministratore;
- l'Amministratore non si unisce al Presidente né al Revisore, perché l'Organo di controllo è fuori da tutto il resto;
- unici: Presidente, Vicepresidente, Segretario, Tesoriere, Revisore, Amministratore. Ripetibile solo il Consigliere.

Effetti sull'Amministratore:
- con un ruolo del CD sullo stesso utente, il socio resta obbligatorio (§4);
- i permessi si sommano come oggi: dove un ruolo scrive e l'altro legge, scrive;
- non cambia i propri ruoli, come il Segretario;
- può mandare il link della password a chiunque, Presidente compreso. È il rimedio alla password persa, senza passare dal database;
- può usare «Passa il ruolo» anche per il Presidente, con conferma e avviso a tutto il CD;
- ogni sua modifica degli utenti avvisa il Presidente.

## 11. Vicepresidente (S2, proposta confermata da Paolo il 16/10/2026)

Statuto: il CD lo nomina al proprio interno (art. 15.2). Sostituisce il Presidente in assenza o impedimento e, se il Presidente cessa, ne esercita le funzioni fino alla nuova elezione (art. 18.3).

**Proposta.**
- Permessi del **Consigliere**: scontrini, Prima Nota in lettura, Utenti in lettura.
- In più, **in sola lettura**, le sezioni del Presidente: Soci, Domande, Rinnovi quota, Tessere, Scadenzario, Promemoria, Impostazioni, Audit Log. Così, quando deve sostituirlo, sa già com'è la situazione.
- Riceve gli avvisi delle operazioni delicate, come Presidente, Segretario e Tesoriere.
- La **sostituzione vera**, con i poteri del Presidente per un periodo, si registra con il modulo Governance CD (Fase 1): data di inizio e fine e delibera. Fino ad allora, se serve, l'Amministratore usa «Passa il ruolo».

**Nota.** La composizione del CD esiste già in un'altra tabella, `gl_governance_cd`, con il socio e la carica, e la usa il voto sulle domande. Con il modulo Governance CD (Fase 1) i ruoli degli utenti del CD si potranno ricavare dalla carica, per avere una sola fonte. Nella v0.42.0 il collegamento al socio (§4) prepara questo passo.

## 12. Esito del controllo del §1 (16/10/2026)

`gl_email_log` sul database del gestionale: gli avvisi delle operazioni risultano tutti «inviata». Quelli per il Segretario vanno a un indirizzo con il dominio sbagliato (`gmail.net` al posto di `gmail.com`). Il server li accetta, ma non arrivano a nessuno.

**O10.1 e O12.1 non sono difetti del codice.** Rimedio: correggere l'email dell'utente dalla pagina Utenti, poi ripetere O10.

Spunto per la v0.42.0, da proporre: un avviso nella pagina Utenti per le email con domini quasi uguali a quelli comuni (`gmail.net`, `gmial.com`…).

## 13. Errori dei campi più evidenti (richiesta di Paolo, 16/10/2026)

Oggi il messaggio sotto il campo è a 11 px: il testo più piccolo della pagina. **Scelta A, approvata da Paolo** sul confronto `errori-confronto.png`:
- messaggio a 13 px su una striscia rosa, con il bordo rosso a sinistra e l'icona «!»;
- etichetta del campo rossa e bordo del campo più spesso;
- il riquadro «Controlla i dati» elenca i campi da correggere, ciascuno come link al campo;
- all'apertura la pagina scorre al primo errore e ci mette il cursore.

Scartati:
- il pop-up sul campo: sul telefono copre, mostra un errore alla volta, i lettori di schermo lo leggono male;
- il messaggio a comparsa: sparisce.

Si fa nei componenti comuni (`x-campo`, `x-avviso` del riquadro), quindi vale per tutti i moduli. Tavola **ER-1**; va nella v0.42.0.
