# Comunicazioni (analisi per il contesto ASD — nessuna modifica necessaria)

**Stato:** confermato riuso diretto (nessun cambiamento di schema)
**Introdotto in:** v0.8.0 (2026-09-22) — analisi, non modifica
**File coinvolti:** nessuno (schema esistente confermato adeguato):
`gl_lettere`, `gl_lettere_invii`, `gl_comunicazioni`,
`gl_comunicazioni_allegati`, `gl_comunicazioni_destinatari`

## 1. Logica

Il modulo comprende due sottosistemi distinti già presenti nel core:
protocollo lettere formale (numerazione progressiva per anno, snapshot
immutabile del destinatario, firma, invio) e circolari a gruppi di soci
(con tracciamento di consegna per singolo destinatario). L'analisi
mirava a verificare se il contesto ASD (corrispondenza con CSEN,
comunicazioni a soci minorenni) richiedesse adattamenti specifici.
Conclusione: nessuno strutturale.

## 2. Struttura dati

**Nessuna modifica.** Punti rilevanti dello schema esistente per questa
analisi:
- `gl_lettere`: il tipo `uscita` e lo snapshot immutabile del
  destinatario (`destinatario_nome`/`_indirizzo`/`_email`, catturati al
  momento della protocollazione) sono già sufficientemente generici per
  la corrispondenza formale con CSEN — non serve un tipo dedicato.
- `gl_comunicazioni_destinatari.email`: campo libero, valorizzato
  verosimilmente da `gl_soci.email` al momento dell'invio — già
  abbastanza generico da poter contenere in alternativa
  `gl_soci.tutore_email` per un socio minorenne senza email propria
  (vedi §4).

## 3. Comportamento / flusso

Nessun cambiamento rispetto al comportamento già previsto dal core.
Punto da implementare in fase applicativa (non di schema): quando si
compone l'elenco destinatari di una comunicazione, per un socio
minorenne privo di email propria, l'applicazione dovrà leggere
`gl_soci.tutore_email` invece di `gl_soci.email`.

## 4. Implementazione tecnica

Nessuna modifica di schema. Unico punto operativo identificato: la
scelta di quale campo email leggere da `gl_soci` (proprio o del
tutore) va implementata come logica applicativa al momento
dell'inserimento delle righe in `gl_comunicazioni_destinatari`, non
richiede una colonna aggiuntiva né un vincolo a livello di database.

## 5. Conformità e sicurezza

- Nessuna sovrapposizione con `gl_rimborsi_rasd_invii` (comunicazione
  trimestrale al RASD, modulo Volontari): concettualmente diverse
  (compliance esterna con vincolo anno/trimestre, vs comunicazione
  interna ai soci), correttamente tenute separate nel core.
- L'uso dell'email del tutore per i minorenni è coerente con quanto già
  verificato nell'analisi GDPR (scheda `gdpr.md`): il trattamento dei
  dati dei minori è già coperto a livello di `gl_soci`, questo modulo
  ne è semplicemente un consumatore.

## 6. Interazioni con altri moduli

- **Soci** (`gl_soci`): dipendenza esistente e invariata
  (`gl_comunicazioni_destinatari.socio_id`); punto di attenzione futuro
  sulla scelta email propria/tutore per i minorenni.
- **Assemblee** (`gl_assemblee`): dipendenza esistente e invariata
  (`gl_comunicazioni.assemblea_id`).
- **Volontari / RASD** (`gl_rimborsi_rasd_invii`): nessuna
  sovrapposizione, moduli distinti per scopo.

## 7. Storia ed evoluzione

- Analisi richiesta esplicitamente in modo puntuale ("procediamo in modo
  puntuale, così è una verifica in più"), con verifica diretta di tutte
  e 5 le tabelle prima di concludere.
- Risultato coerente con altri moduli confermati "riuso diretto" dopo
  verifica in questa sessione (Assemblee, Verbali, GDPR,
  `gl_cassa_chiusure`) — pattern ricorrente: non tutti i moduli
  segnalati come "da verificare" nella checklist iniziale richiedono
  effettivamente modifiche.
- Unico elemento emerso durante l'analisi, non previsto inizialmente:
  la necessità di usare l'email del tutore per i soci minorenni privi
  di email propria — annotato come nota implementativa per la fase UI,
  non come gap di schema.
