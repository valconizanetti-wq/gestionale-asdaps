# Progetti (analisi per il contesto ASD — nessuna modifica necessaria)

**Stato:** confermato riuso diretto (nessun cambiamento di schema)
**Introdotto in:** v0.12.0 (2026-09-22) — analisi, non modifica
**File coinvolti:** nessuno (schema esistente confermato adeguato):
`gl_progetti`, `gl_progetti_preventivo`

## 1. Logica

Il modulo traccia iniziative finanziate a progetto, con un preventivo
per categoria e un collegamento ai movimenti di Prima Nota. L'analisi
ha verificato se la natura ASD (invece che APS/RUNTS) richiedesse un
adattamento specifico. Conclusione: nessuno — il modulo è utile per
qualunque iniziativa finanziata a progetto (es. un bando sportivo, un
progetto scuola-sport), a prescindere dalla forma giuridica
dell'associazione.

## 2. Struttura dati

**Nessuna modifica.** Due campi residui del contesto RUNTS, non
applicabili a questa ASD:
- `gl_progetti_preventivo.codice_dm39` — classificazione bilancio DM39,
  stesso trattamento già riservato a campi analoghi
  (`gl_bilancio_voci.codice`, `gl_prima_nota_categorie.codice_dm39`):
  resta nello schema, semplicemente non popolato.
- `gl_progetti.codice_documento` — verosimilmente un codice
  documento specifico RUNTS (stesso pattern del campo omonimo su
  `gl_bilancio`), non applicabile qui.

## 3. Comportamento / flusso

Nessun cambiamento rispetto al core. Il modulo resta utilizzabile così
com'è per un'eventuale iniziativa finanziata della compagnia (es. un
progetto sostenuto da CSEN o da un bando pubblico locale).

## 4. Implementazione tecnica

Nessuna. L'analisi ha confermato l'adeguatezza dello schema esistente
senza richiedere modifiche.

## 5. Conformità e sicurezza

Nessun aspetto specifico introdotto da questa analisi.

## 6. Interazioni con altri moduli

- **Prima Nota** (`gl_prima_nota`): dipendenza esistente e invariata
  (`progetto_id`).
- **Categorie** (`gl_prima_nota_categorie`): dipendenza esistente e
  invariata (`categoria_id` su `gl_progetti_preventivo`).

## 7. Storia ed evoluzione

- Analisi puntuale, in linea con gli altri moduli verificati in questa
  sessione: nessuna modifica di schema necessaria, solo conferma della
  presenza di residui RUNTS innocui (già visti in forma analoga in
  Bilancio e Prima Nota) da lasciare semplicemente non popolati.
