# Importazione dei soci da Excel — analisi (13/10/2026)

Analisi prima del codice (Fase 0 del piano, `piano-2027.md` §2-ter). Serve
dopo l'Assemblea della B.A.S. (circa il 16/11/2026), con l'azzeramento: i
soci veri entrano tutti insieme dal foglio. Tavole IS-1…IS-3 consegnate a
parte (`Tavole-utenti-importazione.pdf`, non nel repository).

## 1. Materiale

- **Modello** `importazione-soci-modello.xlsx` (Paolo, 13/10/2026), letto:
  - foglio «Istruzioni»: come si compila, cosa succede al caricamento,
    riservatezza, tabella delle colonne;
  - foglio «Soci», 18 colonne (A–R): Cognome*, Nome*, Codice fiscale*,
    Indirizzo*, CAP*, Comune*, Provincia*, Email, Telefono, Data di
    ammissione*, Fondatore, Quota valida fino al, Genitore: cognome e nome,
    codice fiscale, relazione, telefono, email; Note;
  - riga 2 d'esempio («ROSSI (ESEMPIO: CANCELLARE)»), spazio fino alla
    riga 202;
  - controlli già nel foglio: codici fiscali di 16 caratteri, provincia di
    2, date dal 01/01/1950, Fondatore «Sì/No», relazione
    «padre/madre/tutore».
- **Dante**: `SociImportController` (allegato il 13/10/2026), letto per
  intero:
  - CSV o Excel; anteprima con i dati tenuti in sessione;
  - doppioni per codice fiscale o per cognome e nome, saltati;
  - una transazione e un evento HIGH nell'Audit Log;
  - anomalie mostrate **dopo** l'importazione.
  - Da non riprendere:
    - nessun controllo bloccante prima della conferma;
    - il foglio letto è quello attivo, non quello per nome;
    - il file resta nella cartella temporanea di PHP;
    - colonne diverse dalle nostre.
  - Da riprendere: lettura delle date di Excel (numero di serie o testo),
    CAP con lo zero davanti, codifica.
- **Gestionale ASD**: «Carica tessere già emesse» (`TessereController`
  `carica*`, `CaricoTessereCsen`):
  - file in `storage/app/private/tmp/tessere/`, percorso in sessione;
  - anteprima, rilettura alla conferma, file cancellato a conferma o
    annullamento;
  - pulizia dei file più vecchi di un giorno.

  È lo schema da seguire.

## 2. Decisioni già prese (13/10/2026)

- Funzione come «Carica tessere già emesse»:
  - anteprima con gli errori riga per riga;
  - dati di nascita dal codice fiscale;
  - conferma; file cancellato dopo;
  - soci `importato`; Audit Log.
- Privacy e foto/video **non** si importano: arrivano con il link
  personale.
- Le tessere CSEN non si importano qui (carico dal file del portale CSEN).
- **Riga d'esempio riconosciuta dal contenuto**, non dalla posizione: è
  scartata la riga con «ESEMPIO» nel cognome, ovunque sia. Se Paolo l'ha
  cancellata, la riga 2 è un socio come gli altri.
- **«Quota valida fino al» = quota nel vecchio gestionale**: nessun
  movimento in Prima Nota e **nessuna ricevuta**.
- **PhpSpreadsheet** in `composer.json`. Estensioni PHP necessarie: zip,
  xml, xmlreader, gd, mbstring. Sono già quelle di Laravel e di DomPDF; da
  verificare sul server con `php -m` (`deploy.md` §6).

## 3. Scelte proposte

### 3.1 Chi e dove

- Pagina **Soci › Importa da Excel**, per chi **modifica la sezione Soci**
  (Presidente e Segretario). Pulsante nell'elenco dei soci, accanto alle
  esportazioni.
- Link «Scarica il modello» al file vuoto (il modello di Paolo senza la
  riga d'esempio compilata, in `resources/modelli/`).

### 3.2 Lettura del file

- Solo `.xlsx`, al massimo 2 MB; foglio letto **per nome** («Soci»). Se
  manca: «Il file non è il modello dei soci: manca il foglio Soci».
- Intestazioni della riga 1 confrontate con quelle attese, togliendo
  l'asterisco e senza badare a maiuscole e spazi. Se non corrispondono:
  «Le colonne non sono quelle del modello (colonna E: atteso "CAP", trovato
  "…")».
- Righe completamente vuote ignorate; al massimo 500 righe di dati.
- Valori ripuliti: spazi all'inizio e alla fine, a capo dentro la cella,
  codice fiscale e provincia in maiuscolo, CAP numerico riportato a 5
  cifre (5014 → 05014).
- Date: numero di serie di Excel oppure testo `gg/mm/aaaa`. Altro: errore.

### 3.3 Controlli riga per riga

Ogni riga ha uno stato: **errore** (blocca la conferma), **avviso** (non
blocca) oppure pronta.

Errori:

| Campo | Controllo |
|---|---|
| Cognome, Nome, Indirizzo, Comune | obbligatori |
| Codice fiscale | obbligatorio; valido (`CodiceFiscale::analizza`: formato, carattere di controllo, data, comune o Stato riconosciuto) |
| Codice fiscale | non già presente tra i soci, **eliminati compresi** (per un eliminato: «c'è una scheda eliminata: ripristinala dai Filtri»); non ripetuto nel file (errore su tutte le righe coinvolte) |
| CAP | 5 cifre |
| Provincia | 2 lettere |
| Email e Telefono | obbligatori per i maggiorenni (età dal codice fiscale **alla data del caricamento**); email in formato valido quando c'è |
| Data di ammissione | obbligatoria; non nel futuro; non prima della nascita |
| Fondatore | vuoto, «Sì» o «No» |
| Quota valida fino al | vuoto oppure **31/12** di un anno; non prima dell'anno di ammissione |
| Genitore (5 colonne) | obbligatorie per i minorenni; codice fiscale del genitore valido e di un maggiorenne; relazione padre, madre o tutore; email valida |

Avvisi:

- stesso cognome e nome di un socio già presente, con un altro codice
  fiscale (possibile omonimia: «Esiste già Mario Rossi (RSSMRA…): è
  un'altra persona?»);
- il codice fiscale non corrisponde a cognome e nome
  (`corrispondeANominativo`): capita con i doppi cognomi, quindi solo
  avviso;
- dati del genitore compilati per un maggiorenne: si ignorano;
- quota scaduta (fine prima di oggi): si registra lo stesso, il socio
  risulta con la quota scaduta.

Solo con **zero errori** il pulsante «Importa N soci» si accende. Come
dicono le Istruzioni del modello: si corregge nel foglio e si ricarica.

### 3.4 Anteprima (tavola IS-2)

- Riepilogo: righe lette, pronte, con avvisi, con errori, riga d'esempio
  scartata.
- Tabella: n. di riga del foglio (come in Excel, per trovarla subito),
  cognome e nome, codice fiscale, nascita ricavata (data, luogo, sesso),
  età e tipo (Ordinario o Minorenne), ammissione, quota fino al, esito
  con i messaggi.
- Filtro «solo righe con errori o avvisi».
- Su telefono: una scheda per riga.
- Il file resta in `tmp/soci-import/` fino a conferma o annullamento. La
  pagina si può ricaricare: l'anteprima si rilegge dal file.

### 3.5 Conferma

- **Rilettura e ricontrollo completi** al momento della conferma: nel
  frattempo qualcuno può aver inserito un socio con lo stesso codice
  fiscale.
- Una transazione: o tutti i soci o nessuno.
- Per ogni riga, un socio:
  - stato `attivo`, `importato` vero, `inserito_da` chi importa;
  - `tipo` `minorenne` sotto i 18 anni, altrimenti `ordinario` (come
    l'iscrizione da domanda, `IscrizioneSocio`);
  - `fondatore`;
  - data e luogo di nascita e sesso dal codice fiscale;
  - residenza, email, telefono;
  - `data_prima_iscrizione` = data di ammissione, `anno_iscrizione` = suo
    anno;
  - genitore nei campi `tutore_*` (solo per i minorenni);
  - note;
  - privacy vuota (stato «mancante»: arriva con il link).
- **Quota**: `quota_scadenza` = «Quota valida fino al». Da decidere il caso
  della quota che arriva **oltre l'avvio della contabilità** (vedi 3.6).
- Audit Log: una riga per socio (lo fa già l'osservatore di `Socio`), più
  una riga riassuntiva `IMPORT_SOCI` (HIGH: entrano dati personali in
  blocco senza domanda) con numero di soci, nome del file e chi ha
  importato, mai il contenuto.
- File cancellato subito dopo, anche se la conferma fallisce. Pulizia
  giornaliera dei file più vecchi di un giorno (come le tessere).
- Pagina di esito (IS-3):
  - «Importati N soci»;
  - collegamento all'elenco filtrato per «Importati oggi»;
  - promemoria dei passi successivi: link privacy a tutti, tessere CSEN dal
    file del portale.

### 3.6 Quota oltre l'avvio della contabilità (da decidere: D4)

Il caso esiste. Chi entra fra ottobre e dicembre 2026 con la promozione
paga nel vecchio gestionale una quota valida fino al **31/12/2027**. Il
gestionale ricava la situazione 2027 (in regola, in attesa, sospeso) dalle
quote registrate in `gl_rinnovi`, non da `quota_scadenza`. Senza una riga,
quel socio a febbraio 2027 risulterebbe «sospeso» e la tessera chiederebbe
conferma.

- **(a) consigliata**: per ogni anno da quello d'avvio (2027) fino a «Quota
  valida fino al», una riga in `gl_rinnovi`:
  - senza ricevuta e senza movimento;
  - importo della quota del tipo (70 € o 30 €);
  - data di pagamento uguale alla data di ammissione o, se più recente, al
    1° ottobre dell'anno prima;
  - nota «Quota incassata nel vecchio gestionale (importazione)».

  È lo stesso trattamento della quota 2027 versata a dicembre 2026
  (`rinnovi-quota.md`), ma senza ricevuta. Dove oggi si mostra «Ricevuta
  N del …» compare «Quota del vecchio gestionale». Gli anni prima del 2027
  restano solo in `quota_scadenza`, che il gestionale tratta già come
  «vecchio gestionale».
- **(b)**: solo `quota_scadenza`, e la situazione dell'anno impara a
  leggere anche quella. Tocca più logica (Rinnovi quota, Tessere,
  promemoria «Quote non versate») e lascia vuoto l'elenco dei rinnovi 2027
  per quei soci.

## 4. Effetti fuori dal codice

- `soci.md` (nuova sezione), `ui.md`, `MANUALE-ASD.md`, `deploy.md` §6
  (estensioni, `composer install`).
- Azzeramento (piano, dopo l'Assemblea): prima l'azzeramento, poi
  l'importazione, poi il CD vero, poi le tessere dal CSV.
- `da-riportare-gestionale-aps.md`: controlli bloccanti prima della
  conferma, foglio per nome, file cancellato (difetti del controller del
  Dante).
- Collaudo: blocco **O**, con un foglio di prova con dati di fantasia
  (codici fiscali validi inventati) in `tests/fixtures/`, mai dati veri.

## 5. Da decidere (risolto in §7)

- **D4**: quota oltre il 2026, righe senza ricevuta (a, consigliata) o
  solo `quota_scadenza` (b).
- **D5**: importano Presidente e Segretario (chi modifica Soci,
  consigliato) o solo il Presidente, come «percorso eccezionale» del
  Dante.

## 7. Decisioni di Paolo (13/10/2026)

- **D4** (15:16): per la quota oltre il 2026, una riga in Rinnovi quota per
  il 2027 **senza ricevuta né movimento**.
- **D5** (15:16): importano **Presidente e Segretario**.
- Riga d'esempio riconosciuta dal contenuto, «Quota valida fino al» senza
  ricevuta, PhpSpreadsheet (14:35): approvati. Per PhpSpreadsheet vedi però
  §8.

## 8. Realizzazione (v0.40.0, 13/10/2026)

- **Niente PhpSpreadsheet.** Dalla sessione di sviluppo Packagist non è
  raggiungibile (il proxy lo blocca), e sul server sarebbe servito un
  pacchetto in più. Al suo posto `App\Support\LettoreXlsx`, un lettore
  minimo:
  - un `.xlsx` è uno ZIP di file XML, quindi bastano ZipArchive e SimpleXML,
    già presenti per Laravel e DomPDF;
  - legge un foglio per nome, le stringhe condivise, il testo in linea, i
    numeri, le date in numero di serie (sistema 1900 e 1904) e in ISO;
  - massimo 20 MB per parte decompressa, XML senza rete.

  Nessun `composer` da lanciare sul server.
- `App\Services\ImportazioneSoci`: `leggi()` (anteprima) e `importa()`
  (rilettura, ricontrollo, transazione, `IMPORT_SOCI` HIGH):
  - **Prima di leggere le righe**: intestazioni confrontate senza asterischi,
    riga d'esempio scartata, righe con il solo «Fondatore» ignorate (es.
    «No» copiato giù per la colonna).
  - **Pulizia dei valori**: CAP numerico riportato a 5 cifre, telefono
    numerico senza «.0».
  - **Controlli** come in §3.3.
- **Quote**: `quota_scadenza` = «Quota valida fino al».
  - Per ogni anno dall'avvio della contabilità (2027) una riga in
    `gl_rinnovi` senza numero di ricevuta, `metodo_pagamento = altro`, con la
    nota «Quota incassata nel vecchio gestionale (importazione dei soci)…».
    Importo della categoria, data di pagamento uguale alla data di
    ammissione (al più presto il 1° ottobre dell'anno prima).
  - `Rinnovo::senzaRicevuta()` e `riferimento()`. Elenco dei rinnovi, quote
    nella scheda, finestra di annullamento e situazione della tessera
    mostrano «quota 2027 del vecchio gestionale», senza scaricare né inviare
    ricevute (`rinnovi.invia` risponde 422).
  - Annullare una quota importata la toglie. In `aggiornaScadenza`, per un
    socio importato senza altre quote valide, resta la quota del vecchio
    gestionale, al più fino al 31/12 dell'anno prima dell'avvio.
- `SociImportaController`, viste `soci/importa` (IS-1, IS-2) e
  `soci/importa-fatto` (IS-3). Pulsante «Importa da Excel» nell'elenco dei
  soci. Filtro `importati=AAAA-MM-GG` per «Vedi i soci importati».
- Modello scaricabile: `resources/modelli/importazione-soci-modello.xlsx`
  (il file di Paolo del 13/10/2026, con la riga d'esempio).
- File in `storage/app/private/tmp/soci-import/`, cancellato a conferma o
  annullamento; `tessere:pulisci` toglie anche questi dopo un giorno.
- Test: `ImportazioneSociTest` (6 prove) con `tests/fixtures/soci-buoni.xlsx`
  e `soci-errati.xlsx` (dati e codici fiscali inventati).

### Tre giri di revisione critica (13/10/2026)

1. **Primo giro**:
   - `mimes:xlsx` rifiuta i file veri quando il server riconosce il .xlsx
     come «zip»: si controlla l'estensione e il contenuto lo verifica il
     lettore;
   - nel riepilogo «1 minorenni»: singolare e plurale.
2. **Secondo giro**:
   - una riga con soli spazi o con il solo «No» in Fondatore diventava un
     socio pieno di errori: ora è ignorata;
   - annullare la quota 2027 importata azzerava anche la quota 2026 del
     vecchio gestionale (`aggiornaScadenza` guardava solo i rinnovi):
     corretto.
3. **Terzo giro**:
   - i file salvati da un vecchio Excel per Mac usano le date dal 1904
     (sarebbero risultate spostate di quattro anni): il lettore lo
     riconosce dal file;
   - aggiunte le celle data in formato ISO.
