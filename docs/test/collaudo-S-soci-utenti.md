# Collaudo S — Soci e utenti: una sola anagrafica

Blocco della lista di collaudo del Gestionale ASD, in un file a sé. Le regole comuni sono in `test-domande-iscrizione.md`, nella stessa cartella. Copia nel progetto: `claude/collaudo-S-soci-utenti.md`.

Versione: **v0.44.1** (21/10/2026).
- Analisi e decisioni: `docs/analisi-soci-utenti.md` (D-SU1…D-SU3).

**Che cosa si prova.**
- La **pulizia**: l'azzeramento lascia un solo socio e un solo utente, Presidente nel CD.
- Per chi è socio l'anagrafica è una: **cognome, nome ed email dell'utente vengono dalla scheda del socio**.
- Il **cambio dell'email** avvisa il vecchio e il nuovo indirizzo.
- **Chi non è più socio, o esce dal CD senza altri ruoli, non è più utente** (eliminazione logica) e torna se rientra nel CD.

**Prima di cominciare (una volta sola).**
1. Installa la v0.44.1 (LEGGIMI): **una migration**, `php artisan optimize:clear` e le tre cache. Niente npm.
2. Tieni a portata il file Excel dei soci di prova (con un'email diversa per ogni socio adulto).

Dopo S1 il gestionale è vuoto: i blocchi Q e R si rifanno da capo, dopo S2.

---

### S1 — Pulizia: resta solo Paolo, Presidente
**Scopo:** azzerare i dati di prova lasciando un solo socio e un solo utente.
**Chi:** sul server (Terminale di cPanel), nella cartella del gestionale.
**Passi:**
1. `php artisan gestionale:azzera` (prova a vuoto, senza `--tieni`).
2. `php artisan gestionale:azzera --tieni=<la tua email di accesso>` (ancora prova a vuoto).
3. `php artisan gestionale:azzera --tieni=<la tua email di accesso> --esegui` e scrivi `AZZERA`.
4. Entra nel gestionale con la tua email e la tua password di sempre.
5. Apri Soci, Utenti e Consiglio Direttivo.

**Risultato atteso:**
- Al passo 1, in fondo, l'avviso giallo «Manca --tieni=<email>: l'utente che resta, unico socio e unico utente, Presidente nel CD.» e la riga «Utenti che si possono tenere: …» con il tuo nome e la tua email. Nell'elenco «Si svuotano» c'è la riga «Utenti» con tutti gli utenti di adesso.
- Al passo 2, sotto «Si tengono», la riga «Il socio e l'utente Paolo Zanetti (…): unico socio, unico utente, Presidente nel CD»; in fondo «Per azzerare davvero: php artisan gestionale:azzera --tieni=… --esegui».
- Al passo 3, tra le righe con ✔: «Resta solo Paolo Zanetti (…): socio n. 1, utente n. 1, Presidente nel Consiglio Direttivo».
- Al passo 4 si entra (la sessione di prima è chiusa).
- Al passo 5:
  - Soci: solo Zanetti Paolo;
  - Utenti: solo Zanetti Paolo, con il ruolo Presidente;
  - Consiglio Direttivo: solo Zanetti Paolo · Presidente, con l'avviso dei meno di tre membri.

### S2 — Soci di prova e CD di prova
**Scopo:** ricaricare i soci di prova e comporre il CD scegliendo tra loro.
**Chi:** Presidente (tu).
**Passi:**
1. Soci › «Importa da Excel»: carica il file dei soci di prova e conferma.
2. Consiglio Direttivo › «Aggiungi un membro»: aggiungi i soci che vuoi nel CD di prova (per esempio un Segretario e Tesoriere e due Consiglieri), con la spunta del link per la password.
3. Apri Utenti.

**Risultato atteso:**
- Ogni membro aggiunto ha il suo utente, con cognome, nome ed email **della sua scheda** e il ruolo della carica; arriva il link per la password all'email della scheda.
- Utenti mostra solo te e i membri del CD.

Nota: il passo 2 si può fare anche da Presidente e poi, se vuoi, ti dai un'altra carica con «Cambia carica».

### S3 — Il nome e l'email si cambiano nella scheda
**Scopo:** l'utente segue la scheda del socio.
**Chi:** Presidente.
**Prima:** S2, con un Consigliere nel CD (qui «il Consigliere»).
**Passi:**
1. Utenti › matita sul Consigliere.
2. Soci › scheda del Consigliere › «Modifica»: guarda il campo Email.
3. Cambia il nome (per esempio aggiungi una lettera) e l'email (un altro indirizzo tuo) e salva.
4. Apri Utenti e le due caselle email (la vecchia e la nuova).
5. Rimetti nome ed email come prima.

**Risultato atteso:**
- Al passo 1, al posto del campo Email, la riga «Email per entrare» con l'email in grassetto e sotto «È l'email della scheda del socio: si cambia lì (modifica la scheda). Serve per entrare e per le email «al ruolo».». Cognome e nome non si scrivono qui.
- Al passo 2, sotto il campo Email, l'aiuto «Con questa email entra nel gestionale: cambiandola, cambia anche l'accesso (arriva un avviso al vecchio e al nuovo indirizzo).»
- Al passo 4:
  - in Utenti il Consigliere ha il nome e l'email nuovi;
  - al vecchio indirizzo arriva «… · Gestionale: è cambiata l'email per entrare», con l'email nuova scritta in parte («ab…@dominio») e «questo indirizzo non serve più per entrare»;
  - al nuovo indirizzo la stessa email con «Da adesso è l'email per entrare nel gestionale, con la password di prima».
- Il Consigliere entra con l'email nuova e la sua password.

### S4 — Ognuno entra con la sua email
**Scopo:** l'email di chi entra non può essere quella di un altro utente.
**Chi:** Presidente.
**Prima:** S2.
**Passi:**
1. Nella scheda del Consigliere scrivi la tua email di accesso e salva.
2. Nella scheda di un socio **non** del CD scrivi l'email del Consigliere e salva.
3. Prova ad aggiungere quel socio al CD.
4. Rimetti l'email del socio come prima.

**Risultato atteso:**
- Al passo 1, sotto il campo Email: «Questa email è già quella con cui entra nel gestionale Paolo Zanetti: ognuno entra con la sua.» La scheda non si salva.
- Al passo 2 la scheda si salva: un socio senza accesso può avere l'email di un altro (famiglie).
- Al passo 3, nella finestra: «L'email del socio (…) è quella con cui entra nel gestionale un altro socio: ognuno entra con la sua, correggila nella scheda.»

### S5 — Chi esce dal CD non è più utente
**Scopo:** uscita dal CD senza altri ruoli = utente eliminato; rientro = utente di nuovo.
**Chi:** Presidente; poi il Consigliere.
**Prima:** S2.
**Passi:**
1. Consiglio Direttivo › togli il Consigliere (motivo «Dimissioni», data di oggi).
2. Apri Utenti.
3. Prova a entrare con l'email e la password del Consigliere (finestra anonima).
4. Rimettilo nel CD come Consigliere, **senza** la spunta del link.
5. Entra di nuovo come il Consigliere, con la sua password di prima.
6. Audit Log, eventi del giorno.

**Risultato atteso:**
- Al passo 2 il Consigliere non c'è più nell'elenco.
- Al passo 3 non si entra.
- Al passo 4 torna nell'elenco Utenti, con l'email della scheda e il ruolo Consigliere.
- Al passo 5 si entra.
- Al passo 6 c'è «UTENTE_ELIMINATO» (rischio alto); nel dettaglio «Utente n. … eliminato (eliminazione logica): uscito dal Consiglio Direttivo, senza altri ruoli.»

### S6 — Chi non è più socio non è più utente
**Scopo:** la cessazione del socio toglie l'accesso.
**Chi:** Presidente.
**Prima:** S2; un membro del CD da far uscire (qui «il Consigliere»).
**Passi:**
1. Scheda del Consigliere › «Registra cessazione».
2. Togli il Consigliere dal CD, ma prima dagli il ruolo «Amministratore di sistema» da Utenti (spunta «In più»).
3. Ora registra la cessazione.
4. Apri Utenti.
5. Riattiva il socio dalla sua scheda (è una prova) e rimettilo nel CD.

**Risultato atteso:**
- Al passo 1: «Il socio ricopre la carica di Consigliere nel Consiglio Direttivo: toglilo prima dal CD…».
- Al passo 2, fuori dal CD, resta utente come Amministratore.
- Al passo 3: «Cessazione registrata. Il suo accesso al gestionale è stato tolto.»
- Al passo 4 non c'è più, anche se era Amministratore.
- Al passo 5 torna utente, con il ruolo della carica (l'Amministratore non torna: si ridà a mano se serve).

---

## Esiti del collaudo
(da compilare)
