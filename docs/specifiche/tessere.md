# Tessere CSEN — pacchetti, emissione, rinnovo a 12 mesi, carico dal portale

**Stato:** in uso (v0.37.0, 07/10/2026).
**Introdotto in:** v0.37.0 (prima metà dell'analisi `analisi-tessere-scadenze.md`;
la seconda metà, Scadenzario e promemoria, è la v0.38.0)
**File coinvolti:**
- Migration: `2026_10_07_000100_create_gl_tessere_tables`
- Model: `TesseraTipo`, `TesseraPacchetto` (nuovi), `Tesseramento` (rifatto,
  con l'AuditObserver)
- Servizi: `App\Services\Tessere` (numeri, situazione, quota, pacchetti,
  emissione), `App\Services\CaricoTessereCsen` (elenco del portale CSEN),
  `App\Services\TesseraSenzaQuota` (eccezione)
- Controller: `TessereController` (nuovo); `Concerns\Giustificativi` (tratto
  estratto da `PrimaNotaController`, usato da tutti e due);
  `SociController`, `RinnoviController` aggiornati
- Viste: `resources/views/tessere/*` (`index`, `_emissione`, `pacchetto`,
  `carica`, `_suggerita`); `soci/show`, `soci/index`, `rinnovi/index`;
  componente `x-modale` con `titolo-alpine`
- JavaScript: `emissioneTessera`, `pacchettoTessere`, `scadenzaTessera` in
  `resources/js/app.js`
- CSS: sezione 15 di `app.css`
- Seeder: `TesseraTipoSeeder` (nuovo), `SezioneSeeder`,
  `PrimaNotaCategoriaSeeder` (codice `TESSERE_EPS`), `DatabaseSeeder`
- Comando: `tessere:pulisci` (ogni giorno alle 03:30, `routes/console.php`)
- Test: `tests/Feature/TessereTest.php` (11 test)
- Analisi: `docs/analisi-tessere-scadenze.md`; tavole TS-1…TS-5
  (`docs/mockup/Tavole-tessere-scadenze.pdf`)

## 1. Logica

Da Paolo (07/10/2026):
- 4 € di ogni quota vanno al CSEN; la quota entra **intera**, il costo delle
  tessere esce in Prima Nota all'acquisto del pacchetto (criterio di cassa);
- le tessere si comprano **a pacchetti** con numeri stampati e vanno a
  **tutti i soci**; il prezzo dipende dal tipo;
- la tessera vale **un anno dall'emissione** (non l'anno solare) e comprende
  l'assicurazione base: scaduta, il socio non è coperto;
- per ora un solo tipo: **Silver Base a 4 €** (07/10/2026).

Regole:
- **scadenza** = emissione + 1 anno − 1 giorno (dal 09/01/2026 al
  08/01/2027; dal 29/02/2028 al 27/02/2029: niente 1° marzo);
- **rinnovo**: tessera nuova con `precedente_id`, proposta dal giorno dopo
  la scadenza della precedente (se non è già passata, altrimenti da oggi);
  due tessere dello stesso socio non si sovrappongono;
- **numero proposto**: il primo libero dei pacchetti del tipo, dal più
  vecchio; si può scegliere un altro numero libero di un pacchetto dello
  stesso tipo, mai uno già usato né uno fuori dai pacchetti;
- **quota**: si rinnova a chi è in regola con la quota dell'anno della
  tessera (o di un anno dopo: promozione di fine anno) o è ancora nel
  termine del 31 gennaio; a chi è in ritardo (voto sospeso) o da dichiarare
  decaduto (dal 1° maggio) solo con «Rinnova lo stesso», registrato con la
  tessera (`senza_quota`). Prima dell'avvio della contabilità nel gestionale
  (2027) le quote non ci sono: «Quota nel vecchio gestionale», nessun
  blocco.

### Numeri del CSEN
L'elenco del portale (07/10/2026) ha numeri come `26B2749167`: un
**prefisso di stagione** (`26B` fino ad agosto, `27B` da settembre) e un
**progressivo** che continua tra una stagione e l'altra (`26B2229057` il
30/08, `27B2229058` il 02/09). Quindi:
- il pacchetto tiene prefisso, progressivi dal… al… e numero di cifre
  (`0951` resta di 4 cifre);
- un numero è «usato» per il suo progressivo, qualunque sia il prefisso;
- all'emissione si possono scrivere solo le cifre: il prefisso è quello del
  pacchetto; un prefisso diverso da quello del pacchetto si rifiuta.

Da chiarire con Paolo: nell'elenco una **Gold Base** (`27B2229058`) sta
nell'intervallo delle Silver. Se il CSEN vende pacchetti misti, il tipo va
sulla tessera e non sul pacchetto (oggi: un tipo per pacchetto, tavole
approvate).

## 2. Struttura dati

- `gl_tessere_tipi`: `nome` (unico), `prezzo` (vuoto = da scrivere: tipo
  arrivato dal CSV), `attivo`, `ordine`.
- `gl_tessere_pacchetti`: `tipo_id`, `acquistato_il`, `prefisso` (max 10),
  `dal`, `al`, `cifre`, `prezzo_unitario`, `totale`, `prima_nota_id`
  (uscita «Tesseramento Soci EPS»), `note`, `inserito_da`. Al massimo 1.000
  tessere per pacchetto; nessuna sovrapposizione di progressivi con un altro
  pacchetto.
- `gl_tesseramenti` (esistente, adattata): tolto il vincolo unico (socio,
  stagione); `numero_tessera_csen` unico; nuovi `tipo_id`, `pacchetto_id`,
  `progressivo`, `scadenza`, `precedente_id`, `origine` (`emessa` |
  `caricata`), `discipline`, `portale_csen_il` («inserita sul portale
  CSEN»), `senza_quota`. `stagione_sportiva` = anno di emissione;
  `pacchetto_tesseramento` (testo libero) resta con il nome del tipo.
- Migration sulle righe già presenti: prima di ogni modifica controlla
  numeri vuoti o ripetuti (si ferma con l'elenco: MySQL non annulla le
  modifiche di struttura); poi scadenza calcolata, progressivo, origine
  `caricata`, data del portale = rilascio, tipo dal campo libero (tipo nuovo
  senza prezzo).

## 3. Comportamento / flusso

**Pagina Tessere** (menu Tesseramento, TS-1, TS-5): avviso con le tessere in
scadenza e i soci senza tessera valida; indicatori (valide su soci attivi, in
scadenza a 30 giorni, scadute o mancanti, disponibili per tipo, costo delle
tessere emesse nell'anno con la quota netta); elenco «Da rinnovare»
(scadute, mai tesserati, in scadenza) con la quota dell'anno e «Rinnova» /
«Emetti»; riquadro «Disponibili» con i tipi (prezzo, in uso, nuovo tipo);
pacchetti con emesse, disponibili, costo e movimento; elenco di tutte le
tessere con ricerca, filtro (valide, in scadenza, scadute, da inserire sul
portale), spunta del portale ed eliminazione motivata.

**Nuovo pacchetto** (TS-2): acquisto, tipo, prezzo (proposto dall'ultimo
pacchetto dello stesso tipo, altrimenti dal listino), numeri dal… al… come
stampati, quantità e totale calcolati; «Registra l'uscita in Prima Nota»
con data, conto e giustificativi (rifiutata prima dell'avvio della
contabilità: si toglie la spunta). Le tessere già caricate con un numero del
pacchetto e dello stesso tipo si collegano. **Correzione**: date, numeri,
prezzo e note; con tessere emesse serve la motivazione (Audit Log) e
l'intervallo deve contenerle; il movimento segue il nuovo totale (non su un
anno chiuso). **Eliminazione** solo senza tessere, con il movimento e i
giustificativi.

**Emetti / Rinnova** (TS-3): finestra con socio, tipo (come la precedente),
numero proposto, data (il giorno dopo la scadenza della precedente), scadenza
calcolata, riquadro della quota, «Rinnova lo stesso» se serve, «Inserita
anche sul portale CSEN». Si apre dall'elenco, dal pulsante in alto, dalla
scheda del socio (`?emetti=<id>&da=socio`: dopo l'emissione si torna alla
scheda) e dall'avviso che compare dopo la registrazione della quota di un
socio senza tessera valida. Data di emissione: da un anno fa a 60 giorni
avanti.

**Scheda del socio** (TS-4): badge «Tessera CSEN: valida / scade tra N
giorni / scaduta / nessuna» nell'intestazione, riquadro con lo storico delle
tessere e il pulsante solo quando la tessera è in scadenza, scaduta o manca;
avviso rosso senza tessera valida. Lo stesso badge è nell'elenco soci e nei
Rinnovi quota (colonna «Tessera CSEN»), dove compare anche la **quota
netta** dell'anno.

**Carica tessere già emesse**: CSV esportato dal portale CSEN (colonne
separate da «;»; servono CODICE FISCALE, DATA DI RILASCIO, NUMERO TESSERA,
TIPO TESSERA). Anteprima con esito per riga (da caricare, già nel
gestionale, socio non trovato, da correggere), poi conferma. Il socio si
trova per codice fiscale; l'anagrafica non cambia; telefono ed email del file
non si leggono. Si caricano numero, tipo (tipo nuovo senza prezzo), rilascio,
inserimento, scadenza, qualifica (se c'è in `gl_qualifiche`), discipline,
istruttore nelle note; la catena dei rinnovi si ricostruisce. Il file si
cancella alla conferma o all'annullamento (anche se il carico fallisce), e
comunque dopo un giorno (`tessere:pulisci`). Il file si può ricaricare: le
tessere presenti si saltano.

## 4. Implementazione tecnica

- Tutte le scritture in transazione con lock: socio (emissione), pacchetti
  (registrazione, correzione), tessera (eliminazione).
- Una riga di audit per operazione: carico (`TESSERE_CARICATE`, senza una
  riga per tessera), eliminazione (`TESSERA_ELIMINATA`: nel dettaglio, che
  entra nell'hash, solo motivazione e id; numero e socio nei valori),
  correzione motivata (`TESSERE_PACCHETTO_CORRETTO`).
- Giustificativi del pacchetto con lo stesso codice della Prima Nota (tratto
  `Concerns\Giustificativi`): messi da parte prima della validazione,
  nome `PN<anno>-<n.>-<progr.>`, file cancellati solo dopo il commit.
- Finestra di emissione: dati proposti per socio e primo numero libero per
  tipo calcolati dal controller; con un errore «Rinnova lo stesso» (eccezione
  `TesseraSenzaQuota`, flash `serve_conferma`) la casella compare anche se la
  data scelta è di un altro anno.
- Scadenza calcolata uguale in PHP (`addYearNoOverflow`), JavaScript e MySQL
  (migration).
- Tipi di tessera: dalla pagina Tessere, non da Impostazioni come diceva la
  tavola TS-2: Impostazioni è solo del Presidente, i pacchetti li compra il
  Tesoriere.

## 5. Conformità e sicurezza

- Il CSV del CSEN contiene dati personali (anche di minori): resta solo il
  tempo dell'anteprima, in `storage/app/private/tmp/tessere`, e si cancella;
  dal file si leggono solo i dati della tessera.
- Permessi (sezione `tesseramento`): Presidente, Segretario, Tesoriere
  modificano; Revisore in sola lettura. Il Tesoriere non vede la scheda del
  socio: niente link ai soci per lui.
- Senza tessera valida il socio non ha l'assicurazione base: il gestionale
  avvisa, non blocca (decide chi è al campo).

## 6. Interazioni con altri moduli

- **Prima Nota**: uscita «Tesseramento Soci EPS» (codice `TESSERE_EPS`).
- **Rinnovi quota**: situazione della quota nella finestra; colonna «Tessera
  CSEN» e quota netta nell'elenco; dopo la quota di un socio senza tessera,
  invito a emetterla.
- **Soci**: badge e riquadro nella scheda, colonna nell'elenco.
- **v0.38.0**: Scadenzario con «Rinnovata» (affiliazione e polizza RC),
  riquadri in Impostazioni › Ente, promemoria «Tessere in scadenza» (PR-3).

## 7. Storia ed evoluzione

- 07/10/2026: analisi e tavole TS-1…TS-5 approvate da Paolo; tipo Silver Base
  a 4 €; elenco del CSEN (30 tessere) come formato del carico.
- **v0.37.0** (07/10/2026), 86 test, tre giri di revisione critica:
  1. **Rilettura propria.** Righe di `gl_tesseramenti` senza scadenza
     avrebbero fatto fallire lo stato (ora la migration la calcola, anche dal
     29/02); un numero scritto con le sole cifre perdeva il prefisso del
     pacchetto; nelle prove con il browser un nome di variabile ripetuto
     nella vista svuotava il numero proposto.
  2. **Revisione indipendente** (10 difetti, tutti corretti):
     - migration interrotta a metà da numeri ripetuti (ora controllo prima);
     - numeri delle tessere caricate o vecchie riproposti: «usato» ora va
       per progressivo, qualunque sia il pacchetto;
     - prefisso diverso da quello del pacchetto accettato;
     - «Rinnova lo stesso» impossibile da spuntare con una data scelta a
       mano;
     - file dei giustificativi cancellati prima del commit;
     - CSV che poteva restare sul disco (ora `try/finally` e pulizia
       giornaliera);
     - lunghezze del CSV e del prefisso non controllate;
     - `senza_quota` messo anche a gennaio, nel termine;
     - «Rinnova» sulla scheda per una tessera valida (data oltre i 60
       giorni);
     - righe vecchie segnate «da inserire sul portale».
  3. **Verifica delle correzioni** (stesso revisore), 4 punti minori
     corretti:
     - casella che restava cambiando socio;
     - riquadro della quota non più valido dopo l'errore;
     - pagina di correzione e servizio che contavano diversamente le
       tessere del pacchetto;
     - riconoscimento dell'errore dal testo (ora un'eccezione apposta).

     Interfaccia a 1440 e 375 px senza scorrimento orizzontale, nessun
     `style=`.
- **v0.37.2** (07/10/2026, collaudo L7): scegliendo nella finestra un socio
  con la tessera ancora valida a lungo, il rinnovo partirebbe oltre i 60
  giorni ammessi e il server rispondeva solo «L'emissione può essere al
  massimo tra 60 giorni». Ora la finestra lo dice prima («Rinnovo non ancora
  possibile»: tessera, scadenza, data del rinnovo e da quando si registra) e
  il pulsante resta spento finché la data è quella proposta; il messaggio
  del server spiega la regola. Costante `TessereController::GIORNI_AVANTI`.
