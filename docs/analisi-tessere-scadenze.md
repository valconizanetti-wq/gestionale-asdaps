# Tessere CSEN, affiliazione e polizza RC — analisi (07/10/2026)

Analisi prima del codice (prossima versione, v0.37.0). Tavole sulla tela
«Gestionale ASD — Riferimento UI», sezione «Tessere CSEN e scadenze
dell'associazione» (TS-1…TS-5, SC-1…SC-3, PR-3); PDF in
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
   - **Dove si vede l'EPS** (domanda di Paolo, 07/10/2026): oggi solo il
     nome, nel profilo dell'ente per periodi («Ente di affiliazione»); i
     campi `rete_affiliazione_*` di `gl_associazione` non hanno pagina.
     Approvato da Paolo: in Impostazioni › Ente una riga nuova
     «Affiliazione e assicurazione» con due riquadri (tavole SC-2, SC-3):
     - **Affiliazione all'EPS**: ente (dal periodo), codice di
       affiliazione, comitato/referente e contatto, scadenza, ultimo
       rinnovo con link al movimento, stato (valida, in scadenza, scaduta);
     - **Polizza RC**: compagnia (Unipol tramite CSEN), numero, copertura,
       scadenza, ultimo rinnovo, stato (campi nuovi in `gl_associazione`);
     - la scadenza **non si scrive lì**: la tiene lo Scadenzario, e
       «Rinnovata» aggiorna anche il riquadro (un solo dato);
     - modifica solo del Presidente; Tesoriere e Segretario vedono;
     - affiliazione scaduta: avviso rosso nel riquadro, in Dashboard e
       nello Scadenzario (niente tessere nuove o rinnovate, RASD a
       rischio).
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

## 4. Decisioni e risposte (07/10/2026)

- **Tavole approvate** da Paolo: TS-1…TS-5, SC-1…SC-3, PR-3.
- **Tipi e prezzi**: per il momento solo **Silver Base a 4 €**
  (`TesseraTipoSeeder`); gli altri si aggiungono dalla pagina Tessere.
- **Tessere già emesse**: Paolo ha mandato l'elenco esportato dal portale
  CSEN (30 tessere, CSV con «;»). Non si mette nel codice: diventa la
  funzione «Carica tessere già emesse» (anteprima, abbinamento per codice
  fiscale, conferma), da usare sul server.
- **Cosa mostra l'elenco** (da verificare con Paolo):
  - i numeri sono prefisso di stagione + progressivo e il progressivo
    continua al cambio di stagione (`26B2229057` → `27B2229058`): nel
    gestionale un numero è «usato» per il suo progressivo;
  - una **Gold Base** sta nell'intervallo delle Silver: se i pacchetti CSEN
    sono misti, il tipo va sulla tessera e non sul pacchetto.
- **Scostamento dalla tavola TS-2**: i tipi si aggiungono e si prezzano
  dalla pagina Tessere, non da Impostazioni (solo del Presidente), perché i
  pacchetti li compra il Tesoriere.
- **Prima del 2027** la quota non è nel gestionale: nella finestra «Quota
  nel vecchio gestionale», senza «Rinnova lo stesso».
- **Divisione confermata**: v0.37.0 tessere (fatta, scheda `tessere.md`);
  v0.38.0 Scadenzario (SC-1), riquadri Affiliazione e Polizza RC (SC-2,
  SC-3), promemoria «N giorni prima» (PR-3). Per la v0.38.0 resta da sapere
  il **mese di scadenza dell'affiliazione CSEN**.

## 5. Risposte di Paolo (07/10/2026, 15:19)

- **Affiliazione CSEN: scade il 31/12** di ogni anno (anno solare). Nello
  Scadenzario della v0.38.0: «Affiliazione CSEN», 31/12, ricorrente, preavviso
  30 giorni; la polizza RC resta a luglio.
- **Tessere: solo Silver Base.** Niente pacchetti misti da gestire: un tipo per
  pacchetto va bene. La Gold Base arrivata dal CSV resta come tipo della
  tessera caricata (storico), senza prezzo; si può togliere la spunta «In uso».
- **Novità: tesserino tecnico sportivo degli istruttori.** Va gestito,
  insieme a chi sono tecnici e istruttori. Base esistente: `gl_tecnici`
  (socio della Compagnia o nominativo esterno), qualifica `tecnico` in
  `gl_qualifiche`, `tecnico_riferimento_id` sulla tessera; nell'elenco CSEN la
  colonna ISTRUTTORE (es. «Paolo Zanetti») oggi finisce nelle note della
  tessera. Analisi da fare, con le domande per Paolo:
  1. che cos'è il tesserino (qualifica CSEN di Istruttore/Tecnico, livello,
     disciplina), con numero, data di rilascio e **scadenza** (annuale?);
  2. per rinnovarlo servono aggiornamenti o crediti formativi? Costo e chi
     paga (uscita in Prima Nota della Compagnia o del tecnico)?
  3. gli istruttori sono sempre soci, o ci sono esterni?
  4. cosa deve fare il gestionale: elenco dei tecnici con qualifica e
     tesserino, scadenze con promemoria (come le tessere), documento
     allegato, abbinamento con i soci seguiti (colonna ISTRUTTORE del CSEN)?
  5. serve anche per il RASD (tecnici dichiarati) dal 2028?
  Proposta: farlo nella v0.38.0 insieme allo Scadenzario (stessa logica di
  scadenze e promemoria «N giorni prima»), dopo tavole approvate.
