# Comando di azzeramento dei dati di prova — analisi (14/10/2026)

Analisi prima del codice (Fase 0 del piano, `piano-2027.md`): un comando
`php artisan` al posto delle pulizie a mano dei blocchi di collaudo (reset
tra un giro e l'altro, J12, K11, L12, M12, N12, O14), con una prova a vuoto.

## 1. Quando serve

- **Dopo l'Assemblea della B.A.S. (circa il 16/11/2026)**, una volta sola,
  prima di caricare i dati veri. L'ordine:
  1. azzeramento;
  2. importazione dei soci;
  3. CD vero;
  4. tessere dal file del CSEN;
  5. saldi di apertura 2027;
  6. link privacy;
  7. apertura delle iscrizioni.
- Eventualmente anche fra un giro di collaudo e l'altro, sul server o in
  Laragon.
- **Mai dopo l'apertura**: dal primo dato vero il comando si rifiuta (§3.6).

## 2. Situazione di oggi

Le pulizie sono SQL da copiare in phpMyAdmin, sparse in sette punti della
lista di collaudo, più cartelle da cancellare a mano. Non coprono tutto:
- la catena dell'Audit Log («da scrivere nella procedura di
  azzeramento», `audit-log.md`);
- i soci, il CD di prova, i link privacy;
- il registro delle email;
- certificati e attestazioni dell'Audit Log;
- le scadenze spostate in avanti da «Rinnovata».

L'ordine sbagliato blocca le chiavi esterne, e una riga dimenticata (es.
un movimento 2026) fa del 2026 il primo anno contabile.

Il database ha **91 tabelle**: 84 `gl_` e 7 di Laravel (sessioni, cache, code, migrations). Quasi la metà
appartiene a moduli non ancora costruiti (Bilancio, Verbali, Volontari,
GDPR…) ed è vuota.

## 3. Scelte proposte

### 3.1 Il comando

```
php artisan gestionale:azzera            # prova a vuoto: dice cosa farebbe, non tocca niente
php artisan gestionale:azzera --esegui   # azzera davvero, dopo la conferma
```

- Senza `--esegui` **non cambia niente**. È il modo predefinito, così un
  comando lanciato per sbaglio non fa danni.
- Con `--esegui` chiede di scrivere `AZZERA` per confermare, poi lavora in
  quest'ordine:
  1. **copia di sicurezza** del database;
  2. gestionale in manutenzione (`down`);
  3. svuotamento delle tabelle;
  4. cartelle dei file;
  5. impostazioni;
  6. riga nuova dell'Audit Log;
  7. `up`.

  Se qualcosa va storto si ferma, rimette su il gestionale e dice dove
  trovare la copia.

### 3.2 Tre gruppi di tabelle

Ogni tabella è in uno dei tre gruppi, scritti nel codice.

**Si tengono** (configurazione e dati veri dell'ente):

| Tabella | Perché |
|---|---|
| `gl_ruoli`, `gl_sezioni`, `gl_ruoli_sezioni` | permessi |
| `gl_users`, `gl_users_ruoli` | utenti (D1) |
| `gl_impostazioni` | quote, soglie, versioni dei testi (alcune chiavi si rimettono, §3.4) |
| `gl_associazione`, `gl_associazione_periodi` | dati fissi e periodi dell'ente |
| `gl_prima_nota_categorie` | le 33 categorie |
| `gl_tessere_tipi` | Silver Base, Gold Base con i prezzi |
| `gl_qualifiche`, `gl_divisioni_arco`, `gl_specialita_gara` | tabelle di riferimento |
| `gl_promemoria` | promemoria con i testi (D3) |
| `gl_scadenzario` | solo le tre pronte, con la data rimessa (D4) |
| `migrations` | stato del database |

**Si svuotano** (dati di prova), tutte le altre, compresi:
- domande, voti e verbali di ammissione;
- soci, documenti, link privacy, CD;
- quote e ricevute, promemoria inviati;
- Prima Nota, documenti, anni contabili, tag, fornitori, scontrini;
- tessere e pacchetti;
- rinnovi dello Scadenzario e scadenze create a mano;
- Audit Log e certificati;
- registro delle email;
- sessioni, cache, code, token delle password.

I contatori si rimettono a 1: la prima domanda vera sarà la n. 1, la prima
ricevuta 1/2027, il primo movimento il n. 1.

**Nuove tabelle**: se trova una tabella che non è in nessun gruppo il
comando **si ferma**, e un test lo controlla a ogni versione. Così una
tabella aggiunta in futuro non resta piena (o non si svuota) per
dimenticanza.

### 3.3 File

Si cancellano le cartelle di `storage/app/private` con i file di prova:
- `domande`, `soci` (documenti dei soci), `ricevute`;
- `prima-nota` (giustificativi e documento dei saldi), `scontrini`;
- `audit` (certificati e attestazioni);
- `tmp` (anteprime abbandonate di tessere, importazione e giustificativi).

Una cartella sconosciuta viene elencata e lasciata dov'è, con un avviso.

### 3.4 Impostazioni rimesse

- `iscrizioni_aperte` = no; `domande_modalita_test` = no (fine delle prove).
- Tolta `audit_ultima_verifica`: la prima verifica sarà quella vera.
  (L'esito mostrato dopo una verifica a mano sta nella sessione, che si
  svuota comunque.)
- Tutte le altre restano come sono: quote, `contabilita_anno_avvio` 2027,
  versioni di statuto e informativa, soglie.

### 3.5 Audit Log

La catena si svuota e riparte. La prima riga della catena nuova è
`AZZERAMENTO_DATI_PROVA` (HIGH):
- data e ora;
- quante righe tolte per tabella;
- quanti file;
- nome del file di copia.

Così resta scritto che prima c'era un periodo di prova, e la verifica
d'integrità parte pulita. Il certificato annuale 2026 non si fa (dati di
prova); il primo vero sarà il 2027.

### 3.6 Protezioni

- **Copia di sicurezza** automatica prima di toccare qualunque cosa:
  `storage/app/private/backup/azzeramento-AAAAMMGG-HHMMSS.sql.gz`, tutte le
  tabelle, scritta da PHP (non serve `mysqldump`, che su cPanel a volte
  manca). Resta finché non la cancelli tu; è l'unico modo per tornare
  indietro.
- **Conferma scritta**: `AZZERA`. Con `--force` si salta solo la domanda,
  non la copia.
- **Rifiuto dopo l'apertura**: il comando non parte se trova dati veri:
  - un anno contabile **chiuso**;
  - movimenti di Prima Nota dall'anno d'avvio della contabilità
    (`contabilita_anno_avvio`, 2027) in poi;
  - ricevute di quote con la numerazione di quell'anno (es. 1/2027).

  In quei casi servirebbe un'altra procedura. Se trova una tabella che non
  è in nessun gruppo (§3.2), si ferma comunque.

  *Cambiata in realizzazione (§8).* La prima proposta guardava le domande
  arrivate con le iscrizioni aperte e la modalità test spenta. Ma il
  collaudo K8 ne chiede proprio una così, e il comando si sarebbe
  rifiutato dopo il collaudo. Quote e movimenti del 2027, invece, prima
  dell'apertura non ci possono essere.
- Gli **utenti restano**, come le loro password. Dopo l'azzeramento le
  persone vere si sistemano dalla pagina Utenti (D1).

### 3.7 Tavole: cosa si vede nel terminale

Il comando non ha pagine: le «tavole» sono i testi che stampa.

**AZ-1 — Prova a vuoto**
```
$ php artisan gestionale:azzera

AZZERAMENTO DEI DATI DI PROVA — prova a vuoto (non cambia niente)
Database: uiynixlg_asd · 14/11/2026 21:30

Si svuotano (righe):
  Soci ................................ 34   (gl_soci, documenti, link privacy)
  Domande e voti del CD ............... 12   (gl_domande 4, gl_voti_cd 8)
  Quote e ricevute .................... 21
  Prima Nota ......................... 58   (movimenti 41, documenti 17)
  Anni contabili ....................... 1   (2026, aperto)
  Scontrini ............................ 6
  Tessere CSEN ........................ 31   (tessere 30, pacchetti 1)
  Scadenzario ........................ 3   (rinnovi 2, scadenze a mano 1)
  Audit Log ......................... 812
  Registro delle email ............... 95
  Altre 61 tabelle .................... 0   (moduli non ancora in uso)

Si tengono:
  Utenti .............................. 5   (Andrea Papini – Presidente; Paolo Zanetti – Segretario e Tesoriere; …)
  Profilo dell'ente, periodi, categorie, tipi di tessera, impostazioni
  Promemoria ......................... 6   (i testi restano come sono)
  Scadenze pronte ..................... 3   (date rimesse: Affiliazione 31/12/2026, Polizza RC 31/07/2027, Rendiconto 30/04/2027)

File da cancellare: 143 (38,2 MB) in domande, soci, ricevute, prima-nota, scontrini, audit, tmp

Impostazioni: iscrizioni chiuse, modalità test spenta, verifica dell'Audit Log da rifare.

Per azzerare davvero:  php artisan gestionale:azzera --esegui
```

**AZ-2 — Esecuzione**
```
$ php artisan gestionale:azzera --esegui
(… lo stesso riepilogo …)

ATTENZIONE: i dati elencati sopra si cancellano. Prima si fa una copia del database.
Scrivi AZZERA per continuare: AZZERA

  ✔ Copia di sicurezza: storage/app/private/backup/azzeramento-20261114-213102.sql.gz (1,4 MB)
  ✔ Gestionale in manutenzione
  ✔ Tabelle svuotate: 1.069 righe in 32 tabelle
  ✔ File cancellati: 143 (38,2 MB)
  ✔ Impostazioni rimesse, scadenze pronte alla prossima data
  ✔ Audit Log ripartito: riga n. 1 «Azzeramento dei dati di prova»
  ✔ Gestionale di nuovo in linea

Fatto. Ora: importazione dei soci (Soci › Importa da Excel), CD vero, tessere dal file del CSEN,
saldi di apertura 2027 (Prima Nota › Anni contabili).
La copia di sicurezza contiene i dati di prova: cancellala quando hai controllato che va tutto bene.
```

**AZ-3 — Rifiuto**
```
$ php artisan gestionale:azzera
Non si può azzerare: l'anno contabile 2027 è chiuso, quindi ci sono dati veri.
```

## 4. Test

- La prova a vuoto non cambia niente: stessi conteggi prima e dopo.
- `--esegui`:
  - svuota le tabelle di prova e tiene utenti, ruoli, categorie, profilo
    dell'ente e tipi di tessera;
  - rimette le tre scadenze pronte e le impostazioni;
  - riparte la catena dell'Audit Log, e la verifica dà zero anomalie;
  - scrive la copia di sicurezza;
  - cancella le cartelle dei file.
- Conferma diversa da `AZZERA`: non fa niente.
- Rifiuto con un anno contabile chiuso.
- **Ogni tabella del database è in un gruppo**: il test fallisce se una
  migration nuova aggiunge una tabella non classificata.
- Dopo l'azzeramento si possono creare un socio e una quota, che prendono
  il numero 1.

## 5. Documenti da aggiornare

- `deploy.md`: §5 (quando lanciarlo) e §6.
- Lista di collaudo: blocco **P**, le prove J12…O14 diventano «vedi
  `gestionale:azzera`».
- `audit-log.md` (punto «da scrivere»), `MANUALE-ASD.md`, bootstrap.

## 6. Da decidere (decise il 14/10/2026, §7)

- **D1 Utenti**: restano tutti (proposta), e li sistemi tu dalla pagina
  Utenti. In alternativa si cancellano tutti tranne i Presidenti.
- **D2 Copia di sicurezza** automatica dentro `storage/app/private/backup`
  (proposta), oppure nessuna copia, lasciandola a te da cPanel.
- **D3 Promemoria**: restano con i testi di adesso (proposta: li hai
  provati e magari ritoccati), oppure si rimettono quelli predefiniti.
- **D4 Scadenze pronte**: si rimette la data della prossima scadenza vera
  (proposta: il collaudo con «Rinnovata» le ha spostate in avanti), oppure
  restano come sono.
- **D5 Fornitori**: si cancellano tutti (proposta: sono tutti di prova),
  oppure restano quelli creati a mano.

## 7. Decisioni (Paolo, 14/10/2026)

«Ok, per il comando di azzeramento»: accettate le cinque proposte.
- **D1**: gli utenti restano tutti, con ruoli e password. Il CD vero si
  sistema dalla pagina Utenti; l'utente di prova del blocco O resta
  disattivato.
- **D2**: copia di sicurezza automatica in `storage/app/private/backup`.
- **D3**: i promemoria restano con i testi di adesso.
- **D4**: le tre scadenze pronte tornano alla prossima data vera.
- **D5**: i fornitori si cancellano tutti.

## 8. Realizzazione (v0.41.0, 14/10/2026)

**File nuovi:**
- `app/Services/Azzeramento.php`: i tre gruppi di tabelle (`TIENI`,
  `PARZIALI`, `SVUOTA`), il riepilogo, la copia di sicurezza,
  l'azzeramento e il rifiuto;
- `app/Console/Commands/Azzera.php`: il comando `gestionale:azzera`, che
  stampa le tavole AZ-1…AZ-3;
- `tests/Feature/AzzeramentoTest.php`: 5 test.

Nessuna migration, nessuna pagina.

**Come lavora:**
- **Tabelle.** Ogni tabella del database è in uno dei tre gruppi. I gruppi
  sono scritti a mano nel codice, così una tabella nuova ferma il comando
  finché non si decide.
  - `SVUOTA`: 75 tabelle, comprese quelle di Laravel (sessioni, cache,
    code) e i token delle password.
  - `TIENI`: 14 tabelle.
  - `PARZIALI`: impostazioni e Scadenzario.
- **Svuotamento.** `TRUNCATE` con i controlli delle chiavi esterne sospesi:
  l'ordine non conta e i contatori ripartono da 1. Il test controlla che
  nessuna tabella tenuta abbia una chiave esterna verso una tabella
  svuotata.
- **Copia di sicurezza.** La scrive PHP, non serve `mysqldump`: per ogni
  tabella `SHOW CREATE TABLE` più gli `INSERT`, compressa gzip. Si importa
  così com'è in phpMyAdmin.
- **Scadenze pronte.** Prossima data della ricorrenza a partire da oggi.
  La scheda dell'ente riprende la scadenza dell'affiliazione.
- **Audit Log.** La catena riparte con la riga `AZZERAMENTO_DATI_PROVA`.
  Come ogni riga HIGH di sistema, l'avviso va al Presidente.
- **Manutenzione.** Il comando mette il gestionale in manutenzione
  (`down --retry=60`) e lo rimette in linea anche se qualcosa va storto.
  Se era già in manutenzione, lo lascia così.

**Prova sul database di sviluppo (`asd_demo`):**
- prova a vuoto: niente cambiato;
- esecuzione: catena dell'Audit Log integra;
- copia di sicurezza reimportata in un database vuoto: 91 tabelle con
  tutte le righe.

**Test:** 160 verdi (5 nuovi).

**Tre giri di revisione:**
1. *Logica.*
   - `Schema::getTables()` elencava anche le tabelle degli altri database
     visibili all'utente MySQL, per esempio quello dei test. Corretto:
     solo il database del gestionale.
   - Nel test la chiave della scadenza era sbagliata: `affiliazione` →
     `affiliazione_eps`.
   - Regola del rifiuto cambiata (§3.6).
   - Controllato che le tabelle tenute non puntino a quelle svuotate:
     `gl_users` non ha legami con i soci, `gl_promemoria` e
     `gl_scadenzario` hanno i legami nelle tabelle figlie, che si svuotano.
2. *Dati e sicurezza.*
   - La copia di sicurezza sta in `storage/app/private`, fuori dalla
     cartella pubblica. Contiene le password cifrate: il comando e
     `deploy.md` §5.1 dicono di cancellarla dopo il controllo.
   - `--force` salta solo la domanda, non la copia.
   - `TRUNCATE` non si annulla: se il comando si interrompe a metà, l'unico
     ritorno è la copia. Per questo la copia si fa prima di tutto, e
     l'errore dice dove trovarla e come reimportarla.
   - Il registro delle email si svuota prima dell'avviso al Presidente,
     così l'avviso è la prima riga del registro nuovo.
3. *Uso.*
   - Le sessioni si svuotano: chi era dentro deve rientrare. Scritto in
     `deploy.md` §5.1 e nel collaudo P.
   - Le cartelle di `storage/app/private` che il comando non conosce
     restano e vengono elencate.
   - Il messaggio finale elenca i passi successivi, nell'ordine di §1.
   - Le prove di pulizia a mano J12, K11, L12, M12, N12 e O14 della lista
     di collaudo rimandano al comando.

**Documenti:**
- `deploy.md` §5.1;
- `specifiche/audit-log.md`;
- lista di collaudo: blocco P e rimandi;
- MANUALE;
- bootstrap.

