# Scadenzario — scadenze dell'associazione

**Stato:** in uso (v0.38.0, 11/10/2026). Prima: solo analisi (v0.10.0).
**Introdotto in:** v0.38.0 (pagina, «Rinnovata», riquadri, promemoria); tabella dal core.
**File coinvolti:**
- Migration: `2026_10_11_000101_scadenzario_affiliazione_polizza` (su
  `gl_scadenzario` del core: `chiave`, `prima_nota_categoria_id`, categoria
  `assicurazione`; nuova `gl_scadenzario_rinnovi`; in `gl_associazione`
  contatto del referente e dati della polizza RC)
- Model: `Scadenza` (con l'AuditObserver), `ScadenzaRinnovo` (idem)
- Servizio: `App\Services\Scadenzario` (rinnovo, annullamento, avviso
  sull'affiliazione)
- Controller: `ScadenzarioController`; in `ImpostazioniEnteController`
  `updateAffiliazione`, `updatePolizza`
- Viste: `scadenzario/index`, `_tabella`, `_rinnova`, `form`;
  `impostazioni/_affiliazione-polizza`; avvisi in `dashboard` e
  `tessere/index`
- Promemoria: `App\Promemoria\Funzioni\ScadenzeAssociazione`,
  `TessereInScadenza`, `App\Promemoria\Interni` (`promemoria.md`)
- Seeder: `ScadenzarioSeeder` (in `DatabaseSeeder`); codici
  `AFFILIAZIONE_EPS` e `POLIZZE` in `PrimaNotaCategoriaSeeder`; permessi in
  `SezioneSeeder`; promemoria pronti in `PromemoriaSeeder`
- Test: `tests/Feature/ScadenzarioTest.php`
- Tavole SC-1, SC-2, SC-3, PR-3 (`docs/mockup/Tavole-tessere-scadenze.pdf`),
  analisi `docs/analisi-tessere-scadenze.md` §2.6, §2.7, §5

## 1. Logica

Richiesta di Paolo (07/10/2026): l'affiliazione al CSEN ha un costo annuale
con una scadenza; la polizza RC (Unipol, tramite CSEN) scade a luglio. Sono
scadenze **dell'associazione**, poche righe, diverse da quelle dei soci
(tessere, certificati), che restano nei loro moduli.

Scelte (tavole approvate il 07/10/2026):
- lo Scadenzario del core (`gl_scadenzario`, ricorrenza annuale con giorno e
  mese fissi e preavviso) diventa una pagina;
- una scadenza **con un pagamento** ha una categoria di Prima Nota:
  «Rinnovata» registra l'uscita (con il giustificativo) e sposta la data
  all'anno dopo; **senza** pagamento è un adempimento: «Fatta» (es.
  l'approvazione del rendiconto);
- la scadenza dell'affiliazione e quella della polizza **non si scrivono in
  Impostazioni**: le tiene lo Scadenzario, i riquadri di Impostazioni › Ente
  le leggono (un solo dato);
- l'affiliazione scaduta è un avviso rosso nel riquadro, in Dashboard, nello
  Scadenzario e nella pagina Tessere: senza affiliazione valida il CSEN non
  emette tessere e l'iscrizione al RASD decade;
- i promemoria «N giorni prima» avvisano Presidente e Tesoriere (non i
  soci) prima di ogni scadenza.

Date (Paolo, 07/10/2026): affiliazione CSEN **al 31/12** (anno solare);
polizza RC **al 31/07** (confermato da Paolo l'11/10/2026); rendiconto **entro il 30/04**
(statuto art. 22.2: quattro mesi dalla chiusura dell'esercizio).

## 2. Struttura dati

- `gl_scadenzario` (dal core), in più:
  - `chiave` (unica, facoltativa): `affiliazione_eps`, `polizza_rc`,
    `rendiconto`. Le scadenze con la chiave le usa il gestionale (riquadri,
    avvisi): si modificano, non si eliminano;
  - `prima_nota_categoria_id`: categoria dell'uscita (solo uscite usabili nei
    movimenti). Vuota = adempimento;
  - `categoria`: in più `assicurazione`. `runts` resta nell'enum ma non si
    propone (la Compagnia non è nel RUNTS);
  - per le ricorrenti `giorno_ricorrenza` e `mese_ricorrenza` si prendono
    dalla data; il 29/02 diventa 28/02 negli anni non bisestili.
- `gl_scadenzario_rinnovi`: ogni «Rinnovata» o «Fatta»:
  - `data_scadenza` (la scadenza soddisfatta), `fatto_il`;
  - `importo`, `conto`, `prima_nota_id` (l'uscita, se registrata);
  - `prossima_scadenza` (vuota: una tantum chiusa), `note`, `registrato_da`.
  Una scadenza con rinnovi non si elimina (vincolo e controllo).
- `gl_associazione`:
  - `rete_affiliazione_contatto` (email o telefono del referente);
  - `polizza_compagnia`, `polizza_numero`, `polizza_copertura`;
  - `rete_affiliazione_scadenza` (dal core) resta come **copia** della
    scadenza dell'affiliazione, aggiornata da «Rinnovata», dalla modifica
    della scadenza e dall'annullamento.

Scadenze pronte (`ScadenzarioSeeder`, con la prima data da oggi in poi,
preavviso 30 giorni): Affiliazione CSEN (31/12, «Affiliazione EPS»),
Polizza RC (31/07, «Polizze Assicurative»), Approvazione del rendiconto
(30/04, nessun pagamento). Il seeder riempie anche, se vuoti, compagnia
(«Unipol, tramite CSEN») e copertura della polizza.

## 3. Comportamento / flusso

**Pagina** (SC-1, `/scadenzario`, menu Controllo › Scadenzario):
- «Ricorrenti ogni anno» e, se ci sono, «Una tantum» (quelle fatte restano
  visibili per un anno);
- colonne: scadenza (con la descrizione), categoria (quella di Prima Nota
  per i pagamenti), data, stato, ultimo pagamento (con il link all'uscita),
  preavviso, «Rinnovata» o «Fatta» e la matita;
- stato: **tra N giorni** (grigio), **in scadenza** entro il preavviso
  (giallo), **scaduta** dal giorno dopo (rosso), **fatta** (una tantum);
- in alto l'avviso sull'affiliazione (rosso se scaduta, giallo se in
  scadenza) con «Rinnovata».

**«Rinnovata» / «Fatta»** (finestra della tavola SC-1):
- data del pagamento (non nel futuro), importo (proposto dall'ultimo
  pagamento), conto, categoria (in sola lettura), prossima scadenza (un anno
  dopo, modificabile, deve venire dopo quella di adesso), «Registra l'uscita
  in Prima Nota» con il giustificativo, note;
- la spunta è proposta se l'anno di oggi è già nella contabilità del
  gestionale (`contabilita_anno_avvio`); un pagamento prima di quell'anno o
  in un anno chiuso non registra l'uscita (messaggio nella finestra);
- senza uscita l'importo, se scritto, resta nello storico per la proposta
  del prossimo rinnovo;
- adempimento: data, prossima scadenza (ricorrenti), note; una tantum: si
  chiude;
- dopo un errore la finestra si riapre con i dati scritti e i file già
  caricati (bag `rinnovo`);
- doppio invio o due persone insieme: la finestra porta la data della
  scadenza che rinnova; se nel frattempo è cambiata il gestionale rifiuta
  («già rinnovata, ricarica la pagina»), così la data non salta due anni.

**Scheda della scadenza** (`/scadenzario/{id}`): titolo, descrizione,
ripetizione, data (per spostarla dopo un pagamento si usa «Rinnovata»),
preavviso, categoria, categoria dell'uscita; storico delle registrazioni;
«Annulla l'ultima registrazione» (v0.38.1; prima «Annulla l'ultimo rinnovo») (per un errore di registrazione: motivazione di almeno 15
caratteri, la scadenza torna alla data di prima, l'uscita si elimina con i
giustificativi; non su un anno chiuso; con un'uscita lo annullano solo
Presidente e Tesoriere); «Elimina» solo per le scadenze senza chiave e senza
rinnovi.

**Impostazioni › Ente** (SC-2, SC-3), riga «Affiliazione e assicurazione»:
- **Affiliazione all'EPS**: ente (dal periodo in vigore), stato, codice di
  affiliazione, scadenza, comitato/referente e contatto, ultimo rinnovo con
  il link al movimento; «Vai alla scadenza»;
- **Polizza RC**: compagnia, stato, numero, scadenza, copertura, ultimo
  rinnovo; nota sugli infortuni dei soci (assicurazione base della tessera);
- stato del riquadro: «Valida», «In scadenza» (entro il preavviso),
  «Scaduta»: solo lo stato, la data è nella riga «Scadenza» (v0.38.1);
- «Modifica» solo al Presidente: codice, referente, contatto; compagnia,
  numero, copertura. La finestra ricorda che la scadenza non si scrive lì;
- affiliazione scaduta: avviso rosso sotto i riquadri.

**Avvisi altrove**: Dashboard (avviso e elenco delle scadenze in scadenza o
scadute, per chi vede lo Scadenzario), pagina Tessere (solo se scaduta: il
gestionale avvisa ma non blocca l'emissione, perché registra tessere emesse
sul portale del CSEN).

**Promemoria** (PR-3, `promemoria.md`): funzione «Scadenze
dell'associazione», 30 e 7 giorni prima, alle 9:00, a Presidente e
Tesoriere, una email per scadenza e per utente; dopo «Rinnovata» riparte
dalla scadenza dell'anno dopo.

**Permessi**:
- Scadenzario: Presidente, Segretario e Tesoriere modificano, il Revisore
  consulta;
- l'uscita in Prima Nota la registrano solo Presidente e Tesoriere (sezione
  `prima_nota`): il Segretario segna il rinnovo senza uscita;
- Impostazioni › Ente: il Presidente modifica; Tesoriere e Segretario
  vedono (sola lettura, dalla v0.38.0).

## 4. Implementazione tecnica

- `Scadenzario::rinnova` in una transazione con il lock sulla scadenza e il
  confronto con `per_scadenza`; l'uscita nasce con `forceFill` come nei
  pacchetti di tessere (stessa regola dell'anno contabile: `assicura` e
  `chiuso`), soggetto «CSEN» (dall'ente di affiliazione del periodo) o la
  compagnia della polizza.
- Giustificativi con il tratto `Concerns\Giustificativi` (messi da parte
  prima della validazione, nome codificato `PN<anno>-<n.>-<progr.>`, copie
  tolte se la transazione fallisce).
- Importo letto all'italiana (`App\Support\Importo`); nel modulo, dopo un
  errore, torna com'era scritto.
- Annullamento: l'ultimo **registrato** (per id, non per data), perché è
  quello che ha spostato la scadenza alla data di adesso. Riga d'audit
  `SCADENZA_RINNOVO_ANNULLATO` (MEDIUM) con la motivazione nei valori, fuori
  dall'hash.
- `RiskMatrix`: `ScadenzaRinnovo` MEDIUM; `Scadenza` creata LOW, modificata
  MEDIUM se cambiano data, completamento o categoria dell'uscita.
- Il seeder è idempotente: cerca per chiave; una scadenza senza chiave con
  lo stesso titolo (inserita a mano o rimasta da un rollback) riceve la
  chiave invece di essere duplicata.

## 5. Conformità e sicurezza

- Affiliazione: senza affiliazione valida l'iscrizione al RASD decade
  (`MANUALE-ASD.md` §2.1): avviso rosso in quattro punti e promemoria.
- Uscite in Prima Nota solo per chi ha la sezione `prima_nota`;
  giustificativi nell'archivio della Prima Nota.
- Ogni rinnovo, modifica e annullamento nell'Audit Log.

## 6. Interazioni con altri moduli

- **Prima Nota**: uscite «Affiliazione EPS» e «Polizze Assicurative» (o la
  categoria scelta). Se l'uscita si elimina dalla Prima Nota, il rinnovo
  resta con «senza uscita».
- **Impostazioni › Ente** (`profilo-ente.md`): riquadri SC-2, SC-3.
- **Tessere** (`tessere.md`): avviso di affiliazione scaduta; promemoria
  «Tessere in scadenza».
- **Promemoria** (`promemoria.md`): modalità «N giorni prima o dopo».
- **Compliance RASD** (futuro): userà la stessa scadenza dell'affiliazione.
- **Bilancio** (futuro): «Approvazione del rendiconto» diventerà «Fatta»
  dall'approvazione del rendiconto in Assemblea.

## 7. Storia ed evoluzione

- v0.10.0 (22/09/2026): analisi, schema del core confermato (ricorrenza solo
  annuale, sufficiente: la comunicazione trimestrale al RASD ha la sua
  tabella). Allora: «nessuna riga finché l'associazione non esiste».
- 07/10/2026: richiesta di Paolo (affiliazione, polizza RC), tavole SC-1…SC-3
  e PR-3 approvate; affiliazione al 31/12 (risposta delle 15:19).
- v0.38.0 (11/10/2026): realizzazione. Le scadenze pronte nascono subito
  (non dal 2028): la B.A.S. è già affiliata e assicurata.
  Scostamenti dalle tavole:
  - nessuna colonna «Da confermare»: la data dell'affiliazione è nota;
  - l'avviso rosso non blocca l'emissione delle tessere (il gestionale
    registra tessere già emesse sul portale; confermato da Paolo
    l'11/10/2026);
  - nessun numero sulla voce di menu (i numeri della barra laterale non
    sono ancora in uso per nessuna voce).
  Tre giri di revisione critica:
  1. **Primo giro** (codice): 5 difetti corretti — il permesso di modifica
     della scheda veniva sovrascritto (unione di array: anche il Revisore
     avrebbe visto il modulo attivo); «Annulla l'ultimo» prendeva il rinnovo
     più recente per data invece dell'ultimo registrato; la categoria
     dell'uscita accettava anche categorie di sistema o «solo bilancio»;
     dopo un errore l'importo tornava come «12.00» invece di «12,00»; nella
     pagina del promemoria due campi «Ora» con lo stesso id.
  2. **Secondo giro** (casi limite e installazione): 2 difetti corretti — il
     promemoria con 0 giorni si leggeva «0 giorni dopo» (ora «Il giorno
     della data»); dopo un rollback della migration il seeder duplicava le
     tre scadenze (ora adotta quelle con lo stesso titolo). Verificati: la
     finestra «prima» non parte dopo la data, il recupero a server spento,
     i doppioni di invio, il doppio clic su «Registra», la data passata
     nella finestra riaperta.
  3. **Terzo giro** (documenti, collaudo, telefono): 3 difetti corretti —
     la lista di collaudo (M12) consigliava di registrare con «Rinnovata» i
     pagamenti già fatti per le scadenze in corso, che avrebbe spostato le
     date di un anno (ora lo sconsiglia); la prova K1 diceva ancora che il
     Segretario vede la pagina Promemoria vuota; su telefono i riquadri
     Affiliazione e Polizza andavano in una colonna lunga invece delle due
     della tavola SC-2. Verificati a 1440 e 375 px: Scadenzario, finestra
     «Rinnovata» (anche riaperta dopo un errore, con il file già caricato),
     scheda della scadenza, Impostazioni › Ente (Tesoriere e Presidente,
     finestra «Modifica»), Dashboard con l'avviso rosso, Promemoria (elenco e
     modifica di un promemoria relativo). 104 test superati.
- 11/10/2026, sera: v0.38.0 installata sul server da Paolo; confermati solo
  avviso per l'affiliazione scaduta e polizza al 31/07. Collaudo M da fare.
- 12/10/2026: collaudo M (primo giro) di Paolo, correzioni nella **v0.38.1**:
  - M3: nella finestra «Rinnovata» il campo Categoria in sola lettura
    (`pr-valore`) era più alto di «Prossima scadenza»: ora ha l'altezza e il
    testo dei campi in ogni contesto (38 px, 34 px nelle finestre, 44 e 38 px
    su telefono);
  - M8: «Annulla l'ultimo rinnovo» su una una tantum fatta è voluto (§3: un
    «Fatta» per errore riapre la scadenza), ma la dicitura non andava bene
    (una una tantum non si rinnova, può essere un'azione qualunque): su
    richiesta di Paolo ora è generica ovunque, «Annulla l'ultima
    registrazione» (pulsante, finestra, messaggi, Audit Log);
  - M9: nei riquadri di Impostazioni › Ente lo stato non ripete i giorni
    («Valida», «In scadenza», «Scaduta»): la data è nella riga «Scadenza»;
  - M11: prova e comando dei promemoria, in `promemoria.md` §7.
  Tre giri di revisione critica:
  1. **Primo giro** (codice): 1 difetto corretto — l'anteprima della pagina
     passava ai dati d'esempio appena oggi non c'era nessuno da avvisare,
     mentre la prova usava ancora un destinatario di un'altra data: le due
     non coincidevano (ora stessa scelta: destinatario di oggi, di un'altra
     data, esempio).
  2. **Secondo giro** (casi limite): 1 difetto corretto — nell'esempio delle
     tessere l'anno della quota era sempre quello dopo la scadenza, mentre
     il gestionale usa l'anno del giorno dopo la scadenza (una tessera che
     scade il 27/10/2026 si rinnova con la quota 2026). Verificati: pagina
     «nuovo promemoria» (senza anteprima), Revisore (saluto col suo nome),
     promemoria a data fissa con `--prova` («non è il momento; prossimo
     invio…»), nessun dato personale nell'output del comando.
  3. **Terzo giro** (CSS, documenti, collaudo): 1 difetto corretto — la
     prima correzione di M3 dava al campo 38 px, ma nelle finestre i campi
     sono di 34 px (38 px su telefono): misurati nel browser, ora uguali in
     ogni contesto. Lista di collaudo aggiornata (titolo alla v0.39.0, blocco
     N nell'introduzione, M3, M8, M9, M10, M11). Verificati a 1440 e 375 px:
     finestra «Rinnovata», Impostazioni › Ente, pagina del promemoria
     «Tessere da rinnovare». 136 test superati.
  Poi, stessa sera (ricontrollo di Paolo, M8): dicitura generica
  «Annulla l'ultima registrazione» (pulsante, finestra, messaggi, dettaglio
  dell'Audit Log; il codice dell'evento resta `SCADENZA_RINNOVO_ANNULLATO`
  per non spezzare lo storico del registro). Revisione: cercate nelle viste
  le altre scritte «rinnovo» rivolte all'utente: restano solo dove si parla
  di un pagamento (finestra «Rinnovata»), corrette lì. 136 test superati.

