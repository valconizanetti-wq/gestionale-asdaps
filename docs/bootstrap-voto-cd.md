# Bootstrap — Gestionale ASD, prossima sessione: votazione del CD

## Contesto
Sono Paolo, unico sviluppatore del Gestionale ASD (Laravel 12, Blade + Alpine, Tailwind 4 con `@layer components` e token in `@theme`, Vite, DomPDF) per la Compagnia Arcieri Castel Viscardo A.S.D. (oggi B.A.S., C.F. 90022610555; ente affiliante CSEN; statuto revisionato v1.0 del 27/09/2026).
- Locale: Laragon + Cmder, `C:\laragon\www\gestionale-asd`.
- Collaudo: cPanel SupportHost, account `uiynixlg`, `~/gestionale-asd`, dominio `arco.valconi.it` → `public`, PHP 8.3. Solo dati di prova.
- Sito della Compagnia (WordPress, tema figlio Astra, temporaneo): `arciericastello.valconi.it`. Le pagine pubbliche del gestionale ne copiano intestazione e piè (`layouts/pubblico`, classe `tema-sito`, `config/sito.php`).

## Regole di lavoro
- Tavola di design sulla tela prima del codice (tela: https://claude.ai/artifact/EQpx2xUxVo3jZwysZrLt5H).
- Viste: niente `style=` né `<style>`; una direttiva Blade mai attaccata a una parola; etichette in grassetto; verifica anche a 375 px; componenti in `resources/views/components/`.
- Documentare sempre: scheda in 7 sezioni (`docs/domande-iscrizione.md`), `docs/ui.md`, `docs/MANUALE-ASD.md`.
- Consegna in ZIP con la struttura del progetto (percorsi dalla radice del progetto).
- Tre giri di revisione critica prima di ogni consegna.
- Password SMTP solo nel `.env` del server. Audit: mai dati personali nel dettaglio.

## Deploy (docs/deploy.md §5)
Locale: commit → `npm run build` → `git archive -o deploy.zip HEAD` → `build.zip` da `public/build`.
Server: `unzip -o deploy.zip`; per il build controllare prima `unzip -l build.zip | head`: se i percorsi iniziano con `public/build/` estrarre nella radice, se con `build/` estrarre con `-d public`. Se una cartella risulta in sola lettura: `chmod -R u+w`. Poi `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `view:clear`, `config:cache`, `route:cache`, `view:cache`.

## Stato alla chiusura (28/09/2026)
- v0.29.2 in collaudo: pagina unica di iscrizione modello Dante (istruzioni in accordion, modulo a due schede, FAQ laterali, testi in `resources/content/iscrizione/testi.php`), selettore Maggiorenne/Minorenne, anteprima del codice fiscale in POST.
- Flusso pubblico completo fino alla conferma: stato `in_attesa`, `scadenza_cd` = +5 giorni, ricevuta PDF, avviso alla segreteria.
- Da fare sul server: attivare l'estensione PHP **fileinfo** (cPanel → Select PHP Version → Extensions).
- Da fare sul sito WordPress: voce "Iscrizioni" → `https://arco.valconi.it/iscrizione`; verificare l'indirizzo della voce "Attività" (`/attivita/` in `config/sito.php`).

## Obiettivo della sessione: modulo Voto CD
Specifica in `docs/domande-iscrizione.md` §3 ("Voto del CD") e nelle revisioni:
- all'apertura (alla conferma della domanda): una riga `gl_voti_cd` per ogni consigliere in carica (da `gl_governance_cd` → socio: nome, email), token personale (impronta), flag `presidente` fissato all'apertura; segnalare al Presidente i consiglieri senza email;
- pagina di voto dal link: favorevole / contrario (motivazione obbligatoria); voto registrato con evento di business `VOTO_CD_ESPRESSO` (solo id e voto), dopo il controllo `is_test`; `VotoCd` senza observer;
- esito con `Domanda::calcolaEsito()` / `esitoCerto()`, letti dopo il salvataggio del voto (relazione ricaricata); chiusura anticipata quando l'esito è certo;
- comando giornaliero (riscrivere quello del Dante): sollecito al 3° giorno, esito a 5 giorni (sotto quorum o parità senza Presidente → accolta d'ufficio), decadenza della quota a 30 giorni, cancellazioni per fine conservazione (`cancellaPerFineConservazione()`: non confermate 30 giorni, non accolte 12 mesi);
- esiti: diniego motivato entro 10 giorni con appello all'Assemblea entro 30; accoglimento con quota, IBAN, causale e termine di 30 giorni;
- bozza di verbale del CD con il voto di ciascuno;
- area riservata delle domande: elenco, scheda, azione "Quota versata: iscrivi nel libro soci" (crea il socio, copia anagrafica, tutore e privacy online).
Poi: link personale per il consenso privacy dei soci senza domanda online.

## Materiale allegato
1. `progetto.zip` = `git archive -o progetto.zip HEAD` del repository aggiornato (dopo il commit della v0.29.2).
2. Dal Gestionale APS (Dante): `VotoCDController`, viste del voto e della gestione domande (`domande/`), email del voto/sollecito/esito, il comando giornaliero delle domande, l'eventuale modello di verbale del CD.
3. Dati: IBAN e causale per l'email di accoglimento; composizione di prova del CD (3–5 consiglieri con email di prova, chi è il Presidente).
