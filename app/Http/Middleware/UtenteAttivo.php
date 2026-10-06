<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware 'attivo': ricontrolla a ogni richiesta che l'utente sia
 * ancora attivo, così la disattivazione ha effetto subito e non solo al
 * login successivo (stessa idea di CheckRole nel Gestionale APS).
 */
class UtenteAttivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->attivo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Il tuo account è stato disattivato.']);
        }

        return $next($request);
    }
}
