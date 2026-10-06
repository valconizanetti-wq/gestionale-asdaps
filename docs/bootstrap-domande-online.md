# Bootstrap — ripartenza: Domanda di iscrizione online

**Da caricare all'inizio della prossima sessione**, insieme ai file
elencati in §5. Riassume lo stato del progetto al 27/09/2026 (v0.25.1) e
le regole di lavoro concordate, così non vanno ricostruite.

## 1. Il progetto in breve

- **Gestionale ASD** (`gestionale-asd`): derivato Laravel 12 del core
  (`gestionale-core`) per la **Compagnia Arcieri Castel Viscardo**, oggi
  B.A.S. (C.F. 90022610555), ASD dal 1/1/2028 con lo stesso codice
  fiscale. Tiro con l'arco, affiliazione CSEN, iscrizione RASD.
- Stack: PHP 8.3, MySQL, Blade + Alpine (npm), Tailwind 4 (Vite),
  DomPDF. Sviluppo in locale con Laragon; git con push su `origin`.
- **Collaudo online**: `https://arco.valconi.it` (SupportHost, account
  cPanel `uiynixlg`, PHP `alt-php83` per sito e Terminale). **Solo dati di
  prova** finché non ci saranno dominio e provider definitivi (con DPA).
  Procedura in `deploy.md`.
- Riferimento per molte soluzioni: **Gestionale APS** (Dante Alighieri
  Orvieto), stesso autore, stesso stack.

## 2. Stato dei moduli

| Modulo | Stato |
|---|---|
| Base dati (tutti i moduli) | Completa e documentata (schede in `docs/`) |
| Accesso, permessi, layout | Fatto (`auth.md`, `permessi.md`) |
| UI: token, componenti, telefono | Fatto (`ui.md`) |
| **Soci** | **Completo** (`soci.md`) |
| Audit log (scrittura e verifica) | Fatto (`audit-log.md` §8) |
| PDF (layout comune) | Fatto (`ui.md`, "Modelli PDF") |
| Primo deploy | Fatto (`deploy.md`) |
| **Domande di iscrizione online** | **Prossimo** |
| Rinnovi quota, Tesseramento, Prima Nota, … | Da fare |

## 3. Obiettivo della prossima sessione

Domanda di iscrizione online, **canale principale del consenso
privacy** (decisione di Paolo):
- modulo pubblico di domanda (senza login), con consenso privacy e gli
  altri consensi già previsti in `gl_domande`;
- iter di approvazione del CD (schema esistente: `gl_domande`,
  `gl_voti_cd`; si può percorrere anche solo `in_attesa` → `approvata`);
- all'approvazione, creazione del socio con i campi privacy copiati
  (`privacy_metodo = online`, data/ora, IP del richiedente, versione
  dell'informativa da `gl_impostazioni.privacy_versione_corrente`);
- **pagina di consenso via link personale** per i soci senza domanda
  online (decisa; manca anche nel Gestionale APS): link monouso con
  scadenza, token salvato solo come hash, tabella nuova, rotta pubblica
  con limite di tentativi; condivide con la domanda la pagina
  dell'informativa.

Prerequisiti: posta SMTP configurata; testo dell'informativa privacy.

## 4. Regole di lavoro concordate

- **Prima di ogni controller**: verificare migration e model del modulo
  contro la sua scheda (metodo usato con Soci e Bilancio).
- **Documentare sempre**: ogni modulo ha una scheda in 7 sezioni (Logica,
  Struttura dati, Comportamento, Implementazione, Conformità,
  Interazioni, Storia); le correzioni da riportare al core vanno in
  `MANUALE-ASD.md` §7, i passi in §8.
- **UI**: niente `style=` né `<style>` nelle viste (eccezione: modelli
  PDF); componenti in `resources/views/components/`; stati → badge in
  `config/stati.php`; etichette dei campi in grassetto; ogni vista
  verificata anche a 375 px; per nuove schermate, prima la tavola sulla
  tela di design "Gestionale ASD — Riferimento UI", poi il codice.
- **Rotte**: `<modulo>.<azione>`, pagina principale `<modulo>.index`,
  gruppo con `permesso:<sezione>`; la voce di menu compare da sola.
- **Permessi**: `User::puoAccedere()/puoModificare()/soloLettura()`.
- **Audit**: i model di business dichiarano
  `#[ObservedBy([AuditObserver::class])]`; eventi di business con
  `AuditLogService::scrivi()` (campo `operazione` vuoto); **mai dati
  personali nel `dettaglio`** (entra nell'hash). Formula dell'hash da
  considerare congelata.
- **Consegna**: file singoli o ZIP cumulativi con la struttura del
  progetto; dopo ogni blocco collaudato, commit e push.
- **Blade**: una direttiva non deve essere attaccata a una parola
  (`testo@endif` non viene riconosciuta).

## 5. File da caricare nella prossima sessione

Dal gestionale ASD:
- `MANUALE-ASD.md`, `domande-iscrizione.md`, `soci.md`, `gdpr.md`,
  `ui.md` (e questo bootstrap);
- migration e model di `gl_domande` e `gl_voti_cd` (`Domanda.php`,
  eventuale `VotoCd.php`);
- `app/Models/Impostazione.php` (non ancora visto: serve per leggere la
  versione dell'informativa).

Dal Gestionale APS:
- controller delle domande e relative viste (`iscrizione/`, `domande/`),
  più le email collegate, se ci sono.

Altro:
- dati SMTP (indirizzo mittente, server, porta, utente, password) di una
  casella creata sul cPanel di `valconi.it`;
- testo dell'informativa privacy, se esiste (altrimenti si prepara una
  bozza, eventualmente partendo da quella del Dante).

## 6. Punti aperti da ricordare

- Tessera associativa interna: non ancora deciso se emetterla.
- Motivi di cessazione: confermati, da ricontrollare con lo statuto.
- Certificato medico: previsto ma spento (`certificato_medico_richiesto`).
- Pseudonimizzazione dei soci: da realizzare con il modulo GDPR (dovrà
  oscurare anche i valori nelle righe di audit del socio).
- Dominio e provider definitivi: raccomandato SupportHost con datacenter
  UE e DPA; poi migrazione secondo `deploy.md` §6.
- `.env.example` da aggiungere al progetto (senza chiave né password).
