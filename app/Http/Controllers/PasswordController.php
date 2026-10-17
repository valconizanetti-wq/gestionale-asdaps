<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegolaPassword;

/**
 * «Password dimenticata?» e «Scegli la password» (v0.40.0, tavola UT-3b,
 * decisione D2). Pagine pubbliche nello stile dell'accesso.
 *
 * - La risposta alla richiesta è sempre la stessa, per non rivelare quali
 *   email sono di un utente; il link parte solo per un utente attivo.
 * - Il link è il broker di Laravel (tabella gl_password_reset_tokens,
 *   impronta del token, 24 ore, monouso, un nuovo invio dopo 60 secondi).
 * - Scelta la password si entra subito; la scelta va nell'Audit Log
 *   (MEDIUM, senza la password).
 */
class PasswordController extends Controller
{
    /** Richieste per indirizzo IP in un'ora. */
    private const MAX_RICHIESTE = 5;

    public const MINIMO = 12;

    public function dimenticata()
    {
        return view('auth.password-dimenticata');
    }

    public function inviaLink(Request $request)
    {
        $dati = $request->validate(['email' => ['required', 'email']]);

        $chiave = 'password-dimenticata|' . $request->ip();
        if (RateLimiter::tooManyAttempts($chiave, self::MAX_RICHIESTE)) {
            return back()->withErrors(['email' => 'Troppe richieste. Riprova tra ' . ceil(RateLimiter::availableIn($chiave) / 60) . ' minuti.']);
        }
        RateLimiter::hit($chiave, 3600);

        Password::broker()->sendResetLink(['email' => mb_strtolower(trim($dati['email'])), 'attivo' => true]);

        return back()->with('success', "Se l'email è di un utente del gestionale, tra poco riceverai il link per scegliere la password.");
    }

    public function scegli(Request $request, string $token)
    {
        $email = (string) $request->query('email');
        // O3.4 (v0.42.0): il link scaduto o già usato si dice subito, non
        // dopo aver scritto la password. Al salvataggio il controllo resta.
        $utente = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])->where('attivo', true)->first();
        $valido = $utente && Password::broker()->tokenExists($utente, $token);

        return view('auth.password-scegli', ['token' => $token, 'email' => $email, 'valido' => $valido]);
    }

    public function salva(Request $request)
    {
        $dati = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegolaPassword::min(self::MINIMO)],
        ], [], ['password' => 'password']);

        $utente = null;
        $esito = Password::broker()->reset(
            ['email' => mb_strtolower(trim($dati['email'])), 'attivo' => true] + $request->only('password', 'password_confirmation', 'token'),
            function (User $u, string $password) use (&$utente) {
                $u->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $utente = $u;
                event(new PasswordReset($u));
            }
        );

        if ($esito !== Password::PASSWORD_RESET || ! $utente) {
            return back()->withInput($request->only('email'))->withErrors(['email' => match ($esito) {
                Password::INVALID_TOKEN => 'Il link non è più valido (scaduto o già usato): chiedine un altro con «Password dimenticata?».',
                default => 'Email non riconosciuta: usa l\'indirizzo a cui è arrivato il link.',
            }]);
        }

        AuditLogService::scrivi([
            'evento' => 'UTENTE_PASSWORD',
            'operazione' => 'updated',
            'entita_tipo' => 'User',
            'entita_id' => $utente->id,
            'utente_nome' => $utente->name,
            'livello_rischio' => 'MEDIUM',
            'dettaglio' => "Password scelta dall'utente n. {$utente->id} con il link per email.",
        ]);

        Auth::logout();
        Auth::login($utente);
        $request->session()->regenerate();
        $utente->update(['ultimo_accesso' => now()]);

        return redirect()->route('dashboard')->with('success', 'Password salvata. Benvenuto nel gestionale.');
    }
}
