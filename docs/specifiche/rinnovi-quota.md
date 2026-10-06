# Rinnovi quota — quote annuali, ricevute numerate, annullamento

**Stato:** in uso (v0.36.0, 07/10/2026). Fino alla v0.35 era solo schema
(analisi del 22/09/2026, riuso diretto di `gl_rinnovi`).
**Introdotto in:** v0.36.0
**File coinvolti:**
- Migration: `2026_10_07_000098_adapt_gl_rinnovi_for_ricevute_table`
- Model: `Rinnovo` (aggiornato, con l'AuditObserver)
- Servizi: `App\Services\RinnoviQuota` (regole, registrazione, annullamento),
  `App\Services\RicevuteQuota` (PDF e invio), `App\Support\ImportoInLettere`;
  `IscrizioneSocio` (prima quota dalla domanda online) aggiornato
- Controller: `RinnoviController`; `SociController::show` (quote nella scheda)
- Viste: `resources/views/rinnovi/*` (`index`, `create`, `_quote-socio`,
  `_annulla-modale`), `pdf/ricevuta-quota.blade.php`,
  `emails/quote/ricevuta.blade.php`; `emails/iscrizione/iscritto` con la
  ricevuta; `pdf/carta-intestata` con la sezione `stili`
- Mail: `RicevutaQuotaEmail`; `SocioIscritto` con l'allegato
- CSS: sezione 14 di `app.css`
- Test: `tests/Feature/RinnoviQuotaTest.php`
- Analisi: `docs/analisi-rinnovi-quota.md` (anche nel progetto); tavole
  RQ-1…RQ-6 (`docs/mockup/Tavole-rinnovi-quota.pdf`)
- Promemoria delle quote: scheda `promemoria.md`

## 1. Logica

Statuto 1.0:
- quota annuale **entro il 31 gennaio** (art. 8, c. 1, n. 2);
- chi non ha versato **non vota** finché non versa;
- dopo tre mesi (dal 1° maggio) **decade** e il CD ne prende atto (art. 9);
- importi per categoria decisi dal CD: ordinario, anche per i fondatori,
  e giovane, cioè minorenne;
- chi compie 18 anni durante l'anno paga da giovane fino al 31 dicembre
  (art. 5.3): conta l'età al 1° gennaio dell'anno sociale.

Assenso del minore dai 14 anni a ogni tesseramento, quindi anche al
rinnovo (D.Lgs. 36/2021, art. 16, c. 2; statuto art. 6.3).

Decisioni di Paolo (06/10/2026):
- ricevute numerate **per anno di pagamento**, una sola serie anche per la
  prima quota dalla domanda online;
- promemoria automatici con pianificazione e testo modificabili, come
  servizio generale (`promemoria.md`);
- decadenze **solo come elenco** fino al modulo Verbali;
- firma «Il Tesoriere» con il nome stampato.

Dal Gestionale APS si riprende l'impianto («Registra quota», ricevuta
`N/anno`, invio per email), senza i suoi difetti (`analisi-rinnovi-quota.md`
§1).

## 2. Struttura dati

`gl_rinnovi`, campi aggiunti dalla v0.36.0:
- `anno_ricevuta` e `progressivo`: numero della ricevuta per anno di
  pagamento, unici insieme. `numero_ricevuta` resta come testo («13/2027»).
  La migration ricava i due campi dai numeri già scritti come «N/AAAA».
- `categoria`: ordinario o giovane, quella applicata.
- `versato_da_nome` e `versato_da_cf`: chi ha pagato. Per un minore è il
  genitore e il campo è obbligatorio; serve alla ricevuta e alla
  detrazione.
- `assenso_minore_il` e `assenso_minore_da`.
- `annullata_il`, `annullata_da`, `annullamento_motivo`.
- `metodo_pagamento` + `carta` (carta o POS).

Tolti i vincoli unici (socio, anno) e (anno, numero): una ricevuta annullata
resta, quindi per lo stesso socio e anno possono esserci più righe. Una sola
è valida (scope `valido`), regola applicata nel servizio con un lock sul
socio.

`gl_soci.quota_scadenza`: 31/12 dell'ultimo anno con una quota valida,
ricalcolato a ogni registrazione e annullamento.

## 3. Comportamento / flusso

**Elenco** (RQ-1, `/rinnovi`):
- si filtra per anno sociale, situazione, categoria e testo cercato;
- compaiono i soci attivi iscritti entro l'anno;
- situazioni:
  - in regola;
  - da versare entro il 31/01;
  - in ritardo, con il voto sospeso, fino al 30/04;
  - da dichiarare decaduto, dal 1° maggio;
- indicatori: soci, in regola con l'incassato, in ritardo, da dichiarare
  decaduti, ricevute inviate e da inviare;
- avviso della fase dell'anno;
- ogni riga apre la scheda del socio; la ricevuta si scarica, si invia e
  si annulla anche da qui;
- esportazione CSV, registrata nell'audit;
- in fondo, il riquadro dei promemoria delle quote.

**Registra quota** (RQ-2, `/rinnovi/registra`, anche dalla scheda del
socio). Scegliendo socio e anno la pagina si ricarica con categoria,
importo, assenso e destinatario corretti. L'anno proposto è il primo non
ancora versato.
- L'importo è quello della categoria; se è diverso servono le note.
- Data e modalità: i contanti vanno in Cassa; bonifico, carta o POS e
  «altro» vanno in Banca.
- Per un giovane «Versata da» e il codice fiscale di chi versa sono
  obbligatori (precompilati dal tutore); dai 14 anni serve anche la casella
  dell'assenso.
- Il numero della ricevuta si vede in anteprima.
- Si può inviare la ricevuta per email: al socio, al genitore se il socio
  è minorenne.

Al salvataggio, in una sola transazione:
1. il rinnovo, con il numero assegnato sotto lock;
2. il movimento di Prima Nota: entrata nella categoria `QUOTE`, con
   documento «ricevuta N/AAAA» e soggetto chi ha versato;
3. l'aggiornamento di `quota_scadenza`.

Dopo la transazione si generano il PDF e l'email.

**Ricevuta** (RQ-4 e RQ-6, A4 su carta intestata del periodo dell'ente
alla data del pagamento):
- numero e data;
- denominazione, sede legale e codice fiscale dell'ente (con il numero
  RASD se iscritto);
- socio con il codice fiscale; per i giovani anche la data e il luogo di
  nascita;
- categoria e attività sportiva (tiro con l'arco);
- chi ha versato, con il codice fiscale;
- importo in cifre e in lettere, modalità, validità;
- dichiarazione e dicitura fiscale;
- firma del Tesoriere, presa dal CD in carica (carica che contiene
  «Tesoriere»);
- codice del documento.

Riquadro della **detrazione per lo sport dei ragazzi** (art. 15, c. 1,
lett. i-quinquies TUIR; D.M. 28/03/2007): compare solo se valgono tutte
e tre le condizioni:
- alla data del pagamento l'ente è un'A.S.D. o S.S.D. iscritta al RASD
  (`iscrittoRasd()` del periodo);
- il ragazzo ha tra 5 e 18 anni;
- il pagamento è tracciabile (bonifico, carta).

La B.A.S. non è iscritta al RASD (Paolo, 06/10/2026), quindi nel 2027 la
detrazione non c'è mai e la pagina di registrazione lo dice. Dal 2028 la
pagina avvisa se si scelgono i contanti.

**Annullamento** (RQ-5), dalla scheda del socio o dall'elenco:
- serve una motivazione di almeno 15 caratteri;
- la ricevuta resta con il timbro «ANNULLATA» e il numero non si riusa;
- il movimento esce dalla Prima Nota. Su un anno contabile chiuso si
  registra `PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO` e si rifà la fotografia dei
  saldi;
- `quota_scadenza` si ricalcola;
- si può avvisare il socio con la ricevuta annullata in allegato.

Poi si registra di nuovo, con un numero nuovo.

**Scheda del socio**: riquadro «Quote associative» (`#quote`), visibile a
chi accede a Rinnovi quota. Mostra lo storico (anche le ricevute annullate,
con il motivo), la situazione dell'anno, «Registra quota», scarica, invia e
annulla.

**Prima quota dalla domanda online** (`IscrizioneSocio`): passa da
`RinnoviQuota::registra` (stessa serie di numeri; assenso e genitore dalla
domanda). La ricevuta va allegata all'email «Iscrizione completata».

Permessi: sezione `rinnovi_quota`. Presidente e Tesoriere registrano,
inviano e annullano; il Revisore consulta e scarica. Il riquadro nella
scheda del socio si vede solo con l'accesso a Rinnovi quota.

## 4. Implementazione tecnica

- **Numerazione**: `RinnoviQuota::prossimoNumero` legge il massimo dell'anno
  con `lockForUpdate` dentro la transazione. Il vincolo unico
  (`anno_ricevuta`, `progressivo`) fa da rete di sicurezza.
- **Una sola quota valida** per socio e anno: `lockForUpdate` sul socio,
  poi la verifica.
- **Quote incassate prima dell'avvio della contabilità** (impostazione
  `contabilita_anno_avvio`, 2027 per la Compagnia; se esiste già un anno
  contabile precedente, per esempio durante il collaudo, vale quello):
  rinnovo e ricevuta sì, **movimento in Prima Nota no**, con una nota,
  perché l'incasso è nel vecchio gestionale (`gestionale-acv`). Vale per le
  quote 2027 versate a dicembre 2026 e per le domande online accolte prima
  del 2027. Un movimento del 2026 farebbe del 2026 il «primo anno»
  contabile e il 2027 perderebbe i saldi di apertura (revisione, giro 1).
- **Importo in lettere** (`ImportoInLettere`), scritto da zero:
  - «dodici/50», «trentuno», «trentotto», «ventitré», «centottanta»;
  - il Gestionale APS arrotondava all'intero e sbagliava le elisioni.
- **Audit**:
  - `Rinnovo` è osservato: creazione MEDIUM, annullamento HIGH, con email
    al Presidente come le altre operazioni HIGH;
  - file e stato dell'invio si salvano senza eventi (rumore tecnico);
  - esportazione CSV registrata come `RINNOVI_ESPORTATI`.
- PDF sul disco `local` in `ricevute/<anno>/<nome casuale>.pdf`; se il file
  manca si rigenera. Download con `nosniff`.
- Email con `InvioEmail`, che le registra nel registro email; tipi
  `ricevuta_quota` e `ricevuta_quota_annullata`.
- Dicitura IVA: «Somma non soggetta a IVA (art. 4 D.P.R. 633/1972) e non
  imponibile (art. 148, c. 1, TUIR). Documento non fiscale.» Il passaggio
  all'esenzione è rinviato al 1° gennaio 2036 (provvedimento in Gazzetta
  Ufficiale del 12/12/2025). **Da far confermare al commercialista**,
  insieme al bollo: oggi le quote sono sotto 77,47 €, quindi la ricevuta lo
  dice.

## 5. Conformità e sicurezza

- Ricevute senza buchi né doppioni; un annullamento lascia traccia, con il
  motivo, nell'Audit Log e sulla ricevuta stessa.
- Dati per la detrazione sportiva sempre presenti per i giovani; la
  dicitura compare solo quando la detrazione spetta.
- Assenso del minore registrato con data e operatore.
- La quota in contanti resta permessa (sotto la soglia di tracciabilità).

## 6. Interazioni con altri moduli

- **Prima Nota**: categoria `QUOTE`, conto secondo la modalità; il
  movimento della quota non si elimina dalla Prima Nota, ma dalla quota
  (annullamento).
- **Anni contabili**: anno chiuso rifiutato per la registrazione;
  annullamento su anno chiuso come correzione motivata.
- **Soci**: `quota_scadenza` e filtri «quota versata» dell'elenco Soci;
  riquadro nella scheda.
- **Domande**: prima quota con ricevuta (`IscrizioneSocio`).
- **Profilo dell'ente**: denominazione, sede e RASD alla data del
  pagamento.
- **Promemoria**: funzione «Quote non versate» (`promemoria.md`).
- **Verbali** (da fare): presa d'atto delle decadenze dal 1° maggio.

## 7. Storia ed evoluzione

- 22/09/2026: analisi, schema confermato.
- 06/10/2026:
  - analisi del Gestionale APS e decisioni di Paolo;
  - correzione del MANUALE §2.3 sull'IVA;
  - domanda sulla detrazione sportiva;
  - tavole RQ-1…RQ-6 approvate.
- **v0.36.0** (07/10/2026), con tre giri di revisione critica:
  1. **Correttezza e sicurezza.** Trovati e corretti due difetti:
     - una quota con data precedente all'avvio della contabilità (per
       esempio la quota 2027 versata a dicembre 2026, o una domanda accolta
       prima del 2027) avrebbe creato l'anno contabile 2026, togliendo al
       2027 i saldi di apertura. Prima la regola rifiutava la quota, ma così
       si bloccavano anche le iscrizioni dalla domanda (lo ha mostrato il
       test del voto del CD). Ora la quota si registra con rinnovo e
       ricevuta ma senza movimento, e c'è l'impostazione
       `contabilita_anno_avvio`, con test;
     - la regola `accepted` dell'assenso scattava anche quando il campo
       non era richiesto, bloccando ogni quota degli adulti (trovato dai
       test).

     Verificati: lock sulla numerazione e sul socio, escape dei testi nelle
     email e nelle anteprime, permessi del Revisore, ricevuta di un'altra
     sezione non raggiungibile.
  2. **Coerenza e casi limite.**
     - Il Tesoriere non accede alla scheda del socio, quindi non avrebbe
       potuto annullare: l'annullamento c'è anche nell'elenco, con la
       stessa finestra.
     - Il salvataggio del file e dello stato d'invio produceva righe di
       audit inutili; ora è senza eventi.
     - L'indicatore «In ritardo» ripeteva i decaduti dopo il 30 aprile.
     - Provato l'annullamento delle migration con i dati.
  3. **Interfaccia** (1440 e 375 px, nessun `style=`): sul telefono
     l'intestazione «Quote associative» usciva dallo schermo (ora va a
     capo); promemoria del riquadro messi nell'ordine dell'anno sociale.
