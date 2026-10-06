<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware 'permesso:<sezione>', con la stessa sintassi del Gestionale
 * APS (Dante), incluso l'OR: 'permesso:soci|domande' passa se il ruolo
 * accede ad ALMENO UNA delle sezioni.
 *
 * Qui i permessi sono a sezione intera (permessi.md): il middleware
 * controlla solo l'accesso. La sola lettura NON blocca la rotta: sono le
 * viste e i controller a impedire le modifiche con puoModificare().
 *
 * Uso: ->middleware('permesso:soci')
 */
class PermessoMiddleware
{
    public function handle(Request $request, Closure $next, string $sezioni): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        foreach (explode('|', $sezioni) as $sezione) {
            if ($user->puoAccedere(trim($sezione))) {
                return $next($request);
            }
        }

        abort(403, 'Il tuo ruolo non ha accesso a questa sezione.');
    }
}
