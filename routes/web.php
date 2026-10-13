<?php

use App\Http\Controllers\AnniContabiliController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\UtentiController;
use App\Http\Controllers\DomandeController;
use App\Http\Controllers\ImpostazioniEnteController;
use App\Http\Controllers\PrimaNotaController;
use App\Http\Controllers\ScontriniController;
use App\Http\Controllers\FornitoriController;
use App\Http\Controllers\PromemoriaController;
use App\Http\Controllers\RinnoviController;
use App\Http\Controllers\ScadenzarioController;
use App\Http\Controllers\SociController;
use App\Http\Controllers\SociImportaController;
use App\Http\Controllers\SocioDocumentiController;
use App\Http\Controllers\TessereController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Convenzioni (vedi auth.md)
|--------------------------------------------------------------------------
| - Ogni modulo ha rotte con nome "<modulo>.<azione>" e pagina principale
|   "<modulo>.index": è il nome che config/navigazione.php cerca per
|   mostrare la voce nella sidebar.
| - Ogni gruppo di rotte di un modulo porta ->middleware('permesso:<sezione>'),
|   dove <sezione> è il codice in gl_sezioni.
| - Le rotte si aggiungono qui solo quando il controller esiste: la voce di
|   menu compare da sola.
*/

Route::get('/', fn () => redirect()->route('dashboard'));

// Domanda di iscrizione online, informativa e statuto: pagine pubbliche
// (domande-iscrizione.md). Fuori dall'area riservata.
require __DIR__ . '/iscrizione.php';

// Accesso
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

// Password dimenticata e link «Scegli la password» (v0.40.0, tavola UT-3b):
// pubbliche anche da collegati (il link si può aprire su un browser dove è
// collegato qualcun altro: alla conferma entra l'utente del link).
Route::controller(PasswordController::class)->group(function () {
    Route::get('/password-dimenticata', 'dimenticata')->name('password.dimenticata');
    Route::post('/password-dimenticata', 'inviaLink')->name('password.invia');
    Route::get('/password/scegli/{token}', 'scegli')->name('password.scegli');
    Route::post('/password/scegli', 'salva')->name('password.salva');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Area riservata
Route::middleware(['auth', 'attivo'])->group(function () {

    Route::view('/dashboard', 'dashboard')->name('dashboard');

    // Importazione dei soci da Excel (v0.40.0, tavole IS-1…IS-3): chi
    // modifica i Soci (controllo nel controller). Prima del gruppo Soci, che
    // ha la rotta /soci/{socio}.
    Route::middleware('permesso:soci')
        ->prefix('soci/importa')->name('soci.importa')
        ->controller(SociImportaController::class)
        ->group(function () {
            Route::get('/', 'index');
            Route::get('/modello', 'modello')->name('.modello');
            Route::post('/', 'leggi')->name('.leggi');
            Route::post('/conferma', 'conferma')->name('.conferma');
            Route::post('/annulla', 'annulla')->name('.annulla');
            Route::get('/fatto', 'fatto')->name('.fatto');
        });

    // Soci (soci.md)
    Route::middleware('permesso:soci')
        ->prefix('soci')->name('soci.')
        ->controller(SociController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/esporta', 'export')->name('export');
            Route::get('/libro-soci.pdf', 'pdf')->name('pdf');
            Route::post('/link-privacy', 'inviaLinkPrivacy')->name('privacy-link.tutti');
            Route::get('/nuovo', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::post('/{id}/ripristina', 'ripristina')->whereNumber('id')->name('ripristina');
            Route::get('/{socio}', 'show')->name('show');
            Route::get('/{socio}/modifica', 'edit')->name('edit');
            Route::put('/{socio}', 'update')->name('update');
            Route::post('/{socio}/cessazione', 'cessazione')->name('cessazione');
            Route::post('/{socio}/riattiva', 'riattiva')->name('riattiva');
            Route::delete('/{socio}', 'destroy')->name('destroy');
        });

    // Audit Log: consultazione, esportazioni, verifica, certificati e
    // attestazioni (audit-log.md §9). Presidente pieno accesso, Revisore in
    // sola lettura; certificato e attestazione anche al Revisore (controllo
    // nel controller).
    Route::middleware('permesso:audit_log')
        ->prefix('audit-log')->name('audit-log.')
        ->controller(AuditLogController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/esporta.csv', 'exportCsv')->name('export-csv');
            Route::get('/esporta.pdf', 'exportPdf')->name('export-pdf');
            Route::get('/verifica', 'verifica')->name('verifica');
            Route::post('/verifica', 'eseguiVerifica')->name('verifica.esegui');
            Route::post('/certificati', 'generaCertificato')->name('certificati.genera');
            Route::get('/certificati/{certificato}', 'scaricaCertificato')->whereNumber('certificato')->name('certificati.scarica');
            Route::get('/attestazioni/bozza', 'bozzaAttestazione')->name('attestazioni.bozza');
            Route::post('/attestazioni', 'caricaAttestazione')->name('attestazioni.carica');
            Route::get('/attestazioni/{id}/{parte?}', 'scaricaAttestazione')->whereNumber('id')->whereIn('parte', ['firmata', 'relazione'])->name('attestazioni.scarica');
        });

    // Documenti e consenso privacy del socio (soci.md)
    Route::middleware('permesso:soci')
        ->prefix('soci/{socio}')->name('soci.')
        ->controller(SocioDocumentiController::class)
        ->group(function () {
            Route::post('/documenti', 'store')->name('documenti.store');
            Route::get('/documenti/{documento}', 'download')->name('documenti.download');
            Route::get('/documenti/{documento}/anteprima', 'anteprima')->name('documenti.anteprima');
            Route::delete('/documenti/{documento}', 'destroy')->name('documenti.destroy');
            Route::post('/privacy/presenza', 'privacyPresenza')->name('privacy.presenza');
            Route::post('/privacy/cartacea', 'privacyCartacea')->name('privacy.cartacea');
            Route::post('/privacy/link', 'privacyLink')->name('privacy.link');
            Route::post('/foto-video/revoca', 'fotoVideoRevoca')->name('foto-video.revoca');
            Route::get('/privacy/modulo', 'privacyDownload')->name('privacy.download');
            Route::get('/privacy/modulo/anteprima', 'privacyAnteprima')->name('privacy.anteprima');
        });

    // Domande di adesione, voto del CD, iscrizione nel libro soci
    // (domande-iscrizione.md §3). Le azioni del Presidente controllano il
    // ruolo nel controller.
    Route::middleware('permesso:domande')
        ->prefix('domande')->name('domande.')
        ->controller(DomandeController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/promozione', 'promozione')->name('promozione');
            Route::get('/{domanda}', 'show')->name('show');
            Route::get('/{domanda}/ricevuta', 'ricevuta')->name('ricevuta');
            Route::get('/{domanda}/verbale', 'verbale')->name('verbale');
            Route::post('/{domanda}/voti/{voto}/reinvia', 'reinviaLink')->name('reinvia');
            Route::post('/{domanda}/parita', 'decidiParita')->name('parita');
            Route::post('/{domanda}/diniego', 'diniego')->name('diniego');
            Route::post('/{domanda}/iscrivi', 'iscrivi')->name('iscrivi');
            Route::delete('/{domanda}', 'destroy')->name('destroy');
        });

    // Impostazioni › Ente: profilo dell'ente per periodi, passaggio, apertura
    // delle iscrizioni online (profilo-ente.md). Modifiche solo al Presidente
    // (controllo nel controller).
    Route::middleware('permesso:impostazioni')
        ->prefix('impostazioni')->name('impostazioni.')
        ->controller(ImpostazioniEnteController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/ente/dati', 'editDati')->name('ente.dati');
            Route::put('/ente/dati', 'updateDati')->name('ente.dati.update');
            Route::get('/ente/periodi/nuovo', 'createPeriodo')->name('periodi.create');
            Route::post('/ente/periodi', 'storePeriodo')->name('periodi.store');
            Route::get('/ente/periodi/{periodo}/modifica', 'editPeriodo')->name('periodi.edit');
            Route::put('/ente/periodi/{periodo}', 'updatePeriodo')->name('periodi.update');
            Route::delete('/ente/periodi/{periodo}', 'destroyPeriodo')->name('periodi.destroy');
            Route::get('/ente/periodi/{periodo}/passaggio', 'passaggio')->name('periodi.passaggio');
            Route::post('/ente/periodi/{periodo}/spunte', 'spunta')->name('periodi.spunta');
            Route::post('/iscrizioni', 'iscrizioni')->name('iscrizioni');
            // Affiliazione e polizza RC (tavole SC-2, SC-3, v0.38.0)
            Route::put('/ente/affiliazione', 'updateAffiliazione')->name('ente.affiliazione');
            Route::put('/ente/polizza', 'updatePolizza')->name('ente.polizza');
        });

    // Utenti del gestionale (v0.40.0, tavole UT-1…UT-3): Presidente e
    // Segretario modificano (il Segretario con i limiti di Utenti), il resto
    // del CD consulta (controllo nel controller).
    Route::middleware('permesso:utenti')
        ->prefix('utenti')->name('utenti.')
        ->controller(UtentiController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/nuovo', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{utente}/modifica', 'edit')->whereNumber('utente')->name('edit');
            Route::put('/{utente}', 'update')->whereNumber('utente')->name('update');
            Route::post('/{utente}/link-password', 'linkPassword')->whereNumber('utente')->name('link-password');
        });

    // Rinnovi quota (rinnovi-quota.md, v0.36.0): Presidente e Tesoriere
    // registrano e annullano, il Revisore consulta (controllo nel controller).
    Route::middleware('permesso:rinnovi_quota')
        ->prefix('rinnovi')->name('rinnovi.')
        ->controller(RinnoviController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/esporta-csv', 'exportCsv')->name('export-csv');
            Route::get('/registra', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{rinnovo}/ricevuta', 'ricevuta')->whereNumber('rinnovo')->name('ricevuta');
            Route::post('/{rinnovo}/invia', 'invia')->whereNumber('rinnovo')->name('invia');
            Route::post('/{rinnovo}/annulla', 'annulla')->whereNumber('rinnovo')->name('annulla');
        });

    // Tessere CSEN: pacchetti, emissione e rinnovo, carico dell'elenco del
    // CSEN (analisi-tessere-scadenze.md, v0.37.0). Presidente, Segretario e
    // Tesoriere modificano, il Revisore consulta (controllo nel controller).
    Route::middleware('permesso:tesseramento')
        ->prefix('tessere')->name('tesseramento.')
        ->controller(TessereController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::get('/carica', 'carica')->name('carica');
            Route::post('/carica', 'caricaFile')->name('carica.file');
            Route::post('/carica/conferma', 'caricaConferma')->name('carica.conferma');
            Route::post('/carica/annulla', 'caricaAnnulla')->name('carica.annulla');
            Route::get('/pacchetti/nuovo', 'createPacchetto')->name('pacchetti.create');
            Route::post('/pacchetti', 'storePacchetto')->name('pacchetti.store');
            Route::get('/pacchetti/{pacchetto}/modifica', 'editPacchetto')->whereNumber('pacchetto')->name('pacchetti.edit');
            Route::put('/pacchetti/{pacchetto}', 'updatePacchetto')->whereNumber('pacchetto')->name('pacchetti.update');
            Route::delete('/pacchetti/{pacchetto}', 'destroyPacchetto')->whereNumber('pacchetto')->name('pacchetti.destroy');
            Route::post('/tipi', 'storeTipo')->name('tipi.store');
            Route::put('/tipi/{tipo}', 'updateTipo')->whereNumber('tipo')->name('tipi.update');
            Route::post('/{tessera}/portale', 'portale')->whereNumber('tessera')->name('portale');
            Route::delete('/{tessera}', 'destroy')->whereNumber('tessera')->name('destroy');
        });

    // Scadenze dell'associazione: affiliazione, polizza, adempimenti
    // (scadenzario.md, tavola SC-1, v0.38.0).
    Route::middleware('permesso:scadenzario')
        ->prefix('scadenzario')->name('scadenzario.')
        ->controller(ScadenzarioController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/nuova', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{scadenza}', 'edit')->whereNumber('scadenza')->name('edit');
            Route::put('/{scadenza}', 'update')->whereNumber('scadenza')->name('update');
            Route::delete('/{scadenza}', 'destroy')->whereNumber('scadenza')->name('destroy');
            Route::post('/{scadenza}/rinnova', 'rinnova')->whereNumber('scadenza')->name('rinnova');
            Route::post('/{scadenza}/annulla-rinnovo', 'annulla')->whereNumber('scadenza')->name('annulla');
        });

    // Promemoria: email automatiche pianificate per tutte le funzioni
    // (promemoria.md, v0.36.0). Chi modifica un promemoria dipende dalla
    // sezione della sua funzione (controllo nel controller).
    Route::middleware('permesso:promemoria')
        ->prefix('promemoria')->name('promemoria.')
        ->controller(PromemoriaController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/nuovo', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{promemoria}', 'edit')->whereNumber('promemoria')->name('edit');
            Route::put('/{promemoria}', 'update')->whereNumber('promemoria')->name('update');
            Route::delete('/{promemoria}', 'destroy')->whereNumber('promemoria')->name('destroy');
            Route::post('/{promemoria}/prova', 'prova')->whereNumber('promemoria')->name('prova');
        });

    // Prima Nota: movimenti, giroconti, giustificativi, anni contabili,
    // categorie e tag (prima-nota.md, v0.35.0). Presidente e Tesoriere
    // modificano, il Revisore consulta (controllo nei controller).
    Route::middleware('permesso:prima_nota')
        ->prefix('prima-nota')->name('prima-nota.')
        ->group(function () {
            Route::controller(AnniContabiliController::class)->prefix('anni')->name('anni.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::put('/{anno}/saldi', 'saldi')->whereNumber('anno')->name('saldi');
                Route::get('/{anno}/saldi/documento', 'documento')->whereNumber('anno')->name('documento');
                Route::post('/{anno}/chiudi', 'chiudi')->whereNumber('anno')->name('chiudi');
                Route::post('/{anno}/riapri', 'riapri')->whereNumber('anno')->name('riapri');
            });

            Route::controller(CategorieController::class)->group(function () {
                Route::get('/categorie', 'index')->name('categorie.index');
                Route::post('/categorie', 'store')->name('categorie.store');
                Route::put('/categorie/{categoria}', 'update')->name('categorie.update');
                Route::post('/categorie/{categoria}/sposta', 'sposta')->name('categorie.sposta');
                Route::post('/categorie/{categoria}/attiva', 'attiva')->name('categorie.attiva');
                Route::delete('/categorie/{categoria}', 'destroy')->name('categorie.destroy');
                Route::put('/tag/{tag}', 'updateTag')->name('tag.update');
                Route::delete('/tag/{tag}', 'destroyTag')->name('tag.destroy');
            });

            Route::controller(PrimaNotaController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/esporta.csv', 'exportCsv')->name('export-csv');
                Route::get('/registro.pdf', 'exportPdf')->name('export-pdf');
                Route::get('/nuovo', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/giroconto', 'createGiroconto')->name('giroconto.create');
                Route::post('/giroconto', 'storeGiroconto')->name('giroconto.store');
                Route::get('/{movimento}', 'show')->whereNumber('movimento')->name('show');
                Route::get('/{movimento}/modifica', 'edit')->whereNumber('movimento')->name('edit');
                Route::put('/{movimento}', 'update')->whereNumber('movimento')->name('update');
                Route::delete('/{movimento}', 'destroy')->whereNumber('movimento')->name('destroy');
                Route::post('/{movimento}/documenti', 'aggiungiDocumenti')->whereNumber('movimento')->name('documenti.store');
                Route::get('/{movimento}/documenti/{documento}', 'documento')->whereNumber('movimento')->name('documenti.show');
                Route::delete('/{movimento}/documenti/{documento}', 'eliminaDocumento')->whereNumber('movimento')->name('documenti.destroy');
            });
        });

    // Scontrini dal telefono con OCR e fornitori (scontrini.md, tavole
    // SN-1…SN-8, v0.39.0). Caricano tutti i ruoli con la sezione piena;
    // verificano, dividono e correggono Presidente e Tesoriere (controllo
    // nei controller con puoModificare('prima_nota')).
    Route::middleware('permesso:scontrini')
        ->prefix('scontrini')->name('scontrini.')
        ->group(function () {
            Route::controller(FornitoriController::class)->prefix('fornitori')->name('fornitori.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::post('/unisci', 'unisci')->name('unisci');
                Route::put('/{fornitore}', 'update')->whereNumber('fornitore')->name('update');
                Route::delete('/{fornitore}', 'destroy')->whereNumber('fornitore')->name('destroy');
            });

            Route::controller(ScontriniController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('/miei', 'miei')->name('miei');
                Route::get('/nuovo', 'create')->name('create');
                Route::post('/leggi', 'leggi')->name('leggi');
                Route::get('/nuovo/{token}', 'conferma')->where('token', '[A-Za-z0-9]{32}')->name('conferma');
                Route::get('/nuovo/{token}/foto', 'fotoBozza')->where('token', '[A-Za-z0-9]{32}')->name('conferma.foto');
                Route::post('/nuovo/{token}', 'store')->where('token', '[A-Za-z0-9]{32}')->name('store');
                Route::delete('/nuovo/{token}', 'annulla')->where('token', '[A-Za-z0-9]{32}')->name('annulla');
                Route::get('/{scontrino}', 'show')->whereNumber('scontrino')->name('show');
                Route::put('/{scontrino}', 'update')->whereNumber('scontrino')->name('update');
                Route::delete('/{scontrino}', 'destroy')->whereNumber('scontrino')->name('destroy');
                Route::post('/{scontrino}/dividi', 'dividi')->whereNumber('scontrino')->name('dividi');
                Route::post('/{scontrino}/foto', 'aggiungiFoto')->whereNumber('scontrino')->name('foto.store');
                Route::get('/{scontrino}/foto/{file}', 'foto')->whereNumber('scontrino')->whereNumber('file')->name('foto.show');
                Route::get('/{scontrino}/anteprima', 'anteprima')->whereNumber('scontrino')->name('anteprima');
            });
        });

    // Qui, modulo per modulo, gli altri gruppi con ->middleware('permesso:<sezione>').
});
