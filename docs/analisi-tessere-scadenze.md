# Tessere CSEN, affiliazione e polizza RC — analisi (07/10/2026)

Analisi prima del codice (prossima versione, v0.37.0). Tavole sulla tela
«Gestionale ASD — Riferimento UI», sezione «Tessere CSEN e scadenze
dell'associazione» (TS-1…TS-5, SC-1, PR-3); PDF in
`docs/mockup/Tavole-tessere-scadenze.pdf`.

## 1. Cosa ha detto Paolo (07/10/2026)

- Dalla quota di ogni socio vanno **4 € al CSEN** (l'EPS a cui la
  Compagnia è affiliata).
- L'**affiliazione** al CSEN ha un costo annuale con una scadenza di
  pagamento.
- Le **tessere** si comprano **a pacchetti** e poi si assegnano ai soci.
  - Il numero è **noto all'acquisto**: è un intervallo stampato.
  - La tessera va a **tutti i soci**.
  - Il **prezzo dipende dal tipo** di tessera (base, silver, gold…).
- La quota della Compagnia segue l'**anno solare**; la tessera del CSEN
  vale **un anno dall'emissione** e si rinnova dopo 12 mesi.
- Non si vuole emettere tutte le tessere insieme a gennaio.
- La **polizza RC** è Unipol, stipulata tramite CSEN, e **scade a luglio**
  di ogni anno.

## 2. Scelte proposte

1. **Quota intera, costi separati (criterio di cassa).** La quota entra
   intera (70 €, ricevuta da 70 €). I 4 € sono un'uscita
   «Tesseramento Soci EPS» quando si compra il pacchetto, con il prezzo
   del tipo di tessera. Il collegamento lo fa il conteggio delle tessere:
   comprate, emesse, disponibili. Nell'elenco dei rinnovi si aggiunge la
   «quota netta» dell'anno (incassato meno il costo delle tessere emesse).
2. **Pacchetti di tessere** (nuova tabella), con:
   - data d'acquisto e tipo;
   - intervallo di numeri (dal… al…) e quantità;
   - prezzo unitario e totale;
   - movimento di Prima Nota collegato (uscita «Tesseramento Soci EPS») e
     giustificativo.
3. **Tessere emesse**: si riusa `gl_tesseramenti` con la data di emissione
   e la **scadenza** (un anno dopo), il pacchetto e il tipo.
   - Il numero proposto è il primo libero del pacchetto di quel tipo; si
     può cambiare, ma non con un numero già usato o fuori dai pacchetti.
   - Emissione dalla pagina Tessere, dalla scheda del socio e, per un
     socio nuovo, subito dopo la registrazione della quota.
4. **Rinnovo della tessera dopo 12 mesi**:
   - elenco «in scadenza» (30 giorni) con il pulsante «Rinnova»;
   - promemoria interno al Tesoriere N giorni prima di ogni scadenza (la
     modalità «prima o dopo una data del destinatario», già prevista nei
     Promemoria);
   - regola: si rinnova a chi è in regola con la quota o è ancora nel
     termine per versarla (fino al 30 aprile). A chi è da dichiarare
     decaduto il gestionale lo segnala; la decisione resta all'operatore.
5. **Tessera scaduta = niente assicurazione base**: badge «Tessera
   scaduta» nell'elenco soci, nei rinnovi e nella scheda del socio;
   «Soci senza tessera» nella pagina Tessere.
6. **Affiliazione e polizza RC = scadenze dell'associazione**:
   - si usa lo Scadenzario (`gl_scadenzario`, ricorrenza annuale e
     preavviso) con una pagina semplice;
   - «Rinnovata» registra il pagamento in Prima Nota («Affiliazione EPS»
     o «Polizze Assicurative», con il giustificativo) e sposta la
     scadenza all'anno dopo;
   - la scadenza dell'affiliazione si scrive anche nella scheda
     Associazione (`rete_affiliazione_scadenza`).
7. **Promemoria**, due funzioni nuove, entrambe «N giorni prima» e con
   Presidente e Tesoriere come destinatari (interni, non soci):
   - «Scadenze dell'associazione»;
   - «Tessere in scadenza».
8. **Permessi**:
   - tessere: oggi la sezione Tesseramento è di Presidente e Segretario,
     si aggiunge il Tesoriere (compra i pacchetti);
   - Scadenzario: si aggiunge il Tesoriere (paga affiliazione e polizza);
   - Revisore in sola lettura.

## 3. Da confermare

- **Mese di scadenza dell'affiliazione al CSEN**: la risposta «luglio» si
  riferisce alla polizza RC; vale anche per l'affiliazione?
- **Tipi di tessera e prezzi** del listino CSEN 2027 (oggi noto solo «4 €»
  per la base).
- **Prima emissione**: le tessere dei soci attuali ancora valide si
  caricano con il loro numero e la data di emissione (scadenza calcolata),
  così i rinnovi partono dalle date giuste.
