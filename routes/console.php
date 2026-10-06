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
