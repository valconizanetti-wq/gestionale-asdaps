# Analisi — Soci e utenti: una sola anagrafica

Richiesta di Paolo del 21/10/2026, durante i collaudi Q e R (v0.44.0):

> Bisogna fare pulizia: la cancellazione dei soci non ha determinato la
> cancellazione degli utenti. Se un socio non è più tale non deve essere
> neanche presente tra gli utenti della piattaforma. Inoltre è possibile
> che un utente che è anche socio abbia un indirizzo email differente se
> utente da quello di socio. Sembra ci sia uno scollamento tra soci ed
> utenti. Un utente è anche socio e le informazioni sono uniche e le
> modifiche vanno fatte nell'anagrafica soci.

## 1. Come funziona oggi (v0.44.0)

- **Nascita dell'utente.** Quando un socio entra nel CD
  (`ConsiglioDirettivo::aggiungi`, `utenteDelMembro`) l'utente prende
  **una copia** di cognome, nome ed email dalla scheda del socio e il
  collegamento `gl_users.socio_id`. Da lì in poi le due anagrafiche vanno
  ognuna per conto suo:
  - la modifica della scheda del socio (`SociController::update`) non
    tocca l'utente;
  - la pagina Utenti lascia cambiare l'email anche agli utenti del CD
    (`UtentiController::regole`: il controllo «email di un socio» vale
    solo per gli esterni).
  Così l'email di accesso e quella della scheda possono divergere: è lo
  scollamento visto nel collaudo.
- **Email unica tra gli utenti** (`gl_users.email` è `unique`), mentre
  nella scheda dei soci la stessa email può stare su più soci (genitori,
  e i soci di prova del CD con `valconi@usa.net`). Nel CD di prova, quindi,
  gli utenti hanno per forza email diverse da quelle delle schede.
- **Uscita dal CD** (`ConsiglioDirettivo::togli`): l'utente perde i ruoli
  del CD e, se non ne ha altri (Amministratore), viene **disattivato**,
  non eliminato. Resta nell'elenco Utenti.
- **Cessazione del socio**: possibile solo dopo l'uscita dal CD; l'utente
  resta (disattivato).
- **Eliminazione della scheda**: bloccata per chi è o è stato nel CD.
- **Azzeramento** (`gestionale:azzera`, decisione D1 della v0.41.0): gli
  utenti restano tutti, scollegati dai soci di prova cancellati. È la
  causa degli utenti «orfani» visti oggi.
- **Regola generale** della v0.40.0: «nessuna eliminazione: un utente si
  disattiva». Il motivo: dieci tabelle hanno un riferimento a `gl_users`
  (chi ha caricato un documento, chi ha registrato una scadenza, chi ha
  inviato una lettera…), e l'Audit Log registra il nome di chi ha
  operato.

## 2. Proposta

**Principio.** Per chi è socio, l'anagrafica è una sola: la scheda del
socio. L'utente è solo «l'accesso» di quel socio al gestionale.

1. **Dati dalla scheda.** Cognome, nome ed email dell'utente collegato si
   aggiornano dalla scheda del socio a ogni salvataggio, nella stessa
   transazione. Nella pagina Utenti, per gli utenti collegati a un socio,
   questi campi sono in sola lettura con il link «Si modificano nella
   scheda del socio». La colonna `gl_users.email` resta (serve
   all'accesso e al link della password) ma non si scrive più a mano.
2. **Email unica per chi ha un utente.** Se il socio ha un utente,
   l'email della scheda non può essere quella di un altro utente: errore
   nel modulo del socio («Questa email è dell'accesso di …»). Per gli
   altri soci (genitori con la stessa email) nulla cambia.
3. **Cambio dell'email di accesso.** Quando cambia l'email di un socio con
   utente, un avviso va al vecchio e al nuovo indirizzo (sicurezza
   dell'accesso) e l'evento finisce nell'Audit Log.
4. **Chi non è più socio non è più utente.** Alla cessazione del socio,
   all'eliminazione della scheda e all'uscita dal CD senza altri ruoli,
   l'utente viene **eliminato** dall'elenco Utenti e non può più entrare.
   Vedi la domanda D-SU1 sul tipo di eliminazione.
5. **Pulizia una tantum.** Un comando (o una voce dell'azzeramento) toglie
   gli utenti collegati a soci non più attivi o a schede cancellate, e
   allinea email, cognome e nome degli utenti collegati alle schede.

## 3. Domande per Paolo

- **D-SU1 — Eliminazione logica o fisica.** Logica (consigliata): l'utente
  sparisce dall'elenco e non entra più, ma il suo nome resta nei registri
  («caricato da …», Audit Log); si può ripristinare se rientra nel CD.
  Fisica: la riga si cancella e i riferimenti diventano vuoti.
- **D-SU2 — Azzeramento.** Dopo `gestionale:azzera` i soci di prova non ci
  sono più: con la regola nuova spariscono anche gli utenti del CD di
  prova, compreso il tuo. Restano solo gli utenti esterni (Amministratore
  di sistema senza socio, Revisore): per rientrare e ricreare il CD dopo
  l'importazione dei soci veri serve un Amministratore esterno. Va bene, o preferisci che l'azzeramento lasci gli utenti
  e li scolleghi come oggi?
- **D-SU3 — Collaudo.** Con l'email unica fra gli utenti, i quattro del CD
  di prova non possono più avere tutti `valconi@usa.net` nella scheda:
  servono quattro indirizzi diversi (per esempio alias
  `valconi+andrea@…`, se la tua casella li accetta, o quattro caselle).

## 4. Decisioni

(da compilare con le risposte)
