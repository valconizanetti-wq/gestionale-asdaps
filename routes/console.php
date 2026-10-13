<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Domande di adesione: chiusura delle votazioni, solleciti, promemoria,
// decadenze e cancellazioni (domande-iscrizione.md §3). Ogni ora, così la
// votazione si chiude al massimo un'ora dopo la scadenza dei 5 giorni.
// Serve il cron di cPanel "php artisan schedule:run" ogni minuto (deploy.md).
Schedule::command('domande:scadenze')->hourly()->withoutOverlapping();

// Audit Log: verifica dell'intera catena il 1° di ogni mese alle 05:00
// (audit-log.md §9). Con anomalie avvisa Presidente e Revisori.
Schedule::command('audit:verifica-integrita')->monthlyOn(1, '05:00')->withoutOverlapping();

// Promemoria (promemoria.md, v0.36.0): ogni 15 minuti si inviano quelli
// arrivati alla loro data e ora (recupero nei 7 giorni dopo).
Schedule::command('promemoria:invia')->everyFifteenMinutes()->withoutOverlapping();

// Tessere CSEN (v0.37.0) e importazione dei soci (v0.40.0): i file caricati
// contengono dati personali; un'anteprima abbandonata si cancella dopo un
// giorno. Il nome del comando resta quello del cron già configurato.
Artisan::command('tessere:pulisci', function () {
    $disco = \Illuminate\Support\Facades\Storage::disk('local');
    $tolti = 0;
    foreach ([...$disco->files('tmp/tessere'), ...$disco->files('tmp/soci-import')] as $f) {
        if ($disco->lastModified($f) < now()->subDay()->timestamp) {
            $disco->delete($f);
            $tolti++;
        }
    }
    $this->info("File tolti: {$tolti}");
})->purpose('Cancella i file del CSEN e dei soci rimasti in anteprima da più di un giorno');
Schedule::command('tessere:pulisci')->dailyAt('03:30');
