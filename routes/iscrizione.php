<?php

use App\Http\Controllers\IscrizioneController;
use App\Http\Controllers\PrivacyLinkController;
use App\Http\Controllers\VotoCdController;
use Illuminate\Support\Facades\Route;

/*
| Domanda di iscrizione online — pagine pubbliche, senza login
| (domande-iscrizione.md §3). Incluso da routes/web.php, fuori dai gruppi
| con 'auth'. Limiti per IP con nome (AppServiceProvider): ognuno ha il suo
| contatore, così pagine, anteprima del codice fiscale e invii non si sommano.
*/

Route::prefix('iscrizione')->name('iscrizione.')->group(function () {
    Route::middleware('throttle:pubblico-pagine')->group(function () {
        Route::get('/', [IscrizioneController::class, 'dati'])->name('dati');
        Route::get('/controlla', [IscrizioneController::class, 'riepilogo'])->name('riepilogo');
        Route::get('/inviata', [IscrizioneController::class, 'inviata'])->name('inviata');
        Route::get('/conferma/{token}', [IscrizioneController::class, 'mostraConferma'])->name('conferma');
        Route::get('/annulla/{token}', [IscrizioneController::class, 'mostraAnnulla'])->name('annulla');
    });

    // Anteprima del codice fiscale: POST, perché in GET il codice finirebbe
    // nei log di accesso del server. Si chiama a ogni caricamento del
    // modulo e mentre si scrive (una volta per codice completo).
    Route::post('/codice-fiscale', [IscrizioneController::class, 'anteprimaCodiceFiscale'])
        ->middleware('throttle:pubblico-cf')->name('codice-fiscale');

    // Passo 1 → riepilogo: "Avanti / Modifica" si può ripetere.
    Route::post('/', [IscrizioneController::class, 'salvaDati'])
        ->middleware('throttle:iscrizione-passo')->name('dati.salva');

    Route::middleware('throttle:pubblico-azioni')->group(function () {
        Route::post('/controlla', [IscrizioneController::class, 'invia'])->name('invia');
        Route::post('/conferma/{token}', [IscrizioneController::class, 'conferma'])->name('conferma.esegui');
        Route::post('/annulla/{token}', [IscrizioneController::class, 'annulla'])->name('annulla.esegui');
    });
});

Route::middleware('throttle:pubblico-pagine')->group(function () {
    Route::get('/informativa-privacy', [IscrizioneController::class, 'informativa'])->name('informativa');
    Route::get('/statuto', [IscrizioneController::class, 'statuto'])->name('statuto');
});

/*
| Voto del Consiglio Direttivo dal link personale (domande-iscrizione.md
| §3, statuto art. 16.4). Pagina pubblica: il link è la chiave.
*/
Route::prefix('voto-cd')->name('voto-cd.')->group(function () {
    Route::get('/{token}', [VotoCdController::class, 'mostra'])
        ->middleware('throttle:pubblico-pagine')->name('mostra');
    Route::post('/{token}', [VotoCdController::class, 'vota'])
        ->middleware('throttle:pubblico-azioni')->name('vota');
});

/*
| Link personale per l'informativa privacy e foto e video (privacy-link.md
| §3). Pagina pubblica: il link è la chiave.
*/
Route::prefix('privacy')->name('privacy-link.')->group(function () {
    Route::get('/{token}', [PrivacyLinkController::class, 'mostra'])
        ->middleware('throttle:pubblico-pagine')->name('mostra');
    Route::post('/{token}', [PrivacyLinkController::class, 'conferma'])
        ->middleware('throttle:pubblico-azioni')->name('conferma');
});

/*
| Consultazione telematica del CD dal link personale (v0.44.0, tavola CT-2,
| statuto art. 16.4). Pagina pubblica: il link è la chiave. I documenti
| della proposta si scaricano dallo stesso link.
*/
Route::prefix('consultazione')->name('consultazione.')->controller(\App\Http\Controllers\ConsultazioneVotoController::class)->group(function () {
    Route::get('/{token}', 'mostra')->middleware('throttle:pubblico-pagine')->name('mostra');
    Route::get('/{token}/documenti/{documento}', 'documento')->whereNumber('documento')->middleware('throttle:pubblico-pagine')->name('documento');
    Route::post('/{token}', 'vota')->middleware('throttle:pubblico-azioni')->name('vota');
});
