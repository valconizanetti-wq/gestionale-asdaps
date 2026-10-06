# Derivato ASD — Bootstrap sessione di analisi

*Da usare come primo messaggio della sessione dedicata all'analisi del
derivato ASD, insieme ai file elencati in fondo.*

---

## Punto di partenza

Il "Modello Gestionale Core" (Laravel 12, repo
`https://github.com/valconizanetti-wq/gestionale-core`) è completo per la
parte schema DB + model Eloquent, estratto dal Gestionale APS della Società
Dante Alighieri — Comitato di Orvieto. Tutti i dettagli — moduli costruiti,
convenzioni, generalizzazioni già fatte, gap noti — sono in
`MANUALE-CORE.md` alla root del repo.

Obiettivo di questa sessione: **analizzare cosa serve per derivare da questo
core un gestionale per una ASD** (associazione sportiva dilettantistica),
recuperando quanto più possibile del core esistente.

## Perché non è un lavoro di sola traduzione

A differenza del passaggio "Dante → core" (dove le differenze erano quasi
solo di branding), ASD e APS hanno differenze normative reali che vanno
capite prima di toccare schema o codice:

- **Registro**: le ASD si iscrivono al Registro Nazionale delle Attività
  Sportive Dilettantistiche (attivo dal 2022), non al RUNTS — a meno che
  l'associazione non sia *anche* APS (ente ibrido, caso da chiarire subito)
- **Lavoro sportivo**: dalla Riforma dello Sport (D.Lgs. 36/2021 e
  correttivi, D.Lgs. 120/2023) chi presta attività per una ASD va
  inquadrato come lavoratore dipendente, autonomo con P.IVA, o
  co.co.co. sportivo — un regime diverso dai "Collaboratori" con ritenuta
  d'acconto già presenti nel core, con proprie soglie di esenzione e
  comunicazioni obbligatorie (portale Sport e Salute, INPS, INAIL)
- **IVA**: dal 1° gennaio 2025 le prestazioni delle ASD verso i propri
  associati sono passate da escluse a **esenti** IVA — non più irrilevanti
  ai fini fiscali, con adempimenti diversi da prima
- **Bilancio DM39**: è uno standard specifico degli enti del Terzo Settore
  (RUNTS); da verificare se si applica a una ASD non iscritta al RUNTS o se
  serve un modello diverso
- **Volontari**: la riforma distingue esplicitamente volontari da
  lavoratori sportivi, con regole proprie — da confrontare con il modulo
  Volontari già presente nel core (pensato per un'APS generica)
- Probabile necessità di un modulo nuovo, assente nel core: **tesseramento
  sportivo / certificati medico-sportivi** degli atleti (obbligo
  assicurativo e di certificazione per l'attività agonistica/non
  agonistica)

Questi sono punti di partenza per la discussione, non conclusioni: la
normativa (in particolare soglie e adempimenti) va riverificata con fonti
aggiornate alla data della sessione, e alcune scelte (es. se l'ASD sarà
anche APS) vanno chiarite con Paolo prima di decidere come trattare
Compliance RUNTS, Bilancio DM39 e GDPR-trattamenti specifici.

## Metodo di lavoro proposto

Stesso approccio già rodato per il core: procedere modulo per modulo (uso
l'elenco moduli di `MANUALE-CORE.md` §5 come traccia), per ciascuno
decidere insieme:
- **riuso diretto** (schema e model del core vanno bene così)
- **adattamento** (stessa tabella, colonne/enum da modificare — es. come
  fatto per `rete_affiliazione` nel core)
- **non applicabile** (il modulo non serve per un'ASD, es. probabilmente
  Compliance RUNTS se l'ASD non è anche APS)
- **nuovo modulo necessario** (es. tesseramento sportivo/certificati
  medici, non presente nel core)

Prima regola pratica: **non ripartire dallo schema Dante** come fatto per
il core — qui non esiste ancora un gestionale ASD di riferimento, quindi il
punto di partenza per ogni modulo è il core così com'è, da confermare o
modificare voce per voce.

## File da caricare in questa sessione

- `MANUALE-CORE.md` (già pronto, root del repo core)
- Lo statuto dell'ASD di riferimento, se disponibile — utile per capire
  affiliazione (CONI/ente di promozione sportiva), attività previste,
  eventuale doppia natura APS/ASD
- Qualunque documento normativo o gestionale specifico dell'ASD che Paolo
  ha già sottomano (es. regolamento tesseramento, tariffario quote)

## Ambiente

Progetto Laragon separato dal core, non uno scaricamento a mano:
- Su GitHub, "Use this template" sul repo `gestionale-core` → nuovo repo
  (es. `gestionale-asd`), che parte con tutto lo stato attuale del core
- Clonarlo in una nuova cartella Laragon (es.
  `C:\laragon\www\gestionale-asd`), nuovo DB MySQL dedicato (es.
  `gestionale_asd`)
- Stesso PHP 8.2.0; vale ancora l'accortezza Avast sui primi
  `composer install`/`update`
- Dominio di appoggio per questo derivato: **valconi.it** (diverso da
  `ladanteorvieto.com` usato per il Dante) — da tenere presente per email
  mittente, backup, e qualunque altro riferimento di dominio ancora
  hardcoded nel codice ereditato da Dante

## Primo passo di questa sessione

Chiarire con Paolo, prima di aprire la checklist modulo per modulo:
1. L'ASD è *solo* ASD o anche APS (ente ibrido)? Cambia molto su RUNTS/GDPR/Bilancio.
2. Che sport/attività pratica, e se ha tesserati agonisti (rilevante per
   certificazione medica e assicurazione).
3. Ha già dipendenti/collaboratori sportivi, o solo volontari puri?

Poi costruire insieme la checklist di analisi modulo per modulo (non
preparata in anticipo in questa sessione, per non anticipare risposte prima
di avere il contesto reale dell'ASD).
