<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DomandeController;
use App\Http\Controllers\ImpostazioniEnteController;
use App\Http\Controllers\SociController;
use App\Http\Controllers\SocioDocumentiController;
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

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// Area riservata
Route::middleware(['auth', 'attivo'])->group(function () {

    Route::view('/dashboard', 'dashboard')->name('dashboard');

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
        });

    // Qui, modulo per modulo, gli altri gruppi con ->middleware('permesso:<sezione>').
});
