# Rinnovi quota (analisi per il contesto ASD — nessuna modifica necessaria)

**Stato:** confermato riuso diretto (nessun cambiamento di schema)
**Introdotto in:** v0.11.0 (2026-09-22) — analisi, non modifica
**File coinvolti:** nessuno (schema esistente confermato adeguato):
`gl_rinnovi`

## 1. Logica

Il modulo gestisce il rinnovo annuale della quota associativa, con
ricevuta numerata e stato di invio. L'analisi ha verificato due aspetti
specifici del contesto ASD: la tracciabilità dei pagamenti (L.
398/1991) e l'impatto della nuova esenzione IVA sulle quote associative
(dal 1° gennaio 2026, vedi `MANUALE-ASD.md` §2.3).

## 2. Struttura dati

**Nessuna modifica.** Punto rilevante già presente:
- `Rinnovo::movimentoPrimaNota()` (relazione `hasOne` verso `PrimaNota`,
  tramite `rinnovo_id` su `gl_prima_nota`): collega ogni rinnovo al
  proprio movimento contabile, permettendo di riusare senza duplicazioni
  la logica di alert tracciabilità già progettata a livello di Prima
  Nota (vedi scheda `scontrini.md` §6.1).

## 3. Comportamento / flusso

Nessun cambiamento rispetto al core. Un rinnovo pagato in contanti
(`metodo_pagamento = 'contante'`) per un importo che, sommato ad altri
movimenti collegati, superasse la soglia di tracciabilità
(`gl_impostazioni.regime_398_tracciabilita_soglia`), verrebbe già
intercettato dal controllo generale su `gl_prima_nota`, senza bisogno di
una logica dedicata qui.

## 4. Implementazione tecnica

- Nessuna modifica di schema. Confermato che il collegamento esistente
  a Prima Nota è sufficiente a estendere gratuitamente ogni controllo
  già progettato a livello di movimento contabile (tracciabilità,
  distinzione istituzionale/commerciale) anche ai rinnovi quota, senza
  duplicare campi o logica.
- **Nota per la fase di implementazione UI/PDF** (non di schema): dal
  1° gennaio 2026 le quote associative sono passate da operazioni
  escluse IVA a operazioni esenti IVA (art. 10/36-bis DPR 633/72) — il
  template di stampa della ricevuta andrà aggiornato con la dicitura
  corretta ("operazione esente IVA ai sensi dell'art. ..." invece di
  "operazione esclusa"). Non richiede alcun campo aggiuntivo su
  `gl_rinnovi`: è un dettaglio di rendering del PDF, non di dati.

## 5. Conformità e sicurezza

- Nessun campo nuovo introdotto; la conformità alla tracciabilità
  398/91 è ereditata dal controllo già progettato su Prima Nota.
- L'esenzione IVA non richiede tracciamento riga per riga: si applica
  indistintamente a tutte le quote associative di questa ASD.

## 6. Interazioni con altri moduli

- **Prima Nota** (`gl_prima_nota`): dipendenza esistente e invariata
  (`rinnovo_id`), da cui eredita gratuitamente il controllo di
  tracciabilità 398/91.
- **Impostazioni** (`gl_impostazioni`): consumo indiretto, tramite Prima
  Nota, della soglia `regime_398_tracciabilita_soglia`.

## 7. Storia ed evoluzione

- Analisi puntuale, in linea con gli altri moduli verificati in questa
  sessione (GDPR, Comunicazioni, Audit Log, Scadenzario): nessuna
  modifica di schema necessaria.
- Punto di interesse: la relazione già esistente
  `Rinnovo::movimentoPrimaNota()` — pensata originariamente solo per
  collegare rinnovo e movimento contabile — si è rivelata sufficiente
  anche per estendere ai rinnovi, senza sforzo aggiuntivo, i controlli
  di conformità 398/91 progettati per Prima Nota in generale.
