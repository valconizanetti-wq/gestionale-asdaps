# Prima Nota — movimenti, giroconti, anni contabili, categorie e tag

**Stato:** in uso (v0.35.0, 06/10/2026). Scontrini dal telefono nella
v0.36.0, anticipi e note di rimborso nella v0.37.0.
**Introdotto in:** v0.35.0 (2026-10-06)
**File coinvolti:**
- Migration: `2026_10_06_000094_create_gl_anni_contabili_table`
  (sostituisce `gl_prima_nota_anni_chiusi`), `…000095_create_gl_tag_table`
  (con `gl_prima_nota.tag_id`), `…000096_add_uso_to_gl_prima_nota_categorie_table`
- Seeder: `PrimaNotaCategoriaSeeder` (33 categorie, in `DatabaseSeeder`)
- Model: `AnnoContabile`, `Tag` (nuovi); `PrimaNota`, `PrimaNotaCategoria`,
  `PrimaNotaDocumento` (aggiornati, con l'AuditObserver); tolto
  `PrimaNotaAnnoChiuso`
- Servizio: `App\Services\AnniContabili`; supporto `App\Support\Importo`
- Controller: `PrimaNotaController`, `AnniContabiliController`,
  `CategorieController`; `IscrizioneSocio` e `RiskMatrix` aggiornati
- Viste: `resources/views/prima-nota/*`, `resources/views/pdf/prima-nota.blade.php`;
  CSS sezione 13 di `app.css`
- Test: `tests/Feature/PrimaNotaTest.php` (12 test)
- Tavole: sezione «Prima Nota» della tela «Gestionale ASD — Riferimento UI»
  (PN-1…PN-9) e `docs/mockup/Tavole-prima-nota.pdf`

## 1. Logica

Registro per cassa delle entrate e delle uscite della Compagnia, con due
conti (cassa e banca), i giustificativi e un anno contabile che si chiude
dopo l'approvazione del rendiconto.

Da dove viene:
- **gestionale-acv**, il plugin WordPress con cui la B.A.S. tiene oggi la
  contabilità (analisi nel progetto: `claude/analisi-contabilita-esistente.md`,
  documenti `claude/acv-contabilita*.md`): anni con saldi di apertura
  ereditati, categorie (le stesse 33), fornitori «trova o crea», tag
  trasversali, regola della motivazione sull'anno chiuso, lettura degli
  importi all'italiana, conti `cassa`/`banca`;
- **Gestionale APS** (Dante): giroconto come coppia di righe, allegati
  multipli, esportazioni CSV e PDF, audit delle esportazioni; poi (v0.37.0)
  anticipi e note di rimborso, chiusura mensile della cassa e
  riconciliazione bancaria.

Decisioni di Paolo (06/10/2026):
- dal 01/01/2027 `gestionale-acv` va in pensione e si migrano **solo i
  saldi** di cassa e banca al 31/12/2026; il consuntivo 2026 si chiude lì;
- **si tengono tutte le categorie** di `gestionale-acv`;
- **tag** leggeri per i lavori interni, **Progetti** per bandi e contributi
  da rendicontare;
- «Disavanzo/Attivo anno precedente» solo in preventivo e rendiconto, mai nei
  movimenti; «Uscite da classificare» categoria di sistema;
- si tiene tutto ciò che c'era nei due sistemi (anticipi, riconciliazione,
  chiusura mensile della cassa: nelle versioni successive).

## 2. Struttura dati

- **`gl_anni_contabili`** (nuova, sostituisce `gl_prima_nota_anni_chiusi`,
  che conteneva solo gli anni chiusi): `anno` (unico), `stato`
  aperto/chiuso, `saldo_apertura_cassa/banca` (solo per il primo anno,
  `null` = da impostare), `saldi_fonte`, `saldi_documento_path/nome`,
  `saldo_chiusura_cassa/banca` (fotografia alla chiusura),
  `approvazione_data`, `approvazione_rif` (riferimento testuale al verbale:
  il modulo Verbali non c'è ancora), `chiuso_il`, `chiuso_da`. La migration
  copia gli eventuali anni chiusi della vecchia tabella.
- **`gl_tag`** (nuova): `nome` unico; **`gl_prima_nota.tag_id`** (FK,
  `nullOnDelete`). Un tag per movimento, valido per tutti gli anni.
- **`gl_prima_nota_categorie`**: nuove colonne `uso` (`movimenti`,
  `solo_bilancio`, `sistema`) e `natura_da_verificare` (accanto a
  `flag_non_istituzionale`). Codici con significato: `QUOTE` (Quote
  associative, usato da `IscrizioneSocio`), `DA_CLASSIFICARE` (Uscite da
  classificare, di sistema), `DISAVANZO_PREC`, `ATTIVO_PREC`.
- **`PrimaNotaCategoriaSeeder`**: le 33 categorie di `conta_categorie_master`
  (dump del 06/10/2026, `claude/acv-categorie-master.sql`), stesso nome e
  stato, ordine rinumerato (in `gestionale-acv` «Opere e progetti» e «Costi
  di gestione» avevano entrambe 10). «Uscite da classificare» resta attiva
  (lì era disattivata ma usata): è di sistema e non compare nelle tendine.
  Natura «da verificare» su «Gare, manifestazioni e iniziative» (entrata) e
  «Contributi da Esercenti, Commercianti…». Si può rilanciare senza doppioni.
- Invariati: `gl_prima_nota` (resto), `gl_prima_nota_documenti`,
  `gl_soggetti` (fornitori «trova o crea» per nome).

## 3. Comportamento / flusso

**Elenco** (PN-1, `/prima-nota`): anno, ricerca e filtri (conto, tipo,
categoria, tag); indicatori saldo cassa e banca (con l'apertura), entrate,
uscite, anticipi da rimborsare; avviso «da sistemare prima della chiusura»
(movimenti da classificare, anticipi). Un giroconto compare una volta sola
(«Cassa → Banca»), le sue due righe con il filtro di conto. In fondo i totali
del filtro, senza giroconti né anticipi aperti. Su telefono una scheda per
movimento con una sola riga «Importo» col segno.

**Esportazioni**: CSV (con il carattere `'` davanti ai testi che Excel
leggerebbe come formule) e registro PDF A4 orizzontale, con gli stessi filtri;
entrambe nell'Audit Log come `PRIMA_NOTA_ESPORTATA`.

**Movimento** (PN-3): tipo, conto, data, importo (campo di testo, letto
all'italiana), categoria (solo attive, d'uso «movimenti», del tipo scelto;
in modifica resta ammessa quella già assegnata), descrizione, fornitore
(«trova o crea» senza distinguere maiuscole), tag («trova o crea»), progetto
(se ce ne sono), socio (solo entrate), documento, giustificativi (JPG, PNG,
PDF, 8 MB ciascuno, 10 per volta), note. Fornitore e tag si creano nella
stessa transazione del movimento: se il movimento non si salva, non restano
«fantasmi» (bug già visto in `gestionale-acv`). «Salva e inserisci un altro».
Un movimento della quota di un socio o di uno scontrino non si elimina da
qui; una spesa anticipata non ancora rimborsata non si modifica dal modulo.

**Giroconto**: data, da, a, importo, descrizione (proposta se vuota). Due
righe collegate da `giroconto_id`; modifica ed eliminazione valgono per
entrambe.

**Anni contabili** (PN-8, `/prima-nota/anni`):
- un anno ha una riga dal primo movimento (`AnniContabili::assicura`, anche
  dalla domanda online);
- **saldi di apertura**: si scrivono solo per il primo anno del gestionale
  (per la Compagnia il 2027, dalla chiusura 2026 di `gestionale-acv`), con
  provenienza e documento facoltativo; con movimenti già registrati la
  modifica chiede una motivazione (`SALDI_APERTURA_MODIFICATI`, alto). Dagli
  anni dopo l'apertura è il saldo finale dell'ultimo anno registrato prima
  (un anno senza movimenti non ha riga e il saldo passa intatto);
- **chiusura** (dal più vecchio aperto) solo se: l'anno è finito, quello
  prima è chiuso, i saldi di apertura ci sono, nessun movimento da
  classificare, nessun anticipo da rimborsare; servono data e riferimento
  del verbale dell'Assemblea che approva il rendiconto. Si fotografano i
  saldi finali;
- **anno chiuso**: niente nuovi movimenti né movimenti spostati dentro o
  fuori; modifica, eliminazione, giustificativi aggiunti o tolti chiedono una
  motivazione (≥ 15 caratteri), registrata come
  `PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO` (alto), e rifanno la fotografia dei
  saldi, così l'apertura dell'anno dopo resta allineata;
- **riapertura**: solo il Presidente, solo l'ultimo anno chiuso, con
  motivazione (`ANNO_CONTABILE_RIAPERTO`, alto).

**Categorie e tag** (PN-9, `/prima-nota/categorie`): nuova categoria (tipo,
nome, natura), modifica di nome e natura, ordine con le frecce, attiva o
disattiva, elimina solo se mai usata e senza codice. Le categorie di sistema
non si toccano. Lo stesso nome è ammesso per un'entrata e un'uscita. Tag:
movimenti, totali, anni, rinomina senza doppioni, eliminazione se non usati.

Permessi: sezione `prima_nota`, Presidente e Tesoriere modificano, il
Revisore consulta tutto (anche esportazioni e giustificativi).

## 4. Implementazione tecnica

- **Importi** (`App\Support\Importo`): stessa euristica di `gestionale-acv`
  (bug 1.7.1): punto e virgola insieme = migliaia e decimali; solo virgola =
  decimali; solo punto con tre cifre = migliaia («1.450» = 1450); «1,450»
  rifiutato (ambiguo). Campo di testo con `inputmode="decimal"`: con
  `type="number"` in `gestionale-acv` un 23 è finito salvato come 22,96.
- **Saldi** calcolati al volo da `gl_prima_nota` (anticipi aperti esclusi,
  giroconti compresi); fotografati solo alla chiusura.
- **Audit**: `PrimaNota`, `PrimaNotaCategoria`, `PrimaNotaDocumento`,
  `Tag`, `AnnoContabile` con l'AuditObserver. `RiskMatrix`: chiusura e
  riapertura dell'anno alte, altre modifiche dell'anno medie; eliminazione di
  un movimento alta (già prima).
- **Attenzione per il futuro**: un model osservato non deve avere un metodo
  `anno()`: l'AuditObserver legge `$model->anno` e Laravel lo prenderebbe per
  una relazione, facendo fallire la scrittura dell'audit **in silenzio** (solo
  una riga nel log). Per questo il metodo è `PrimaNota::annoMovimento()`.
- Giustificativi su disco `local` in `prima-nota/<anno>/`, serviti con
  `X-Content-Type-Options: nosniff` e nome del file anche con accenti
  (RFC 6266); i file si cancellano dopo la transazione.
- Pulsante «Salva e inserisci un altro»: il valore viaggia in un campo
  nascosto, perché il pulsante si disattiva all'invio e un pulsante
  disattivato non manda il suo valore.

## 5. Conformità e sicurezza

- Criterio di cassa, rendiconto per l'Assemblea (MANUALE §2.4, `bilancio.md`):
  l'approvazione è il presupposto della chiusura dell'anno.
- Regime 398 dal 2028: la natura delle categorie è pronta
  (`flag_non_istituzionale`, `natura_da_verificare`); plafond e avviso
  contanti ≥ 1.000 € quando arriverà il periodo `l398`.
- Ogni correzione dopo la chiusura ha una motivazione nell'Audit Log, con
  valori prima e dopo.
- Esportazioni registrate; CSV protetto dalle formule.

## 6. Interazioni con altri moduli

- **Domande/Iscrizione** (`IscrizioneSocio`): controlla l'anno chiuso con
  `AnniContabili::chiuso`, crea la riga dell'anno e usa la categoria `QUOTE`
  (prima mancava: il movimento restava senza categoria).
- **Audit Log**: eventi `PRIMANOTA_*`, `ANNOCONTABILE_*`, `TAG_*`,
  `PRIMA_NOTA_ESPORTATA`, `PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO`,
  `SALDI_APERTURA_MODIFICATI`, `ANNO_CONTABILE_RIAPERTO`.
- **Scontrini** (v0.36.0): categoria `DA_CLASSIFICARE`, `scontrino_id`;
  **Anticipi** (v0.37.0): `sospeso`, `rimborsato_il`, già esclusi dai saldi.
- **Bilancio/Rendiconto** (da fare): voci per categoria; tag sulle voci del
  preventivo; approvazione collegata al verbale.
- **Azzeramento prima dell'apertura**: svuotare anche `gl_prima_nota`,
  `gl_prima_nota_documenti`, `gl_anni_contabili`, `gl_tag`, i fornitori di
  prova in `gl_soggetti` e `storage/app/private/prima-nota/`, **prima** di
  scrivere i saldi 2027: un movimento di prova del 2026 creerebbe la riga
  2026 e il 2027 non sarebbe più il primo anno.

## 7. Storia ed evoluzione

- 06/10/2026: analisi di `gestionale-acv` e del Gestionale APS
  (`claude/analisi-contabilita-esistente.md`), decisioni di Paolo, tavole
  PN-1…PN-9 approvate, codice v0.35.0.
- **Tre giri di revisione critica** prima della consegna:
  1. *Correttezza e sicurezza.* Prima ancora dei giri, i test hanno trovato
     il metodo `anno()` che bloccava in silenzio l'audit dei movimenti
     (§4) e i campi facoltativi del giroconto letti senza controllo. Nel
     giro: spesa anticipata aperta modificabile dal modulo normale (le
     avrebbe dato un conto), giustificativi aggiunti a un anno chiuso senza
     motivazione, nome del file con accenti che rompeva l'intestazione del
     download, CSV con descrizioni lette da Excel come formule. Corretti, con
     test (`test_revisione_giro_1`).
  2. *Coerenza e casi limite.* Un anno senza movimenti in mezzo staccava
     l'anno dopo dai saldi precedenti (ora si guarda l'ultimo anno
     registrato); l'avviso «saldi da impostare» compariva sfogliando anni
     passati mai usati. Corretti, con test (`test_revisione_giro_2_…`).
  3. *Interfaccia e regressioni* (1440 e 375 px, nessun `style=`): su
     telefono righe vuote «Entrata/Uscita/Stato» nelle schede dei movimenti
     (ora una riga «Importo» col segno) e schede delle categorie troppo
     lunghe (uso e natura solo se diversi dal solito); campi «motivazione»
     con lo stesso id in più finestre. Suite completa: 61 test superati.
