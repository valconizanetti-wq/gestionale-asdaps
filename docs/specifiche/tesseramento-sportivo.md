# Tesseramento sportivo / Certificati medici

**Stato:** tessere CSEN in uso dalla v0.37.0 (scheda `tessere.md`); certificati
medici, divisioni e specialità restano solo schema
**Introdotto in:** v0.2.0 (2026-09-22)
**File coinvolti:**
- Migration: `2026_09_22_000059` → `2026_09_22_000067` (gl_qualifiche,
  gl_divisioni_arco, gl_specialita_gara, gl_tecnici, gl_tesseramenti,
  gl_tesseramenti_qualifiche, gl_tesseramenti_divisioni,
  gl_tesseramenti_specialita, gl_certificati_medici)
- Model: `Qualifica.php`, `DivisioneArco.php`, `SpecialitaGara.php`,
  `Tecnico.php`, `Tesseramento.php`, `CertificatoMedico.php`
- Model aggiornato: `Socio.php` (relazioni `tesseramenti()`,
  `certificatiMedici()`, `ruoloTecnico()`)
- Seeder: `QualificaSeeder.php`, `DivisioneArcoSeeder.php`,
  `SpecialitaGaraSeeder.php`

## 1. Logica

Il modulo traccia il tesseramento sportivo dei soci presso l'ente
affiliante (CSEN) e, quando necessario, i certificati medici sportivi.

Regola di business centrale: per il tiro con l'arco, il certificato
medico **non è obbligatorio** finché l'attività resta classificata come
non agonistica (esonero di legge, vedi §5). Nella configurazione attuale
della compagnia — tutti i soci tesserati come "Atleta Praticante" (non
agonisti), nessuna partecipazione a gare di calendario ufficiale
CSEN/FITARCO — **nessun certificato è richiesto**. Il modulo esiste già,
completo, per il momento in cui questo dovesse cambiare (un socio
tesserato come agonista, o partecipazione a gare ufficiali), senza dover
riprogettare lo schema in corsa.

Casi esclusi/non gestiti: il modulo non calcola automaticamente
l'obbligo di certificato in base alla partecipazione a gare — oggi è un
dato inserito manualmente quando (e se) serve. La partecipazione alle
gare non vive in questo database: Gestione gare è un'app Laravel
separata ("ArcoGare", vedi `MANUALE-ASD.md` §4.3), quindi un eventuale
calcolo automatico richiederebbe un'integrazione via API tra le due app.

## 2. Struttura dati

**Tabelle di lookup:**
- `gl_qualifiche` (`codice`, `label`) — seed: `atleta_praticante`,
  `atleta_agonista`, `dirigente`, `tecnico`. Combinabili liberamente
  (relazione molti-a-molti), a differenza del form CSEN che ne accetta
  una sola per tesseramento — verso CSEN si segue la loro codifica,
  internamente il gestionale resta più flessibile.
- `gl_divisioni_arco` (`codice`, `nome`) — seed: `olimpico` (Ricurvo),
  `compound`, `nudo` (Barebow), `longbow`, `istintivo`. Rappresentano il
  tipo di arco (divisione FITARCO).
- `gl_specialita_gara` (`codice`, `nome`) — seed: `targa`, `campagna`
  (Field), `3d`. Asse indipendente dalla divisione (formato di gara).
- `gl_tecnici` (`socio_id` nullable FK → `gl_soci`, `nome_esterno`
  nullable, `note`) — un tecnico può essere un socio già registrato o un
  nominativo esterno. Validazione applicativa: almeno uno dei due campi
  valorizzato.

**Tabelle principali:**
- `gl_tesseramenti`: `socio_id` (FK → `gl_soci`), `stagione_sportiva`,
  `ente_affiliante` (default `CSEN`), `numero_tessera_csen` (cambia ogni
  stagione), `data_inserimento`, `data_rilascio`,
  `pacchetto_tesseramento` (stringa libera: "Base"/"Silver Base"/"Gold
  Base" — non enum, per non richiedere migration se CSEN introduce nuovi
  pacchetti), `tecnico_riferimento_id` (FK → `gl_tecnici`), `stato`
  (attivo/scaduto/sospeso). Vincolo `unique(socio_id, stagione_sportiva)`.
- `gl_certificati_medici`: `socio_id` (FK → `gl_soci`), `tesseramento_id`
  (FK → `gl_tesseramenti`, **nullable**, non 1:1: un certificato
  agonistico — validità biennale per il tiro con l'arco — può coprire
  più stagioni di tesseramento, tipicamente annuale), `tipo`
  (agonistico/non agonistico), `data_visita`, `data_scadenza`,
  `medico_nome`, `struttura`, `stato` (idoneo/non idoneo/sospeso),
  `file_path`/`file_originale`.

**Pivot (relazioni molti-a-molti):** `gl_tesseramenti_qualifiche`,
`gl_tesseramenti_divisioni`, `gl_tesseramenti_specialita` — ciascuna con
vincolo unique sulla coppia di FK.

## 3. Comportamento / flusso

1. Chi gestisce l'anagrafica registra un tesseramento per un socio,
   scegliendo stagione, numero tessera CSEN, pacchetto, e — se
   applicabile — una o più qualifiche/divisioni/specialità.
2. Se il socio verrà tesserato come agonista (oggi non previsto), si
   registra anche un certificato medico agonistico, con calcolo manuale
   della scadenza biennale.
3. Non c'è oggi alcuna interfaccia di blocco/alert attiva: il modulo è
   dati puri, senza logica di enforcement. Il controllo del certificato
   all'iscrizione a gare ufficiali non avverrà dentro `gestionale-asd`:
   le iscrizioni vivono in ArcoGare (app separata, propria anagrafica
   atleti anche esterni alla compagnia). Se in futuro servirà, ArcoGare
   potrà interrogare questo modulo via API, con raccordo per codice
   fiscale (vedi `MANUALE-ASD.md` §4.3) — oppure gestire la verifica in
   autonomia sulla propria anagrafica.

## 4. Implementazione tecnica

- Scelta deliberata di **stringhe libere invece di ENUM** per i campi il
  cui vocabolario è controllato da CSEN (`pacchetto_tesseramento`) e di
  **tabelle di lookup invece di ENUM** per `qualifica`/`divisione`/
  `specialità`, per evitare una migration ogni volta che CSEN o FITARCO
  aggiornano le proprie classificazioni.
- Qualifiche gestite come **molti-a-molti**, anche se il form ufficiale
  CSEN accetta una sola qualifica per tesserato: la scelta riflette
  un'esigenza interna della compagnia (un socio può essere
  contemporaneamente dirigente, tecnico e atleta praticante), non il
  vincolo esterno di CSEN.
- **Nessuna scadenza iniettata in `gl_scadenzario`**: quella tabella è
  pensata per scadenze di livello associazione (poche righe), non per
  scadenze per-socio (26+ persone). Le scadenze di
  tesseramento/certificato restano campi diretti sulle rispettive
  tabelle; un report dedicato "in scadenza nei prossimi N giorni" andrà
  costruito lato applicazione, sullo stesso principio già usato per
  `gl_soci.quota_scadenza`.
- `Tecnico::getNomeVisualizzatoAttribute()` centralizza la logica "nome
  del socio collegato, altrimenti nome esterno" per l'interfaccia.

## 5. Conformità e sicurezza

- **Esenzione certificato medico**: DM Salute 24 aprile 2013 (attuativo
  dell'art. 7 c.11 D.L. 158/2012 "Decreto Balduzzi", conv. L. 189/2012),
  art. 3 — attività sportive che non comportano impegno fisico —
  integrato dalle linee guida Ministero Salute 08/08/2014, che elencano
  esplicitamente il tiro con l'arco tra gli "sport di tiro" esonerati.
  Verificato applicabile anche via CSEN (non solo FITARCO): l'esonero è
  per disciplina, vale per qualunque ente riconosciuto dal CONI.
- **Esenzione DAE** (defibrillatore): stesso DM 24/04/2013, art. 5 comma
  3 — non direttamente gestita da questo modulo, ma rilevante per lo
  stesso ambito normativo.
- **Riforma dello sport**: D.Lgs. 36/2021 e correttivi (contesto
  generale del tesseramento sportivo dilettantistico).
- **Dati sanitari**: quando il modulo sarà attivo, `gl_certificati_medici`
  conterrà dati sanitari (categoria particolare GDPR). Da censire come
  trattamento specifico in `gl_gdpr_trattamenti` prima dell'attivazione
  — non ancora fatto, dato che il modulo non è in uso.

## 6. Interazioni con altri moduli

- **Soci** (`gl_soci`): tutte le tabelle di questo modulo referenziano
  `gl_soci` con FK; nessuna modifica è stata apportata all'anagrafica
  stessa.
- **ArcoGare** (app esterna, non ancora progettata): nessuna
  dipendenza diretta possibile (database separati). Un'eventuale
  verifica di qualifica/certificato all'iscrizione a una gara ufficiale
  passerebbe da un'integrazione via API, con raccordo per codice
  fiscale — da valutare quando si progetterà ArcoGare.
- **GDPR**: da collegare a `gl_gdpr_trattamenti` quando/se il modulo
  verrà attivato con dati sanitari reali.
- **Volontari**: nessuna dipendenza diretta; lo stesso socio può
  comparire sia come volontario sia come tesserato, senza legami
  strutturali tra i due moduli.

## 7. Storia ed evoluzione

- Prima ipotesi (scartata): campo `categoria` enum
  (`agonista`/`non_agonista`/`dirigente`/`tecnico`) direttamente su
  `gl_tesseramenti`. Sostituita dopo aver esaminato un'estrazione reale
  dei dati CSEN (CSV) e uno screenshot del form di iscrizione CSEN, che
  hanno rivelato la vera struttura: qualifica singola
  (`Atleta Praticante`/`Atleta Agonista`/`Dirigente`), pacchetto
  assicurativo separato, discipline a checkbox multiple.
- Qualifica poi discussa come possibile FK singola (rispecchiando il
  form CSEN a scelta unica) — scartata su richiesta esplicita di tenere
  flessibilità interna (un socio può ricoprire più ruoli
  contemporaneamente anche se CSEN non lo prevede nel proprio form).
- Discipline separate in due assi indipendenti (divisione arco vs
  specialità di gara) su richiesta esplicita, invece di un'unica lista
  come sembrava suggerire il form CSEN.
- Verificata la compatibilità dell'esenzione certificato/DAE con CSEN
  come ente affiliante (la ricerca iniziale aveva usato FITARCO come
  riferimento, poi corretto quando è stato confermato CSEN).
- **Correzione post-consegna**: al momento della progettazione di
  questo modulo, Gestione gare era ipotizzato come modulo interno a
  `gestionale-asd`, destinato a usare direttamente questi dati per
  bloccare le iscrizioni senza certificato valido. La decisione
  successiva di spostarlo in un'app esterna ("ArcoGare") ha reso quella
  dipendenza non più diretta — §1, §3 e §6 aggiornati di conseguenza.
  Nessun impatto sullo schema.

## 7. Aggiornamento v0.37.0 (07/10/2026): tessere CSEN

`gl_tesseramenti` è ora la tabella delle tessere CSEN emesse, con pacchetti,
tipi, scadenza a un anno dall'emissione e rinnovo (scheda `tessere.md`):
- tolto il vincolo unico (socio, stagione): la tessera si rinnova a 12 mesi,
  non per stagione; `stagione_sportiva` è l'anno di emissione;
- `numero_tessera_csen` unico; il «pacchetto» CSEN (Base, Silver Base…) è
  ora un tipo in `gl_tessere_tipi` (`tipo_id`), il campo libero
  `pacchetto_tesseramento` resta con il nome; «pacchetto» nel gestionale è
  il lotto di tessere comprato (`gl_tessere_pacchetti`);
- la scadenza è un campo della tessera, con l'elenco «in scadenza nei
  prossimi 30 giorni» previsto in §4 (pagina Tessere).
