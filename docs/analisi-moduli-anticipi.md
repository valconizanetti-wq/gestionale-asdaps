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
  22/09/2026, mai usate da una pagina).

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

## 2.3 Proposta

**Una tabella per la nota** (`gl_note_rimborso`), che raccoglie una o più
spese della stessa persona. Le colonne del Dante sul movimento si
sostituiscono con:
- `anticipante_socio_id`, oppure `anticipante_nome` per chi non è socio;
- `nota_rimborso_id`.

Sono vuote dappertutto: la migration le toglie.

Il percorso in quattro passi:

1. **Spesa anticipata** (tavola AN-1). In Prima Nota, «Nuova spesa
   anticipata»:
   - data, importo, categoria, descrizione, fornitore, giustificativi;
   - chi ha anticipato: un socio dall'elenco (prima i membri del CD),
     oppure «Non è un socio» con nome e cognome;
   - **nessun conto**: si sceglie al rimborso.

   Il movimento nasce `sospeso`, fuori dai saldi, con il segno «Anticipata
   da …» nell'elenco.
2. **Dal telefono** (Scontrini, passo 2). Oltre a Cassa e Banca c'è «Pagato
   da me», se il modulo Anticipi è acceso e l'utente è un socio (utenti del
   CD, v0.42). Nasce una spesa anticipata da classificare, con
   l'anticipante già scritto.
3. **Nota di rimborso** (tavola AN-2). Da «Anticipi da rimborsare» (AN-3):
   - si scelgono una o più spese aperte della stessa persona e si prepara
     la nota;
   - la nota ha il numero `R<n>/<anno>` (lock e vincolo unico, come le
     ricevute), la data, le spese con i giustificativi, il totale, la
     modalità (contanti o bonifico) e l'IBAN se c'è il bonifico;
   - il PDF ha la firma di chi ha anticipato e il visto del Tesoriere;
   - stato: **emessa**.
4. **Rimborso** (tavola AN-4). Dalla nota, «Registra il rimborso»:
   - data e conto (cassa o banca);
   - in una transazione ogni spesa riceve conto e `rimborsato_il`, ed entra
     nei saldi;
   - stato: **rimborsata**;
   - Audit Log a rischio alto.

**Annullare**:
- una nota emessa si annulla con la motivazione: le spese tornano aperte e
  il numero resta, segnato «annullata» (niente buchi né doppioni);
- un rimborso registrato per errore si annulla con la motivazione, e la
  nota torna «emessa»;
- su un anno chiuso vale la regola della motivazione della Prima Nota.

**Permessi**: le spese anticipate, le note e i rimborsi li registrano
Presidente e Tesoriere (scrittura su `prima_nota`). Il Revisore consulta
tutto. «Pagato da me» è di chi ha la sezione `scontrini`.

## 2.4 Domande per Paolo

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
- AN-1 nuova spesa anticipata (e «Pagato da me» al passo 2 degli scontrini);
- AN-2 nota di rimborso: preparazione e PDF;
- AN-3 «Anticipi da rimborsare», raggruppati per persona;
- AN-4 scheda della nota: «Registra il rimborso», annullamento, rinuncia.

## 2.6 Stima

- Registro dei moduli: una versione (v0.43.0).
- Anticipi e note di rimborso: una versione (v0.44.0).
- Ognuno con un blocco di collaudo (Q e R), entro fine novembre.
