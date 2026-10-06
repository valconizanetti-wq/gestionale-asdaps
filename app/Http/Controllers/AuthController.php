<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login/logout. Struttura ripresa dal Gestionale APS (Dante), con due
 * aggiunte: blocco dei tentativi ripetuti e rifiuto degli utenti
 * disattivati già al login. Vedi auth.md.
 */
class AuthController extends Controller
{
    /** Tentativi di login consentiti per email+IP prima del blocco. */
    private const MAX_TENTATIVI = 5;

    /** Durata del blocco, in secondi. */
    private const DURATA_BLOCCO = 60;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $chiave = Str::lower($credentials['email']) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($chiave, self::MAX_TENTATIVI)) {
            $secondi = RateLimiter::availableIn($chiave);

            throw ValidationException::withMessages([
                'email' => "Troppi tentativi di accesso. Riprova tra {$secondi} secondi.",
            ]);
        }

        // 'attivo' => true: un utente disattivato non entra. Il messaggio
        // resta generico per non rivelare quali account esistono.
        if (Auth::attempt([...$credentials, 'attivo' => true], $request->boolean('remember'))) {
            RateLimiter::clear($chiave);
            $request->session()->regenerate();

            Auth::user()->update(['ultimo_accesso' => now()]);

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($chiave, self::DURATA_BLOCCO);

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => 'Email o password non corrette.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
