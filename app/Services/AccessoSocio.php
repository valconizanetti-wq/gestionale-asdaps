<?php

namespace App\Services;

use App\Mail\EmailAccessoCambiata;
use App\Models\Socio;
use App\Models\User;
use App\Support\InvioEmail;
use Illuminate\Support\Facades\Auth;

/**
 * Utente come «accesso» del socio (v0.44.1, analisi-soci-utenti.md,
 * decisioni di Paolo del 21/10/2026).
 *
 * Per chi è socio l'anagrafica è una sola, la scheda del socio:
 * - cognome, nome ed email dell'utente collegato si riprendono dalla scheda
 *   a ogni salvataggio (allinea), e non si scrivono altrove;
 * - un cambio dell'email avvisa il vecchio e il nuovo indirizzo;
 * - chi non è più socio (cessato, scheda eliminata) o esce dal CD senza
 *   altri ruoli non è più utente: eliminazione logica (elimina), che toglie
 *   i ruoli, blocca l'accesso e libera l'email. La riga resta per i
 *   registri («caricato da…»); l'Audit Log tiene i valori di prima.
 *   Rientrando nel CD lo stesso utente torna (ConsiglioDirettivo).
 */
class AccessoSocio
{
    public const MOTIVI = [
        'socio_cessato' => 'non è più socio',
        'scheda_eliminata' => 'scheda del socio eliminata',
        'uscito_cd' => 'uscito dal Consiglio Direttivo, senza altri ruoli',
    ];

    /** Dominio riservato (RFC 2606): l'email di un utente eliminato non è di nessuno. */
    public const DOMINIO_ELIMINATI = 'eliminato.invalid';

    /** Utente presente collegato al socio, o null. */
    public static function utente(Socio $socio): ?User
    {
        return User::presenti()->where('socio_id', $socio->id)->first();
    }

    /**
     * Dopo il salvataggio della scheda: l'utente prende cognome, nome ed
     * email del socio; se il socio non è più attivo, l'utente si elimina.
     */
    public static function allinea(Socio $socio): void
    {
        $utente = self::utente($socio);
        if (! $utente) {
            return;
        }
        if ($socio->trashed()) {
            self::elimina($utente, 'scheda_eliminata');

            return;
        }
        if ($socio->stato !== 'attivo') {
            self::elimina($utente, 'socio_cessato');

            return;
        }

        $vecchia = $utente->email;
        $email = mb_strtolower(trim((string) $socio->email));
        $utente->fill(['nome' => $socio->nome, 'cognome' => $socio->cognome] + ($email !== '' ? ['email' => $email] : []));
        if (! $utente->isDirty()) {
            return;
        }
        $utente->save();

        if ($vecchia !== $utente->email) {
            $da = Auth::user()?->name;
            $url = route('login');
            foreach ([[$vecchia, true], [$utente->email, false]] as [$indirizzo, $alVecchio]) {
                if (! str_ends_with((string) $indirizzo, '@' . self::DOMINIO_ELIMINATI)) {
                    InvioEmail::invia(new EmailAccessoCambiata($utente->name, $utente->email, $alVecchio, $da, $url),
                        $indirizzo, $utente->name, 'email_accesso_cambiata', socio: $socio);
                }
            }
        }
    }

    /**
     * Eliminazione logica: niente ruoli, niente accesso, email liberata.
     * Non tocca la regola «almeno un Presidente»: il Presidente non esce
     * dal CD e chi è nel CD non si cessa (ConsiglioDirettivo, SociController).
     */
    public static function elimina(User $utente, string $motivo): void
    {
        if ($utente->eliminato()) {
            return;
        }
        $ruoli = $utente->codiciRuoli();
        $utente->assegnaRuoli([]);
        $utente->forceFill([
            'attivo' => false,
            'eliminato_il' => now(),
            'email' => "utente-{$utente->id}@" . self::DOMINIO_ELIMINATI,
            'remember_token' => null,
        ])->save();

        AuditLogService::scrivi([
            'evento' => 'UTENTE_ELIMINATO',
            'operazione' => 'deleted',
            'entita_tipo' => 'User',
            'entita_id' => $utente->id,
            'livello_rischio' => 'HIGH',
            'dettaglio' => "Utente n. {$utente->id} eliminato (eliminazione logica): " . (self::MOTIVI[$motivo] ?? $motivo) . '.',
            'old_values' => ['ruoli' => $ruoli],
            'new_values' => ['motivo' => $motivo],
        ]);
    }

    /**
     * Errore per l'email della scheda di un socio, o null: se il socio ha
     * l'accesso, l'email serve per entrare e non può essere di un altro
     * utente.
     */
    public static function erroreEmail(?Socio $socio, ?string $email): ?string
    {
        $utente = $socio?->exists ? self::utente($socio) : null;
        if (! $utente) {
            return null;
        }
        $email = mb_strtolower(trim((string) $email));
        if ($email === '') {
            return "{$socio->cognome} {$socio->nome} entra nel gestionale con questa email: non si può togliere.";
        }
        $altro = User::whereRaw('LOWER(email) = ?', [$email])->where('id', '!=', $utente->id)->first();

        return $altro ? "Questa email è già quella con cui entra nel gestionale {$altro->name}: ognuno entra con la sua." : null;
    }
}
