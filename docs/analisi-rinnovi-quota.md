# Rinnovi quota e Promemoria — analisi (06/10/2026)

Analisi prima del codice. **Realizzata nella v0.36.0** (07/10/2026):
schede `rinnovi-quota.md` e `promemoria.md`. Tavole sulla tela «Gestionale ASD — Riferimento UI», sezioni
«Rinnovi quota» (RQ-1…RQ-6) e «Promemoria» (PR-1, PR-2); PDF in
`docs/mockup/Tavole-rinnovi-quota.pdf`.

## 1. Situazione di partenza

- **Gestionale ASD**: esistono la tabella `gl_rinnovi` e il model
  `Rinnovo` (un rinnovo per socio e anno, ricevuta numerata unica per
  anno, stato della ricevuta), la voce di menu «Rinnovi quota» (sezione
  `rinnovi_quota`: Presidente e Tesoriere modificano, Revisore consulta)
  e il servizio `Quota` (importi `quota_ordinario` 70 e `quota_giovane`
  30 nelle impostazioni, promozione di ottobre–dicembre per i nuovi soci).
  Nessuna pagina. La quota si registra solo dalla domanda online accolta
  (`IscrizioneSocio`: socio, rinnovo e movimento insieme, ma **senza
  numero di ricevuta**); per un socio inserito a mano non c'è nulla
  (domanda di Paolo, collaudo J10).
- **gestionale-acv** (Compagnia): nessun modulo soci; le quote sono
  movimenti della categoria «Quote associative».
- **Gestionale APS** (Dante), letto il 06/10/2026: «Registra quota» dalla
  scheda del socio, ricevuta `N/anno`, PDF con importo in lettere, invio
  per email, comando di sollecito. Difetti da **non** ripetere:
  - numero della ricevuta calcolato senza lock (doppioni con due
    registrazioni insieme); rinnovo e Prima Nota non nella stessa
    transazione; correzione della ricevuta che non aggiorna la Prima Nota
    e non lascia traccia nell'audit;
  - nessun annullamento: si sovrascrive;
  - importo in lettere sbagliato (12,50 → «tredici»; 31 → «trentauno»);
  - dicitura fiscale «esenzione IVA ai sensi dell'art. 4» (l'art. 4 è
    il «fuori campo», l'esenzione è l'art. 10);
  - ricevuta e solleciti mai al genitore per i minori;
  - solleciti pianificati per errore il 4 e 5 gennaio (`yearlyOn(1, 4)`
    = 4 gennaio), senza registro degli invii né controllo dei doppioni;
  - numero della ricevuta (testo libero) dentro `onclick`/`x-data`
    (XSS), `redirect` a un indirizzo preso dalla richiesta, GET che
    assegna numeri.
  Da riportare al Gestionale APS (`da-riportare-gestionale-aps.md`)
  quando il modulo sarà fatto.

## 2. Regole (statuto 1.0 e decisioni)

- Quota annuale entro il **31 gennaio** (statuto art. 8, c. 1, n. 2); chi
  non ha versato **non vota** finché non versa; **decade** dopo tre mesi
  (dal 1° maggio), il CD ne prende atto (art. 9). Le iscrizioni in corso
  valgono fino al 31/12/2026; dal 2027 quota annuale (art. 31.4).
- Importi per categoria decisi dal CD: ordinario (anche i fondatori) e
  giovane (minorenne). Chi compie 18 anni durante l'anno paga da giovane
  fino al 31 dicembre (art. 5.3): conta l'età al 1° gennaio dell'anno
  sociale.
- Assenso del minore dai 14 anni a ogni tesseramento, quindi anche al
  rinnovo (D.Lgs. 36/2021, art. 16, c. 2; statuto art. 6.3): casella
  obbligatoria nella registrazione, con data e operatore.
- **Decisioni di Paolo (06/10/2026):**
  - numerazione delle ricevute **per anno di pagamento** («13/2027»),
    una sola serie anche per la prima quota dalla domanda online;
  - promemoria **automatici**, con una pagina per pianificare date e
    testo delle email; e il sistema dei promemoria è **generale**,
    applicabile ad altre funzioni (§4);
  - decadenze: **solo l'elenco** «da dichiarare decaduti» ora; la presa
    d'atto del CD con il verbale scelto dall'elenco arriva con il modulo
    Verbali;
  - firma della ricevuta: **il Tesoriere, nome stampato** (dal CD in
    carica), senza immagine.

## 3. Ricevuta

- Numero `progressivo/anno` dell'anno del pagamento, assegnato nella
  stessa transazione del rinnovo e del movimento, con lock: niente buchi
  né doppioni; un numero annullato resta nel registro come «annullata».
- Contenuto (RQ-4): intestazione dell'ente in vigore alla data
  (profilo per periodi), numero e data, socio e codice fiscale, categoria,
  «versata da» (per un minore il genitore), importo in cifre e in lettere,
  modalità, validità, dichiarazione, firma del Tesoriere, codice del
  documento.
- **Dicitura fiscale proposta** (da confermare con il commercialista):
  «Somma non soggetta a IVA (art. 4 D.P.R. 633/1972) e non imponibile ai
  fini delle imposte sui redditi (art. 148, c. 1, TUIR). Documento non
  fiscale.» Le quote associative restano fuori campo IVA: il passaggio
  all'esenzione (art. 10) per gli enti associativi è stato **rinviato al
  1° gennaio 2036** (provvedimento pubblicato in Gazzetta Ufficiale il
  12/12/2025). **Correzione per `MANUALE-ASD.md` §2.3 e `rinnovi-quota.md`
  §4**, che lo davano in vigore dal 1° gennaio 2026.
- Imposta di bollo: le quote attuali (70 e 30 €) sono sotto 77,47 €, quindi
  nessuna marca. Se il CD fissasse una quota più alta, va verificata con il
  commercialista l'esenzione per gli enti sportivi; il modulo avvisa.
- **Detrazione per lo sport dei ragazzi** (domanda di Paolo, 06/10/2026):
  la detrazione del 19% (art. 15, c. 1, lett. i-quinquies TUIR; D.M.
  28/03/2007), fino a 210 € per ragazzo dai 5 ai 18 anni, spetta solo
  per le ricevute di associazioni sportive dilettantistiche riconosciute
  (oggi: iscritte al RASD) e con **pagamento tracciabile** (niente
  contanti). **La B.A.S. non è iscritta al RASD** (Paolo, 06/10/2026):
  le ricevute del 2027 non la riportano (RQ-4). Dal 2028, con l'A.S.D.
  iscritta, la ricevuta di un socio giovane pagata in modo tracciabile
  riporta la dicitura della detrazione e chi l'ha sostenuta (RQ-6). Il
  gestionale lo decide da solo dal profilo dell'ente in vigore alla data
  del pagamento (forma A.S.D. e numero RASD presenti), senza un'impostazione
  da ricordare.
  Contenuto richiesto dal D.M., sempre presente per i soci giovani:
  denominazione, sede legale e codice fiscale dell'ente, causale,
  attività sportiva (tiro con l'arco), importo, dati anagrafici del
  ragazzo (anche data e luogo di nascita), nome e codice fiscale di chi
  paga («Versata da», obbligatorio per i giovani). Con i contanti la
  registrazione avvisa che la quota non è detraibile (dal 2028).
- Invio per email al socio, al genitore per i minori; senza email si
  scarica e si consegna. Stati: da inviare, inviata, errore; reinvio.

## 4. Promemoria (servizio generale)

- Un promemoria = funzione (chi lo riceve e quali segnaposti ha), quando
  (data fissa ogni anno, oppure N giorni prima o dopo una data del
  destinatario), oggetto e testo con segnaposti, attivo o spento.
- Il giorno e l'ora scelti il gestionale calcola i destinatari, manda le
  email (stile del sito, al genitore per i minori, registro email) e
  registra ogni invio; un destinatario riceve lo stesso promemoria una
  volta sola per periodo. Chi non ha email compare nello storico «da
  avvisare a mano».
- Anteprima su un destinatario vero, invio di prova a sé.
- Permessi: dalla sezione della funzione (le quote: `rinnovi_quota`).
- Prima funzione: «Quote non versate» (soci attivi senza la quota
  dell'anno; segnaposti nome, cognome, anno, importo, scadenze, IBAN,
  causale, firma) con tre promemoria pronti: invito il 15 dicembre (anno
  dopo), sollecito il 1° febbraio, ultimo avviso il 1° aprile.
- Funzioni future (con i loro moduli): per esempio certificati medici in
  scadenza o scadenze dello Scadenzario.

## 5. Annullamento

- Una quota registrata per errore non si modifica: si **annulla** la
  ricevuta con motivazione (Audit Log, rischio alto), il movimento esce
  dalla Prima Nota (su anno chiuso: correzione motivata come in Prima
  Nota), il socio torna «da versare»; poi si registra di nuovo.

## 6. Da verificare con il commercialista

- Dicitura fiscale della ricevuta (§3).
- Esenzione dal bollo se una quota superasse 77,47 €.
- Che la quota associativa del socio giovane valga come «iscrizione
  annuale» per la detrazione sportiva (dal 2028) e la dicitura di RQ-6.
