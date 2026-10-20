# Registro dei moduli e anticipi con note di rimborso — analisi (20/10/2026)

Analisi prima delle tavole e del codice. Sono i due lavori anticipati dalla
Fase 1 (`piano-2027.md` §2), da collaudare entro fine novembre: dal 1°
dicembre si fanno solo correzioni. Copia nel progetto:
`claude/analisi-moduli-anticipi.md`.

Materiale letto:
- `analisi-impianto-asd-aps.md` §3 C5, §7 (moduli da accendere e spegnere)
  e §8;
- `specifiche/prima-nota.md` §3, §4 e §7 e `analisi-contabilita-esistente.md`
  §3 (anticipi del Dante, descritti, **il codice non c'è**: lo zip del
  13/10 non è più allegato, e `gestionale-core` non ha la logica);
- `config/navigazione.php`, `ProfiloEnte::haModulo()`, le rotte, la
  pianificazione (`routes/console.php`), `AnniContabili::controlliChiusura()`;
- lo schema di `gl_prima_nota` (colonne degli anticipi già presenti dal
  22/09/2026, mai usate da una pagina);
- **le tavole della Prima Nota approvate il 06/10/2026** (PN-1, PN-3, PN-7),
  ritrovate dopo la prima stesura: la parte 2 ora le segue (§2.3).

---

# Parte 1 — Registro dei moduli

## 1.1 Cosa c'è oggi

- **Moduli per legge**: `ProfiloEnte::haModulo('rasd' | 'l398' | 'runts')`
  dal periodo dell'ente in vigore; la voce `'se'` della navigazione nasconde
  «Compliance RASD».
- **Voci del menu**: compaiono se la rotta esiste (modulo costruito) e il
  ruolo vede la sezione. Così i moduli non ancora costruiti (Bilancio,
  Assemblee, Verbali, Progetti, Volontari, Comunicazioni, Archivio, GDPR)
  oggi non si vedono.
- **Nessun interruttore**: un modulo costruito è sempre acceso. Le uniche
  impostazioni simili sono `iscrizioni_aperte` e `scontrini_split_abilitato`.
- **Comandi pianificati** sempre attivi: `domande:scadenze`,
  `promemoria:invia`, `tessere:pulisci`, `audit:verifica-integrita`.

## 1.2 Proposta

Un **registro unico**, `config/moduli.php`, letto da menu, rotte, comandi
pianificati e promemoria. Ogni modulo ha:
- codice, nome, descrizione di una riga;
- livello: `base`, `facoltativo`, `legge`;
- dipendenze (moduli che devono essere accesi);
- sezioni dei permessi che lo compongono (`gl_sezioni`);
- comandi pianificati e funzioni dei promemoria che gli appartengono.

Classificazione dei moduli costruiti (tavola MO-1):

| Modulo | Livello | Sezioni | Note |
|---|---|---|---|
| Soci, Rinnovi quota, Prima Nota, Consiglio Direttivo, Scadenzario, Promemoria, Audit Log, Privacy, Utenti, Impostazioni | **base** | … | sempre accesi |
| Domande online (con il voto del CD) | facoltativo | `domande` | spento: il modulo pubblico dice «iscrizioni non disponibili»; i soci si inseriscono dalla scheda |
| Tessere EPS (Tesseramento) | facoltativo | `tesseramento` | con le sue funzioni dei promemoria |
| Scontrini con OCR | facoltativo | `scontrini` | dipende da Prima Nota |
| **Anticipi e note di rimborso** (parte 2) | facoltativo | dentro `prima_nota` | «Pagato da me» negli scontrini solo se è acceso anche questo |
| Compliance RASD, plafond 398, RUNTS | legge | … | dal profilo dell'ente, come oggi: non si spengono a mano |

I moduli futuri (Progetti, Volontari, Riconciliazione, Cassa mensile,
Comunicazioni…) entrano nel registro quando si costruiscono.

## 1.3 Regole

1. **Chi**: accende e spegne il Presidente o l'Amministratore, in
   Impostazioni › Moduli (tavola MO-1). Gli altri con la sezione
   `impostazioni` vedono la pagina in sola lettura.
2. **Spegnere non cancella**. La sezione sparisce dal menu, le pagine non si
   aprono (messaggio «Il modulo … è spento», non un 404), i comandi
   pianificati e le funzioni dei promemoria del modulo si fermano.
3. **Se ci sono dati**, il modulo spento resta **consultabile in sola
   lettura** da Presidente, Revisore e Amministratore, con una fascia in
   cima «Modulo spento: sola consultazione».
4. **Dipendenze**: non si accende un modulo se manca una dipendenza, e non
   si spegne un modulo da cui ne dipende un altro acceso. La pagina dice
   quale.
5. **Spegnere con lavoro in corso**: la finestra di conferma elenca che cosa
   resta in sospeso. Per esempio: domande in attesa del voto, anticipi da
   rimborsare, scontrini da quadrare. Si può spegnere lo stesso.
6. **Audit Log**: `MODULO_ACCESO` e `MODULO_SPENTO`, rischio alto, quindi
   email agli altri (AvvisiOperazioni).
7. **Moduli per legge** nella stessa pagina, senza interruttore, con il
   motivo: «Acceso perché l'ente è A.S.D. dal …».

## 1.4 Realizzazione

- `App\Services\Moduli`: `attivo($codice)`, `elenco()`, `accendi()`,
  `spegni()`. Lo stato dei facoltativi sta in `gl_impostazioni`
  (`modulo_<codice>`, vero o falso, predefinito acceso). I moduli per legge
  chiamano `ProfiloEnte::haModulo()`.
- Middleware `modulo:<codice>` sui gruppi di rotte, con la regola della sola
  lettura (§1.3 punto 3).
- `config/navigazione.php`: la voce `'se'` diventa `'modulo'` e vale per
  tutti i livelli.
- Pianificazione: `->when(fn () => Moduli::attivo('domande'))`. Promemoria:
  ogni funzione dichiara il suo modulo.
- Test: tutti i moduli accesi, più due configurazioni tipo (A.S.D. in 398,
  APS con RUNTS) e un test per modulo spento (menu, rotte, comandi).
- Azzeramento: le impostazioni `modulo_*` restano (gruppo TIENI).

---

# Parte 2 — Anticipi e note di rimborso

## 2.1 Il caso

Un socio (di solito un membro del CD) paga di tasca propria una spesa della
Compagnia: colla alla ferramenta, la stampa delle locandine. La Compagnia
gli restituisce i soldi più tardi, in contanti o con un bonifico. Serve:
- che la spesa sia registrata subito, con il giustificativo;
- che **non** tocchi i saldi di cassa e banca finché non è rimborsata
  (criterio di cassa: i soldi della Compagnia non sono ancora usciti);
- un documento per il rimborso, la **nota di rimborso**, firmato da chi ha
  anticipato, che giustifica l'uscita di cassa o il bonifico;
- che nessuna spesa sia rimborsata due volte.

## 2.2 Cosa c'è oggi

- `gl_prima_nota` ha già, mai usate: `sospeso`, `rimborsato_il`,
  `anticipato_da` (testo), `tipo_anticipante` (`cd`/`esterno`),
  `rimborso_nota_numero`, `data_nota`, `iban_rimborso`. Lo schema del Dante
  mette la nota **su ogni movimento**, quindi una nota con più spese si
  riconosce solo dallo stesso numero.
- I saldi escludono già gli anticipi aperti (scope `effettivi`); la Prima
  Nota ha l'indicatore «Anticipi da rimborsare» e il filtro; un anticipo
  aperto non si modifica dal modulo normale.
- La chiusura dell'anno è bloccata se ci sono anticipi aperti.
- Il Dante: nota `RIMB-ANNO-NNN` in due fasi (emessa, pagata) e registro
  degli annullamenti (`gs_rimborsi_annullati`).
- `gestionale-acv`: categorie «Anticipo spese» e «Recupero anticipi»,
  disattivate (il vecchio modo, da non riprendere).

## 2.2-bis Il codice del Dante (letto il 20/10/2026)

Materiale allegato da Paolo il 20/10/2026: `Controllers_SDA.zip`,
`Models_SDA.zip`, `Services_SDA.zip`, `Middleware_SDA.zip`,
`views_SDA.zip` (questo contiene solo le viste del bilancio: mancano
`prima-nota/rimborsa` e `nota-rimborso-pdf`; lette poi in §2.8).

Come funziona (`PrimaNotaController`: `store`, `rimborsa`, `generaNota`,
`confermaPagamento`, `annullaNota`, `notaRimborsoPdf`; `PrimaNota`:
`isPendente`, `hasNotaEmessa`, `isRimborsato`, `scopeEffettivi`, `saldi`):
1. **Spesa anticipata**: spunta `sospeso` nel movimento; tipo forzato a
   uscita, conto vuoto; chi ha anticipato in `anticipato_da` (testo) con
   `tipo_anticipante` `cd` o `esterno`.
2. **Fase 1, la nota**: dalla spesa si apre «Rimborsa»; si possono
   aggiungere le altre spese aperte della stessa persona. «Genera» assegna
   `RIMB-ANNO-NNN` (anno dalla data della nota) a tutte le spese, salva la
   data della nota e apre il PDF da stampare e far firmare. L'IBAN è
   obbligatorio per un esterno.
3. **Fase 2, il pagamento**: data e conto; tutte le spese della nota
   ricevono `rimborsato_il` e il conto, ed entrano nei saldi.
4. **Annullamento** di una nota non pagata, con motivo: le spese tornano
   aperte e una riga va in `gs_rimborsi_annullati` (numero, anticipante,
   totale, numero di spese, motivo, chi e quando).

**Si riprende**: la spunta nel movimento, l'accorpamento delle spese della
stessa persona in una nota, il numero `RIMB-ANNO-NNN`, le due fasi (nota,
pagamento), l'annullamento motivato con il suo registro, il PDF.

**Si migliora** (difetti trovati nel codice):
1. **Il numero non è protetto.** Si calcola come il più alto già usato,
   senza lock (due note insieme possono avere lo stesso numero). Inoltre
   l'annullamento toglie il numero dalle spese: se la nota annullata era
   l'ultima, la nota dopo **riprende lo stesso numero**, e il registro degli
   annullamenti e la nota nuova hanno lo stesso numero. Rimedio: tabella
   delle note con vincolo unico e lock; una nota annullata resta una riga
   «annullata».
2. **Chi ha anticipato è un testo libero**, e le spese si accorpano solo
   se il testo è identico («Luigi Tascio» e «L. Tascio» sono due persone).
   Rimedio: il socio (A4).
3. **Nessuna traccia nell'Audit Log** della data della nota e
   dell'annullamento, scritti con `DB::table` (fuori dall'observer).
   Rimedio: model osservati, eventi propri, rischio alto per pagamento e
   annullamento.
4. **Il pagamento** non è in una transazione, non controlla l'anno chiuso
   né che la data venga dopo quella della nota, e lascia il movimento alla
   data della spesa: il saldo di un giorno già passato cambia quando si
   paga. Rimedio: transazione, controlli, data del pagamento (A1).
5. **La firma resta fuori**: il messaggio dice di stamparla e farla
   firmare, ma la copia firmata non si carica. Rimedio: passo 2 di PN-7.
6. **Il PDF si rigenera** a ogni apertura dai dati del momento: se cambia
   una descrizione, la nota firmata e quella del gestionale non sono più
   uguali. Rimedio: il PDF si salva alla generazione.
7. **Un pagamento sbagliato non si annulla.** Rimedio: annullamento motivato
   del pagamento.
8. Le note della nota si accodano alle note del movimento; l'IBAN si salva
   su ogni spesa. Rimedio: note e IBAN sulla nota.

Da riportare al Gestionale APS quando il modulo sarà fatto
(`da-riportare-gestionale-aps.md`): i punti 1, 3, 4 e 6.

## 2.3 Proposta

Segue le tavole approvate il 06/10/2026:
- **PN-3**, nuovo movimento con la spunta «Anticipata da una persona»;
- **PN-7**, rimborso in tre passi: nota, firma, pagamento, con il numero
  `RIMB-2027-001` come al Dante.

**Una tabella per la nota** (`gl_note_rimborso`), che raccoglie una o più
spese della stessa persona. Le colonne del Dante sul movimento si
sostituiscono con:
- `anticipante_socio_id` (solo soci, risposta A4);
- `nota_rimborso_id`.

Sono vuote dappertutto: la migration le toglie.

Il percorso:

1. **Spesa anticipata** (PN-3). Nel nuovo movimento di uscita, la spunta
   «Anticipata da una persona»:
   - data, importo, categoria, descrizione, fornitore, giustificativi come
     ogni movimento;
   - chi ha anticipato: un socio dall'elenco, prima i membri del CD;
   - **nessun conto**: si sceglie al pagamento.

   Il movimento nasce `sospeso`, fuori dai saldi, con il segno «Anticipata
   da …» nell'elenco.
2. **Dal telefono** (Scontrini, passo 2). Oltre a Cassa e Banca c'è «Pagato
   da me», se il modulo Anticipi è acceso e l'utente è un socio (utenti del
   CD, v0.42). Nasce una spesa anticipata da classificare, con
   l'anticipante già scritto.
3. **Passo 1 di PN-7, la nota.** Da «Anticipi da rimborsare»:
   - si scelgono una o più spese aperte della stessa persona, con la data
     della nota e note facoltative;
   - «Genera la nota RIMB-2027-001 (PDF)»: numero con lock e vincolo unico,
     mai riusato, neanche dopo un annullamento;
   - il PDF elenca le spese con i giustificativi e il totale, con lo spazio
     per la firma di chi ha anticipato e il visto del Tesoriere;
   - l'IBAN si chiede solo se il rimborso sarà un bonifico;
   - stato: **emessa**.
4. **Passo 2 di PN-7, la firma.** Si carica la nota firmata (scansione o
   foto), che diventa il giustificativo del pagamento. Stato: **firmata**.
5. **Passo 3 di PN-7, il pagamento.** «Registra il pagamento», dopo la
   firma:
   - data e conto («Pagato da» cassa o banca);
   - in una transazione ogni spesa riceve conto e `rimborsato_il`, ed entra
     nei saldi;
   - stato: **pagata**;
   - Audit Log a rischio alto.

**Annullare**:
- una nota emessa si annulla con la motivazione: le spese tornano aperte e
  il numero resta, segnato «annullata» (niente buchi né doppioni);
- un pagamento registrato per errore si annulla con la motivazione, e la
  nota torna «firmata»;
- su un anno chiuso vale la regola della motivazione della Prima Nota.

**Permessi**: le spese anticipate, le note e i rimborsi li registrano
Presidente e Tesoriere (scrittura su `prima_nota`). Il Revisore consulta
tutto. «Pagato da me» è di chi ha la sezione `scontrini`.

## 2.4 Domande per Paolo

**Risposte di Paolo (20/10/2026)**: A1, A2, A3, A5 ok; **A4 solo
soci**; **A6 sì, la rinuncia si registra come donazione**; M1: il registro
dei moduli passa alla **Fase 2** (§2.7). La parte 1 resta come analisi per
allora.

Regola di Paolo (20/10/2026): quando una funzione c'è già nel Dante se ne
recupera il codice e lo si corregge o migliora (§2.2-bis).


**A1. Data del movimento.** Con il criterio di cassa, l'uscita avviene il
giorno del rimborso, non quello della spesa. Proposta:
- la data del movimento diventa **quella del rimborso**;
- la data dello scontrino resta come data del documento;
- finché la spesa è aperta, l'elenco la mostra alla data della spesa, con
  il segno «da rimborsare».

Così i saldi di un giorno già passato non cambiano più. Una spesa di
dicembre rimborsata a gennaio entra nell'anno nuovo.

**A2. Chiusura dell'anno con anticipi aperti.** Oggi la chiusura è
bloccata. Con A1 una spesa aperta al 31/12 appartiene all'anno in cui sarà
rimborsata. Proposta:
- la chiusura non si blocca più;
- avvisa e elenca gli anticipi aperti, che nel rendiconto compaiono come
  «debiti verso soci per spese anticipate» (nota fuori saldo, già prevista
  in `analisi-rendiconto-preventivo.md`).

**A3. Nota sempre necessaria?** Proposta: sì, anche per una sola spesa di
pochi euro. È il documento che giustifica l'uscita di cassa, e la firma di
chi riceve i soldi lo protegge come protegge la Compagnia.

**A4. Chi anticipa.** Solo soci (dall'elenco), oppure anche chi non è socio
(un genitore, un volontario esterno), con il solo nome? Proposta: tutti e
due, e il socio è la scelta normale.

**A5. Rimborso a sé stessi.** Tu sei Tesoriere e anticiperai spesso: chi
registra il tuo rimborso?
- (a) chiunque abbia il permesso, compreso chi ha anticipato, con l'email
  automatica agli altri (rischio alto, come oggi per le operazioni
  delicate);
- (b) solo un altro: il Presidente registra i rimborsi del Tesoriere, e il
  contrario.

Proposta: (a). Con due soli utenti che scrivono in Prima Nota, (b)
bloccherebbe i rimborsi quando uno dei due manca.

**A6. Rinuncia al rimborso.** Se chi ha anticipato dice «lascia stare, la
offro io», la spesa è una **donazione in natura**: i soldi della Compagnia
non si muovono. Proposta: pulsante «Rinuncia al rimborso». La spesa entra
in Prima Nota alla data della rinuncia, in cassa, e insieme si registra
un'entrata dello stesso importo («Contributi e donazioni da terzi»): il
saldo non cambia, ma il rendiconto mostra la spesa e la donazione. Va bene,
o la rinuncia non serve (si rimborsa sempre)?

**M1. Classificazione dei moduli** (tavola §1.2). Domande online, Tessere
EPS, Scontrini e Anticipi facoltativi; tutto il resto base. Va bene così?
Per la Compagnia tutti accesi.

## 2.5 Tavole da disegnare (dopo le risposte)

- MO-1 Impostazioni › Moduli (computer e telefono), con la finestra di
  spegnimento e il lavoro in sospeso;
- MO-2 fascia «Modulo spento: sola consultazione»;
PN-3 e PN-7 restano come approvate. Da aggiungere:
- AN-1 «Pagato da me» al passo 2 degli scontrini;
- AN-2 PDF della nota di rimborso;
- AN-3 «Anticipi da rimborsare», raggruppati per persona;
- AN-4 scheda della nota: stato, annullamento, annullamento del pagamento
  (e rinuncia, se A6 è sì).

**Disegnate il 20/10/2026** (`Tavole-anticipi.pdf`, fuori dal repository),
con il CSS vero del gestionale:
- AN-1 spesa anticipata (modifica di PN-3: socio dall'elenco, niente conto);
- AN-1b «Pagato da me» al passo 2 degli scontrini (telefono);
- AN-3 «Anticipi e note di rimborso», computer e telefono;
- AN-4 scheda della nota al passo 3, con gli altri stati descritti;
- AN-4b rinuncia al rimborso (donazione): dichiarazione, firma,
  registrazione di uscita ed entrata in cassa;
- AN-2 PDF della nota.

## 2.6 Stima

- Anticipi e note di rimborso: una versione (v0.43.0), collaudo Q, entro
  fine novembre.
- Registro dei moduli: Fase 2.

## 2.7 M1: perché facoltativi (risposta alla domanda di Paolo)

Per la Compagnia non serve spegnere niente: nel 2027 tutto resta acceso.
Gli interruttori servono solo al **modello unico** (`analisi-impianto-asd-aps.md`
§7): un ente non deve vedere funzioni che non usa. Rivista caso per caso:
- **Domande online** e **anticipi** li usano sia la Compagnia sia il
  Dante: diventano **base**;
- **Scontrini con OCR**: il Dante non li ha;
- **Tessere EPS**: il Dante ha le tessere sociali, non quelle di un ente
  sportivo;
- i moduli futuri (Riconciliazione, Cassa mensile, Progetti, Volontari):
  facoltativi, quando si costruiscono.

Conseguenza: oggi gli interruttori riguarderebbero solo Scontrini e Tessere
EPS, e nessuno dei due si spegne prima della migrazione del Dante.
**Proposta: il registro dei moduli passa alla Fase 2**, insieme ai moduli
del Dante che lo rendono utile. Novembre resta agli anticipi e poi a
Verbali e Assemblee.

## 2.8 Esame delle tavole (20/10/2026)

Paolo ha riallegato `Tavole-anticipi.pdf` e il codice del Dante, questa
volta con `resources.zip` (tutte le viste), `routes.zip` e `app.zip`. Si
leggono così anche le due viste che mancavano:
- `prima-nota/rimborsa.blade.php`: la pagina «Rimborsa movimento», con
  l'accorpamento delle altre spese della stessa persona e le due fasi;
- `prima-nota/nota-rimborso-pdf.blade.php`: il PDF.

Del PDF del Dante si riprende:
- il codice della riga del Modello D accanto alla categoria, quando ci sarà
  la mappa delle categorie (Fase 1);
- tipo, numero e data del documento, se ci sono;
- la causale del bonifico.

Si lascia la promessa «il rimborso sarà effettuato entro il …».

Nel Dante c'è anche un modulo diverso, **Rimborsi ai volontari**
(`RimborsiVolontariController`, `gs_rimborsi_volontari`, numero
`RIM-ANNO-NNN`). Ha voci a km ACI, viaggio e vitto, un avviso oltre i
1.500 € di km nell'anno e una sola uscita in Prima Nota al pagamento. Non è
l'anticipo di una spesa ma il rimborso dell'attività del volontario: va con
il modulo Volontari (Fase 2), non nella v0.43.0.

### Difetti delle tavole (si correggono nel codice)

- **T1**, AN-1b. «Di tasca mia: te li rimborsa la Compagnia» diventa «Di
  tasca mia: me li rimborsa la Compagnia».
- **T2**, AN-4b. Il passo «Firma» non ha la data. Diventa «Firma · caricata
  il …», come in AN-4.
- **T3**, AN-2. Il codice fiscale del piè di pagina è un segnaposto: si
  legge dal profilo dell'ente.
- **T4**, AN-3. Manca il modo di classificare, correggere o eliminare una
  spesa aperta: la tavola dice «Prima va scelta la categoria», ma non dove.
  Serve anche nel caso della nota RIMB-2027-003 annullata per una «spesa
  scritta due volte».

  **Proposta**: la descrizione della spesa apre la sua scheda in Prima
  Nota. Finché la spesa non è in una nota, Presidente e Tesoriere la
  modificano o la eliminano, con le regole della Prima Nota.
- **T5**, AN-3. Le note si accumulano negli anni. Si aggiunge la scelta
  dell'anno della nota, con l'anno in corso come predefinito. Le «Spese
  senza nota» si vedono tutte, di qualunque anno.
- **T6**, AN-4. «Annulla la nota» compare anche con la nota firmata.
  Regola: una nota si annulla finché non è pagata, cioè quando è emessa o
  firmata.

### Domande per Paolo

- **E1. Quietanza dei contanti.** La nota si firma prima del pagamento,
  quindi è una richiesta di rimborso. Con i contanti nessun foglio prova che
  il socio ha ricevuto i soldi: il Dante, per il CD, faceva firmare «dichiara
  di aver ricevuto».

  **Proposta**: solo per i contanti, nel PDF una seconda riga «Per
  quietanza: ricevuti € … il __/__/____», firmata dal socio quando riceve i
  soldi. La copia caricata al passo 2 porta le due firme. Per il bonifico
  basta la contabile della banca.
- **E2. Visto sulla propria nota.** Se ha anticipato il Tesoriere, il PDF
  ha «Il socio Paolo Zanetti» e «Visto, il Tesoriere Paolo Zanetti».

  **Proposta**: in quel caso il visto è del Presidente. La registrazione
  resta come in A5 (a).
- **E3. Modalità della nota e conto del pagamento.** La nota firmata dice
  «In contanti, dalla cassa», ma AN-4 lascia scegliere Banca.

  **Proposta**: il conto è fissato dalla modalità scritta nella nota. Per
  cambiarla si annulla la nota e se ne fa un'altra; il numero annullato
  resta. Il documento firmato e il movimento restano così sempre uguali.
- **E4. Giustificativo obbligatorio.** In AN-1 è facoltativo, ma la nota
  dichiara «i giustificativi allegati sono gli originali».

  **Proposta**: almeno un giustificativo è obbligatorio per una spesa
  anticipata. Dal telefono la foto c'è sempre.
- **E5. Chi può anticipare.** **Proposta**: solo i soci maggiorenni attivi,
  prima i membri del CD.
- **E6. Categoria della donazione** (AN-4b). L'unica categoria di donazioni
  attiva è «Contributi e donazioni da terzi»; «Donazioni» (899) è
  disattivata.

  **Proposta**: si usa «Contributi e donazioni da terzi», senza riattivare
  l'altra.

Tavole per il telefono di AN-4 e AN-4b: non disegnate, si verificano a
375 px nel codice.

**Risposte di Paolo (20/10/2026)**: E1–E6 tutte «ok»; nessuna obiezione
alle correzioni T1–T6.

## 2.9 Realizzazione (v0.43.0, 20/10/2026)

Scheda: `specifiche/anticipi.md`. In breve:
- tabella `gl_note_rimborso` con la fotografia della nota; su
  `gl_prima_nota` il socio, la nota e la data della spesa al posto delle
  cinque colonne del Dante;
- servizio `NoteRimborso`, controller `AnticipiController`, viste AN-3,
  passo 1, AN-4/AN-4b, PDF AN-2; spunta AN-1 nel modulo del movimento;
  «Pagato da me» negli scontrini (AN-1b);
- A2: la chiusura dell'anno avvisa degli anticipi aperti senza bloccare;
- T1–T6 fatti: testo di «Pagato da me», data della firma nei passi,
  codice fiscale dalla scheda Associazione (carta intestata), spesa
  aperta modificabile ed eliminabile dal modulo, scelta dell'anno delle
  note, nota annullabile anche firmata.
- Tre giri di revisione: `anticipi.md` §7. 181 test verdi.
- Collaudo: blocco **Q**, `docs/test/collaudo-Q-anticipi.md`.
