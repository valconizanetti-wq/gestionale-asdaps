<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->limitiPaginePubbliche();
    }

    /**
     * Limiti per IP delle pagine pubbliche (routes/iscrizione.php).
     *
     * Ogni limitatore ha un contatore suo. Con "throttle:N,1" senza nome
     * Laravel usa una sola chiave per IP per tutte le rotte: pagine,
     * anteprima del codice fiscale e POST si sommavano, e dopo due giri
     * "Avanti / Modifica" il limite di 10 delle POST dava 429 (collaudo A1,
     * 29/09/2026).
     */
    private function limitiPaginePubbliche(): void
    {
        $perIp = fn (string $nome, int $alMinuto) => RateLimiter::for(
            $nome,
            fn (Request $request) => Limit::perMinute($alMinuto)->by($nome . '|' . $request->ip())
        );

        // Pagine in GET (modulo, riepilogo, informativa, statuto, voto).
        $perIp('pubblico-pagine', 120);
        // Anteprima del codice fiscale: una chiamata a ogni caricamento del
        // modulo e a ogni codice completo scritto.
        $perIp('pubblico-cf', 120);
        // Passo 1 → riepilogo: si può andare avanti e indietro più volte.
        $perIp('iscrizione-passo', 30);
        // Azioni che scrivono nel database o inviano email: invio della
        // domanda, conferma, annullamento, voto del consigliere.
        RateLimiter::for('pubblico-azioni', fn (Request $request) => [
            Limit::perMinute(10)->by('azioni-min|' . $request->ip()),
            Limit::perHour(40)->by('azioni-ora|' . $request->ip()),
        ]);
    }
}
