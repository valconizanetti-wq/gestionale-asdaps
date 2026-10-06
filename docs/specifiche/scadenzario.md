# Scadenzario (analisi per il contesto ASD — nessuna modifica necessaria)

**Stato:** confermato riuso diretto (nessun cambiamento di schema)
**Introdotto in:** v0.10.0 (2026-09-22) — analisi, non modifica
**File coinvolti:** nessuno (schema esistente confermato adeguato):
`gl_scadenzario`

## 1. Logica

Il core generalizza già la categoria "sda" (specifica del Dante) in
`rete_affiliazione`, pensata per qualunque APS federata — verificato che
questa generalizzazione regge anche per un'ASD affiliata a un Ente di
Promozione Sportiva (CSEN) invece che a una rete come AVIS/ARCI/Dante.
L'analisi ha verificato due cose: se le categorie esistenti coprono i
bisogni reali dell'ASD, e se il modello di ricorrenza (annuale) è
sufficiente per tutte le scadenze rilevanti.

## 2. Struttura dati

**Nessuna modifica.** Mappatura delle categorie esistenti
(`gl_scadenzario.categoria`, enum `runts`/`rete_affiliazione`/
`assemblea`/`contabile`/`altro`) sui bisogni specifici di questa ASD:

- `rete_affiliazione` → rinnovo affiliazione CSEN (vedi `MANUALE-ASD.md`
  §2.1: senza affiliazione valida l'iscrizione al RASD decade — scadenza
  critica, non solo informativa).
- `contabile` → termine di approvazione del bilancio/rendiconto (4-6
  mesi dalla chiusura dell'esercizio, vedi scheda `bilancio.md`).
- `runts` → **non applicabile** a questa ASD (non iscritta al RUNTS).
  Resta nell'enum senza essere rimossa: valore semplicemente non
  utilizzato, innocuo, stesso trattamento già riservato a `codice_dm39`
  in altri moduli (`gl_prima_nota_categorie`, `gl_bilancio_voci`).

## 3. Comportamento / flusso

Nessun cambiamento. Punto verificato specificamente: `giorno_ricorrenza`/
`mese_ricorrenza` sono campi singoli (non un elenco), quindi il modello
di ricorrenza supporta solo scadenze **annuali** (un giorno/mese fisso
ogni anno), non ricorrenze infrannuali (es. trimestrali).

## 4. Implementazione tecnica

- Il limite di ricorrenza annuale è stato verificato **non essere un
  problema pratico** per questa ASD: l'unica scadenza potenzialmente
  trimestrale individuata nell'intera sessione — la comunicazione al
  RASD dei rimborsi forfettari ai volontari — ha già una tabella
  dedicata (`gl_rimborsi_rasd_invii`, vincolo `anno`+`trimestre`,
  costruita nel modulo Volontari) e non deve/dovrebbe passare da
  `gl_scadenzario`.
- Nessuna riga di scadenza reale inserita in questa sessione: coerente
  con l'approccio già seguito per il registro trattamenti GDPR
  (`gdpr.md`), i dati vanno inseriti quando l'attività diventa reale
  (l'associazione non esiste ancora, costituzione 1/1/2028), non prima.

## 5. Conformità e sicurezza

Nessun aspetto di conformità specifico introdotto da questa analisi
oltre a quanto già tracciato nelle scadenze di affiliazione (§2.1) e
bilancio (§2.4, `bilancio.md`).

## 6. Interazioni con altri moduli

- **Compliance RASD/Affiliazione** (§4.2 di `MANUALE-ASD.md`, scheda
  `compliance-rasd.md`, v0.15.0): userà la categoria
  `rete_affiliazione` sia per la scadenza di rinnovo CSEN (da
  `gl_associazione.rete_affiliazione_scadenza`) sia per i termini di
  regolarizzazione delle verifiche Sport e Salute
  (`gl_rasd_verifiche`), generati lato applicazione.
- **Bilancio**: userà la categoria `contabile` per il termine di
  approvazione, quando la logica applicativa verrà scritta (non ancora
  implementata, vedi `bilancio.md` §4).
- **Volontari / RASD** (`gl_rimborsi_rasd_invii`): tabella dedicata
  separata per la comunicazione trimestrale, non passa da
  `gl_scadenzario` — nessuna sovrapposizione.
- **Tesseramento sportivo / Certificati medici**: deliberatamente
  **escluso** da questo modulo — le scadenze per-socio (26+ persone)
  restano su un report dedicato lato applicazione, non su
  `gl_scadenzario` (pensato per scadenze di livello associazione, poche
  righe), vedi scheda `tesseramento-sportivo.md` §4.

## 7. Storia ed evoluzione

- Analisi condotta in modo puntuale, verificando per la prima volta in
  questa sessione l'aspetto della ricorrenza (non controllato in
  precedenza per altri moduli).
- Confermato un pattern già emerso più volte in questa sessione: la
  checklist iniziale segnalava questo modulo come "+ scadenze
  certificati medici" da aggiungere, ma la decisione presa in sede di
  progettazione del modulo Tesseramento (non iniettare scadenze
  per-socio in questa tabella) rende quella nota originaria superata —
  corretta in questa sessione.
- Nessuna modifica necessaria: secondo caso in questa sessione (dopo
  GDPR, Comunicazioni, Audit Log) in cui un'analisi puntuale conferma
  l'adeguatezza dello schema esistente invece di richiedere un
  adattamento.
