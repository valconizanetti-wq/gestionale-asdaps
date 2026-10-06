# Gestionale Core

Modello riusabile di gestionale associativo (Laravel 12 / PHP 8.2 / MySQL 8),
estratto dal **Gestionale APS — Società Dante Alighieri, Comitato di Orvieto**
(progetto originale, mantenuto separatamente).

## Cosa è

Un'app Laravel 12 completa e installabile che copre il dominio comune a
un'**APS iscritta al RUNTS**: soci, quote/rinnovi, contabilità, governance
(assemblee/verbali/delibere), compliance RUNTS, GDPR, comunicazioni,
archivio documentale, permessi granulari per ruolo, audit log con catena
di integrità.

## Cosa NON è

- Non è un fork del Gestionale APS Dante: non condivide storia git con
  l'originale, la sincronizzazione tra i due è **manuale e intenzionale**
  (vedi `SYNC-LOG.md`).
- Non contiene nulla di specifico della Società Dante Alighieri — Comitato
  di Orvieto: nessun dato associativo hardcoded (ragione sociale, sede,
  CF/P.IVA sono in `gl_associazione`, non nel codice).
- Non è pensato per essere installato così com'è per un'associazione reale:
  è il punto di partenza da cui nascono i **progetti derivati** (uno per
  associazione), ciascuno poi personalizzato e mantenuto per conto proprio.

## Come nasce un progetto derivato

Questo repo è marcato come **GitHub Template Repository**. Per una nuova
associazione: "Use this template" su GitHub → nuovo repo indipendente,
storia propria, nessun collegamento git col core. Il derivato viene poi
adattato (moduli tagliati/aggiunti/modificati) e mantenuto separatamente.

## Convenzioni ereditate dal progetto originale

- Prefisso tabelle: `gl_` (nel Gestionale APS Dante è `gs_`)
- Nessuno staging: sviluppo e test in locale, deploy a pacchetti
  incrementali su cPanel condiviso, verifica md5sum post-deploy
- Modifiche DB in produzione sempre via tinker, mai migration dirette
  (le migration servono solo per l'installazione iniziale di un derivato)
- PHP/Blade: vietati caratteri accentati/apostrofi in stringhe PHP
  single-quote; vincoli DomPDF (no flex/grid, no simbolo €, font DejaVu Sans)
- Se un derivato usa l'audit log con catena di integrità: scrittura SEMPRE
  tramite `AuditLogService`, mai `DB::table()->insert()` diretto

## Aggiornare il core da un miglioramento fatto nel progetto Dante

1. Nel progetto Dante si sviluppa e si stabilizza la modifica
2. Si valuta se il modulo interessato è tra quelli presenti nel core
3. Si porta la modifica qui (copia mirata o cherry-pick), si aggiorna
   `SYNC-LOG.md`, si crea un nuovo tag (`vN`)
4. Si applica il tag ai derivati che si vuole aggiornare — sincronizzazione
   manuale, un derivato alla volta, mai automatica
