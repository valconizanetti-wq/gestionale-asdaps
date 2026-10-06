<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias dei middleware dell'area riservata (vedi auth.md)
        $middleware->alias([
            'attivo'   => \App\Http\Middleware\UtenteAttivo::class,
            'permesso' => \App\Http\Middleware\PermessoMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 = token del modulo non più valido (sessione scaduta o cambiata
        // dopo il caricamento della pagina). Invece della pagina d'errore:
        // - su "Esci" si porta semplicemente al login;
        // - altrove si torna al modulo con i dati inseriti e un avviso.
        // Vedi auth.md §3.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->routeIs('logout')) {
                return redirect()->route('login');
            }

            return back()
                ->withInput($request->except(['_token', 'password']))
                ->with('error', 'La sessione è scaduta: controlla i dati e riprova.');
        });
    })->create();
