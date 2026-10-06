# Statuto — modifiche proposte per la v1.1 (domanda e voto del CD)

**Nota (05/10/2026):** la bozza 1.1 citata sotto è superata e tolta;
l'unico testo dello statuto è quello della B.A.S. (`statuto-bas-bozza.md`,
in questa cartella), che contiene queste modifiche. Il file resta come
storia.

**Stato:** recepite (v0.30.4, 04/10/2026) nella bozza
`statuto-1.1-bozza.md` / `.docx` / `ACV-Statuto-1.1.pdf` e nel testo
pubblicato dal gestionale (`resources/content/statuto/1.0.md`); restano da
portare al notaio o al commercialista con la revisione finale
**Origine:** decisioni di Paolo del 29/09/2026 sul modulo Voto CD
(`domande-iscrizione.md` §1 e §3, v0.30.0) e del 30/09/2026 sul termine
per la quota

Il gestionale segue queste regole dalla v0.30.0. Dal 04/10/2026 (richiesta
di Paolo: "unisci le modifiche allo statuto") il testo pubblicato sulla
pagina `/statuto`, quello che il richiedente accetta, è il testo completo
della bozza 1.1: queste modifiche più le clausole recepite dal modello
CSEN il 29/09/2026 (artt. 6.3, 11.3, 14, 16.7, 22.5, 24-bis, 31). Resta
numerato **1.0** per la regola "fino all'apertura i testi si correggono sul
posto con il numero di versione dell'avvio" (`domande-iscrizione.md` §7,
v0.30.3). All'adozione si decide se pubblicarlo come 1.0 (prima versione
pubblica) o 1.1; da quel momento ogni nuova versione avrà il suo file e
l'impostazione `statuto_versione_corrente`, senza toccare le precedenti.

Questa scheda resta come registro delle modifiche rispetto al testo
chiuso il 27/09/2026, con il motivo di ciascuna.

## Art. 6, comma 1 — domanda telematica valida con l'invio

**Testo v1.0:**
> Chi intende diventare socio presenta domanda al Consiglio Direttivo in
> forma scritta, anche per via telematica tramite il modulo pubblicato
> dall'Associazione. La domanda telematica è valida quando il richiedente
> la conferma attraverso il collegamento inviato all'indirizzo di posta
> elettronica indicato; l'Associazione conserva la prova dell'invio e
> della conferma (data, ora e dati tecnici) e trasmette al richiedente una
> ricevuta della domanda.

**Testo proposto:**
> Chi intende diventare socio presenta domanda al Consiglio Direttivo in
> forma scritta, anche per via telematica tramite il modulo pubblicato
> dall'Associazione. La domanda telematica è valida con l'invio del
> modulo completo delle dichiarazioni richieste; l'Associazione conserva
> la prova dell'invio (data, ora e dati tecnici) e trasmette all'indirizzo
> di posta elettronica indicato una ricevuta della domanda, con il
> collegamento per ritirarla.

Perché: la conferma dal link è stata tolta per semplificare (Paolo,
29/09/2026). Da sapere: senza la conferma nessuno verifica che
l'indirizzo sia del richiedente; la ricevuta con il link per ritirare la
domanda permette al titolare dell'indirizzo di accorgersene.

## Art. 6, comma 4 — termine, accoglimento d'ufficio, parità

**Testo v1.0:**
> Il Consiglio Direttivo delibera sulla domanda entro 5 giorni dalla sua
> conferma, quando l'Associazione invia al richiedente la ricevuta, anche
> con la procedura telematica dell'art. 16. Se alla scadenza del termine
> non ha votato la maggioranza dei componenti, o se i voti sono pari e il
> Presidente non ha votato, la domanda si intende accolta d'ufficio. Il
> verbale riporta l'esito di ciascuna domanda, il voto di ciascun
> consigliere e l'eventuale accoglimento d'ufficio.

**Testo proposto:**
> Il Consiglio Direttivo delibera sulla domanda entro 5 giorni dalla sua
> presentazione, anche con la procedura telematica dell'art. 16. Se alla
> scadenza del termine non ha votato la maggioranza dei componenti, la
> domanda si intende accolta d'ufficio. Se i voti espressi sono pari e il
> Presidente non ha votato, decide il Presidente. Il verbale riporta
> l'esito di ciascuna domanda, il voto di ciascun consigliere,
> l'eventuale accoglimento d'ufficio e l'eventuale decisione del
> Presidente.

## Art. 16, comma 4, lettera d — chiusura della consultazione

**Testo v1.0:**
> la consultazione si chiude alla scadenza del termine, o prima quando
> tutti hanno votato o quando l'esito non può più cambiare;

**Testo proposto:**
> la consultazione si chiude alla scadenza del termine, o prima quando
> tutti hanno votato;

Perché: con il quorum raggiunto si aspetta comunque la scadenza, perché
tutti i consiglieri possano votare. Il Presidente non chiude la
consultazione: interviene solo in caso di parità senza il suo voto (art.
6.4) e per scrivere la motivazione del diniego (Paolo, 29/09/2026).

## Art. 6, comma 6 — termine per il versamento della quota

**Testo v1.0:**
> L'accoglimento è comunicato al richiedente. L'ammissione si perfeziona
> con il versamento della quota associativa entro 30 giorni dalla
> comunicazione e con l'iscrizione nel libro dei soci.

**Testo proposto:**
> L'accoglimento è comunicato al richiedente. L'ammissione si perfeziona
> con il versamento della quota associativa, disposto entro 5 giorni dalla
> comunicazione, e con l'iscrizione nel libro dei soci.

Perché: termine più breve deciso da Paolo il 30/09/2026 (collaudo).
"Disposto": conta il giorno in cui il bonifico è ordinato, non quello
dell'accredito, che può arrivare 1–2 giorni lavorativi dopo. Nel
gestionale la decadenza automatica scatta solo dopo altri 5 giorni di
margine (`Domanda::GIORNI_MARGINE_DECADENZA`), per dare tempo
all'accredito e alla registrazione della segreteria.

## Art. 6, comma 3 — assenso del minore dai 14 anni

Già nella bozza 1.1 dal 29/09/2026 (modello CSEN), pubblicata il
04/10/2026:
> Il minore che ha compiuto quattordici anni presta personalmente il
> proprio assenso al tesseramento, che altrimenti non può avvenire (art. 16
> del D.Lgs. 36/2021).

Effetto sul gestionale: il modulo di domanda raccoglie l'assenso del minore
dai 14 anni dalla v0.31.0 (`domande-iscrizione.md` §3 e §7). Resta da
applicare ai rinnovi annuali del tesseramento.

## Nessuna modifica necessaria

- Art. 6, comma 5 (diniego motivato entro 10 giorni, appello entro 30):
  invariato.
- Art. 16, comma 4, lettere a, b, c, e, f: invariati. La lettera c
  ("in caso di parità prevale il voto del Presidente") resta la regola
  generale; per l'ammissione il caso del Presidente che non ha votato è
  regolato dall'art. 6.4 come modificato.
