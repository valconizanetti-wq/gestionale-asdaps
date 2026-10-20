# Anticipi e note di rimborso

**Stato:** consegnato (v0.43.0, 20/10/2026), da collaudare (blocco Q).
**Introdotto in:** v0.43.0. Prima: colonne del Dante su `gl_prima_nota` (22/09/2026), mai usate da una pagina.
**File coinvolti:**
- Migration: `2026_10_20_000105_note_rimborso`. Fa quattro cose:
  - crea `gl_note_rimborso`;
  - su `gl_prima_nota` aggiunge `anticipante_socio_id`, `nota_rimborso_id`
    e `data_spesa`, e toglie `anticipato_da`, `tipo_anticipante`,
    `rimborso_nota_numero`, `data_nota` e `iban_rimborso`;
  - su `gl_prima_nota_scontrini` aggiunge `anticipante_socio_id`;
  - mette il codice `DONAZIONI` su «Contributi e donazioni da terzi».
- Model: `NotaRimborso` (nuovo). Aggiornati:
  - `PrimaNota`: `anticipante`, `notaRimborso`, `isAnticipo`, `inNota`,
    `dataSpesa`;
  - `PrimaNotaScontrino`: `ANTICIPO`, `anticipante`, `notaRimborso()`;
  - `PrimaNotaCategoria`: `DONAZIONI`.
- Servizio: `App\Services\NoteRimborso`. Fa la nota, la firma, il
  pagamento, l'annullamento del pagamento e della nota, la rinuncia e il
  PDF. Ha anche i soci che possono anticipare, il visto e l'IBAN.
  Toccati anche:
  - `Scontrini::pagamento()` (anticipo dal telefono);
  - `AnniContabili::controlliChiusura()` (A2);
  - `AvvisiOperazioni` (voce `NotaRimborso`);
  - `Azzeramento` (`gl_note_rimborso` da svuotare).
- Controller: `AnticipiController` (nuovo). Cambiati anche:
  - `PrimaNotaController` (spunta, campi bloccati in una nota);
  - `ScontriniController` («Pagato da me», blocchi);
  - `SociController` (la scheda con anticipi non si elimina).
- Viste:
  - `anticipi/index` (AN-3), `anticipi/create` (passo 1), `anticipi/show`
    (AN-4, AN-4b), `pdf/nota-rimborso` (AN-2);
  - cambiate: `prima-nota/_form` (AN-1), `prima-nota/show`, `index` e
    `anni`; `scontrini/conferma` (AN-1b) e `show`; componente `passi`.
- Rotte: `prima-nota.anticipi.*` nel gruppo `permesso:prima_nota`.
- CSS: sezione 20 di `app.css`.
- Test: `tests/Feature/AnticipiTest.php` (10 test). Aggiornati
  `PrimaNotaTest` e `ScontriniTest`, che restano verdi.
- Analisi: `docs/analisi-moduli-anticipi.md` (parte 2).
- Tavole AN-1…AN-4b: `Tavole-anticipi.pdf`, fuori dal repository.
- Collaudo: `docs/test/collaudo-Q-anticipi.md`.

## 1. Logica

Un socio, di solito un membro del CD, paga di tasca propria una spesa della
Compagnia. La Compagnia gli restituisce i soldi più tardi, in contanti o
con un bonifico. Per il criterio di cassa la spesa:
- si registra subito, con il giustificativo;
- non tocca i saldi di cassa e banca finché non è rimborsata;
- entra in Prima Nota il giorno del rimborso, con il conto usato (A1).

Il rimborso si giustifica con una **nota di rimborso** firmata dal socio.
Ogni spesa si rimborsa una volta sola, perché sta in una nota sola.

**Il punto di partenza è il codice del Dante**, recuperato e migliorato come
vuole la regola di Paolo. Del Dante si riprendono:
- la spunta nel movimento;
- l'accorpamento delle spese della stessa persona;
- il numero `RIMB-ANNO-NNN`;
- le due fasi (nota, pagamento);
- l'annullamento motivato e il PDF.

I suoi otto difetti sono corretti (analisi §2.2-bis): numero protetto e mai
riusato, socio al posto del testo, Audit Log a ogni passo, pagamento in una
transazione, firma caricata, PDF salvato, annullamento del pagamento, note
e IBAN sulla nota.

**Risposte di Paolo** (20/10/2026, analisi §2.4 e §2.8):
- A1–A3, A5 come proposti;
- A4: chi anticipa è solo un socio;
- A6: la rinuncia al rimborso si registra come donazione;
- E1–E6 come proposti: quietanza dei contanti, visto del Presidente sulla
  nota del Tesoriere, conto fissato dalla modalità, giustificativo
  obbligatorio, solo soci maggiorenni, categoria «Contributi e donazioni
  da terzi».

Il registro dei moduli (parte 1 dell'analisi) passa alla Fase 2.

## 2. Struttura dati

**`gl_note_rimborso`**:
- `numero` (unico) con `anno` e `progressivo` (unici insieme);
- `tipo`: `rimborso` o `rinuncia`;
- `socio_id`, `data_nota`;
- `modalita`: `cassa` o `banca`, vuota per la rinuncia;
- `iban` (solo per il bonifico) e `note`;
- `totale`, `numero_spese`;
- `dati`: la fotografia, vedi sotto;
- `stato`: `emessa`, `firmata`, `pagata` o `annullata`;
- `pdf_path`;
- la firma: `firmata_path`, `firmata_nome`, `firmata_il`, `firmata_da`;
- il pagamento: `pagata_il`, `conto`, `pagamento_registrato_il`,
  `pagata_da`;
- l'annullamento: `annullata_il`, `annullata_da`, `motivo_annullamento`;
- `preparata_da`.

`dati` è la fotografia presa alla generazione:
- socio: nome, cognome, codice fiscale, carica nel CD o «Socio»;
- visto: carica e nome;
- righe: movimento, data della spesa, descrizione, categoria, codice della
  riga del rendiconto, fornitore, documento, importo, numero di
  giustificativi;
- ora di generazione.

Il PDF si rifà sempre uguale da qui. Così le descrizioni corrette dopo in
Prima Nota non cambiano la nota firmata.

**`gl_prima_nota`**:
- `sospeso` (spesa anticipata, c'era già);
- `anticipante_socio_id`;
- `nota_rimborso_id`, che si toglie quando la nota si annulla;
- `data_spesa`: la data dello scontrino, che resta anche dopo il rimborso;
- `rimborsato_il`.

Finché la spesa è aperta, `data` è uguale a `data_spesa` e `conto` è vuoto.
Al pagamento `data` diventa la data del pagamento e `conto` il conto della
nota. L'entrata della rinuncia ha `nota_rimborso_id`, ma non `sospeso`.

**`gl_prima_nota_scontrini`**: `conto` può valere `anticipo` («Pagato da
me»), con `anticipante_socio_id`.

## 3. Comportamento / flusso

**Spesa anticipata (AN-1).** Nel nuovo movimento di uscita c'è la spunta
«Anticipata da un socio (pagata di tasca propria)»:
- il conto sparisce;
- «Chi l'ha pagata» propone i soci attivi e maggiorenni, prima i membri
  del CD nell'ordine delle cariche (E5);
- almeno un giustificativo è obbligatorio (E4).

Dalla pagina degli anticipi «Spesa anticipata» apre il modulo con la spunta
già messa. Finché la spesa non è in una nota, la correggono e la eliminano
dal modulo Presidente e Tesoriere (T4). Togliendo la spunta diventa un
movimento normale.

**Dal telefono (AN-1b).** Al passo 2 degli scontrini c'è la terza scelta
«Pagato da me». Compare solo per chi è collegato a un socio attivo
maggiorenne (gli utenti del CD). I movimenti dello scontrino, anche dopo la
divisione, sono spese anticipate di quel socio.

**Anticipi e note di rimborso (AN-3)**, `prima-nota/anticipi`. Ci si arriva
dal pulsante «Anticipi e note» e dall'indicatore della Prima Nota.
- Riquadri: da rimborsare (importo, spese, soci, di ogni anno), note da
  firmare, note da pagare, rimborsato nell'anno scelto.
- «Spese senza nota»: una card per socio, con una casella per spesa e i
  pulsanti «Prepara la nota di rimborso» e «Rinuncia al rimborso
  (donazione)».
- Se tra le spese scelte ce n'è una «da classificare», i pulsanti si
  spengono con la spiegazione. La descrizione apre il movimento, da cui si
  sceglie la categoria.
- «Note»: le note dell'anno scelto, anche quelle annullate, con il motivo.

**Passo 1, la nota.** Si scelgono:
- la data, non prima della spesa più recente e non futura;
- il rimborso, «Cassa» o «Banca». Con la banca serve l'IBAN, controllato
  con le cifre di controllo;
- note facoltative.

«Genera la nota (PDF)» assegna il numero `RIMB-ANNO-NNN`, dall'anno della
data della nota. Il numero si prende con un lock sulle note dell'anno e ha
un vincolo unico. La nota nasce **emessa**, con la fotografia e il PDF
salvato. Il visto è del Tesoriere, o del Presidente se ha anticipato il
Tesoriere (E2).

**PDF (AN-2).** Sulla carta intestata del periodo dell'ente alla data della
nota. Contiene:
- socio con il codice fiscale, carica e modalità; IBAN e causale per il
  bonifico;
- le spese, con la categoria e, quando ci sarà la mappa, la riga del
  rendiconto;
- il totale in cifre e in lettere e la dichiarazione del socio;
- le firme: il socio e il visto. Per i contanti anche la riga «Per
  quietanza: ricevuti € … il __/__/____», firmata quando il socio riceve i
  soldi (E1).

**Passo 2, la firma.** Si carica la scansione o la foto (PDF, JPG o PNG,
fino a 8 MB). Il file si chiama `RIMB-2027-001-firmata.pdf`. Stato:
**firmata**. Finché non si paga, la copia si può sostituire.

**Passo 3, il pagamento (AN-4).** Si sceglie la data, non prima della nota,
non futura, non prima dell'avvio della contabilità e non in un anno chiuso.
Il conto è quello della nota (E3); per un altro conto si annulla la nota e
se ne fa un'altra.

In una transazione ogni spesa riceve `data` (il pagamento), `conto` e
`rimborsato_il`, ed entra nei saldi. Stato: **pagata**. L'Audit Log ha una
riga ad alto rischio, che arriva per email agli altri (A5).

**Rinuncia (AN-4b).** Stessa numerazione e stessi passi, con queste
differenze:
- il PDF è una dichiarazione di rinuncia: il socio dona l'importo;
- al passo 3, «Registra la donazione» mette in **cassa**, nella stessa
  data, le spese (uscite) e un'entrata uguale. L'entrata ha la categoria
  `DONAZIONI`, il socio, e il numero come documento;
- il saldo non cambia, ma il rendiconto mostra la spesa e la donazione.
  Stato: «Registrata».

**Annullare:**
- *una nota emessa o firmata* (T6): con il motivo, almeno 15 caratteri.
  Le spese tornano «senza nota». La nota resta «annullata» con il suo
  numero, che non si riusa, e il PDF si rifà con la scritta ANNULLATA;
- *un pagamento* (o la registrazione della rinuncia): con il motivo. Le
  spese tornano alla data della spesa, senza conto e fuori dai saldi.
  L'entrata della donazione si toglie. La nota torna «firmata». Se l'anno
  del pagamento è chiuso, i suoi saldi finali si aggiornano.

**Blocchi:**
- Una spesa in una nota non si elimina. Dal modulo si cambiano categoria,
  descrizione, fornitore, tag e documenti, non importo, data, conto e
  socio. Lo stesso vale per l'entrata della rinuncia.
- Uno scontrino con un movimento in una nota non cambia totale, data,
  conto e divisione, e non si elimina.
- La scheda di un socio con spese anticipate o note non si elimina.

**Chiusura dell'anno (A2).** Gli anticipi aperti non bloccano più la
chiusura: la lista li mostra come avviso, con l'importo. La spesa entra
nell'anno in cui si rimborsa; nel rendiconto, quando ci sarà, è un debito
verso i soci.

## 4. Implementazione tecnica

- `NoteRimborso::speseScelte()` controlla le spese, una volta per la
  pagina e una volta con il lock dentro la transazione. Ogni spesa deve
  essere aperta, senza nota, dello stesso socio e classificata. Due note
  preparate insieme sulle stesse spese: la seconda trova le spese già in
  una nota e si ferma.
- Una riga dell'Audit Log per operazione:
  - eventi `NOTA_RIMBORSO_EMESSA` (medio), `…_FIRMATA` (basso, medio se si
    sostituisce), `…_PAGATA`, `…_ANNULLATA` e
    `…_PAGAMENTO_ANNULLATO` (alti), e gli stessi `RINUNCIA_RIMBORSO_…`;
  - le spese cambiate dalla nota si salvano senza eventi;
  - il dettaglio non ha dati personali: socio, motivo e movimenti stanno
    nei valori, fuori dall'hash.
- `NotaRimborso` non ha l'AuditObserver: gli eventi li scrive il servizio.
- I file stanno in `storage/app/private/prima-nota/rimborsi/<anno>/`, una
  cartella già svuotata dall'azzeramento.
- Gli errori del servizio vanno nella borsa della finestra da cui parte la
  richiesta: `firmata`, `pagamento`, `annulla`, `annulla_pagamento`; negli
  scontrini `dati`, `divisione`, `elimina`.

## 5. Conformità e sicurezza

- Rimborso di spese documentate: non è un compenso né un reddito per chi lo
  riceve. Lo dice il PDF.
- Copia firmata e quietanza dei contanti proteggono il socio e la
  Compagnia.
- Il PDF ha il codice fiscale del socio. Lo vedono quelli che vedono la
  Prima Nota (CD e Revisore).

## 6. Interazioni con altri moduli

- Prima Nota: saldi (`effettivi`), indicatore, filtro «Anticipi da
  rimborsare», chiusura dell'anno.
- Scontrini: «Pagato da me» e blocchi.
- Soci: blocco dell'eliminazione della scheda.
- Consiglio Direttivo: carica nella nota, ordine dei soci, visto.
- Avvisi delle operazioni delicate, Audit Log, azzeramento.
- Rendiconto (Fase 2): i debiti verso i soci per le spese anticipate aperte.
- Diverso dai **rimborsi ai volontari** del Dante (`RIM-ANNO-NNN`, km ACI,
  vitto): vanno con il modulo Volontari (Fase 2).

## 7. Storia ed evoluzione

- 20/10/2026: analisi, tavole AN-1…AN-4b. Paolo risponde ad A1–A6 ed
  E1–E6 e approva le correzioni T1–T6. Codice v0.43.0.
- **Tre giri di revisione critica** prima della consegna:
  1. *Integrità dei dati.* Una scheda di un socio con spese anticipate o
     note si poteva eliminare (soft delete), lasciando note e spese senza
     socio visibile. Corretto in `SociController::bloccaEliminazione`, con
     il test `test_socio_con_anticipi_non_si_elimina`.
  2. *Casi limite delle pagine* (verificate a 1440 e 375 px). Nella
     modifica di una spesa già rimborsata il Conto spariva (la spunta
     dell'anticipo lo nascondeva): ora i campi bloccati mostrano il conto
     se c'è. Sul telefono i passi della nota con le date uscivano dallo
     schermo (scorrimento orizzontale): vanno a capo (`an-passi`). Il PDF
     aveva il riquadro della quietanza senza margine.
  3. *Permessi e testi.* Verificato che chi consulta (Revisore,
     Consigliere) non apre il passo 1 e non registra (test). I riquadri
     «Note da firmare» e «da pagare» elencavano tutti i numeri: ora al più
     tre.
- Test: 181 verdi (10 nuovi in `AnticipiTest`).
