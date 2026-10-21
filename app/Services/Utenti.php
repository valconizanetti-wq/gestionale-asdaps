<?php

namespace App\Services;

use App\Models\Ruolo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Gestione degli utenti del gestionale (v0.40.0, analisi-ruoli-utenti.md
 * §3.3–3.5; rivista nella v0.42.0, analisi-collaudo-O.md §10 e §15).
 *
 * Dalla v0.42.0 i ruoli del CD (Presidente, Vicepresidente, Segretario,
 * Tesoriere, Consigliere) nascono dalla pagina Consiglio Direttivo
 * (App\Services\ConsiglioDirettivo) e qui non si cambiano. Qui restano:
 * - gli utenti esterni: Revisore (esterno alla Compagnia, decisione di Paolo del 18/10/2026; statuto art. 20.1) o
 *   solo Amministratore, con cognome e nome scritti a mano;
 * - per tutti: email, stato, Amministratore di sistema in più.
 *
 * Regole:
 * - Revisore e Amministratore: uno solo fra gli utenti attivi;
 * - l'Amministratore non si unisce a Presidente e Revisore;
 * - resta sempre almeno un Presidente attivo; nessuno disattiva sé stesso;
 * - chi gestisce senza essere Presidente (Segretario, Amministratore) non
 *   cambia i propri ruoli; il Segretario non modifica l'utente del
 *   Presidente, l'Amministratore sì (email, link della password: è il
 *   rimedio a una password o un'email perse);
 * - da qui nessuna eliminazione: un utente si disattiva. L'utente di un
 *   socio si elimina (logicamente) solo quando il socio esce o lascia il CD
 *   senza altri ruoli (AccessoSocio, v0.44.1), e i suoi cognome, nome ed
 *   email vengono sempre dalla scheda del socio;
 * - cambi di ruoli nell'Audit Log (HIGH, prima e dopo), che manda l'avviso
 *   agli altri (AvvisiOperazioni); nome, email e stato li registra
 *   l'observer del model.
 */
class Utenti
{
    public const CARICHE = ['presidente', 'segretario', 'tesoriere'];

    /** Ruoli fuori dal CD, che si danno da qui. */
    public const ESTERNI = ['revisore', 'amministratore'];

    /** Ruoli di una sola persona fra gli utenti attivi (quelli del CD li controlla il CD). */
    public const UNICI = ['revisore', 'amministratore'];

    /**
     * Combinazioni non ammesse: messaggio per l'utente, o null.
     *
     * @param list<string> $codici
     */
    public static function erroreCombinazione(array $codici): ?string
    {
        if (! $codici) {
            return 'Scegli almeno un ruolo.';
        }
        if (in_array('revisore', $codici, true) && count($codici) > 1) {
            return "Il Revisore non può avere altri ruoli: l'Organo di controllo è fuori dal Consiglio Direttivo (statuto, art. 20.1).";
        }
        if (in_array('amministratore', $codici, true) && in_array('presidente', $codici, true)) {
            return "L'Amministratore di sistema non si unisce al Presidente.";
        }

        return null;
    }

    /** L'utente viene dal CD (collegato a un socio o con ruoli del CD)? */
    public static function delCd(User $utente): bool
    {
        return $utente->socio_id !== null || (bool) array_intersect($utente->codiciRuoli(), ConsiglioDirettivo::RUOLI_CD);
    }

    /** Chi gestisce senza essere Presidente: non cambia i propri ruoli. */
    public static function conLimiti(User $chi): bool
    {
        return ! $chi->haRuolo('presidente');
    }

    /** Il gestore può modificare questo utente? Messaggio se no. */
    public static function motivoBlocco(User $chi, User $utente): ?string
    {
        if (! $chi->puoModificare('utenti')) {
            return 'Il tuo ruolo può solo consultare gli utenti.';
        }
        if (self::conLimiti($chi) && ! $chi->haRuolo('amministratore') && $utente->haRuolo('presidente')) {
            return "L'utente del Presidente lo modificano solo il Presidente e l'Amministratore.";
        }

        return null;
    }

    /**
     * Crea (solo esterni) o aggiorna un utente.
     *
     * @param array{cognome?: string, nome?: string, email: string, revisore?: bool, amministratore: bool, attivo: bool} $dati
     */
    public function salva(?User $utente, array $dati, User $chi): User
    {
        $nuovo = $utente === null;
        if (! $nuovo && ($e = self::motivoBlocco($chi, $utente))) {
            throw ValidationException::withMessages(['ruoli' => $e]);
        }
        $delCd = ! $nuovo && self::delCd($utente);
        $prima = $nuovo ? [] : $utente->codiciRuoli();

        // Ruoli: quelli del CD restano come sono; da qui Revisore (esterni) e Amministratore
        $ruoli = $delCd ? array_values(array_intersect($prima, ConsiglioDirettivo::RUOLI_CD))
            : (! empty($dati['revisore']) ? ['revisore'] : []);
        if (! empty($dati['amministratore'])) {
            $ruoli[] = 'amministratore';
        }
        $ruoli = array_values(array_intersect(array_keys(Ruolo::etichette()), $ruoli)); // ordine

        // Senza ruoli solo se disattivato (es. uscito dal CD)
        if (($ruoli || $dati['attivo']) && ($e = self::erroreCombinazione($ruoli))) {
            throw ValidationException::withMessages(['ruoli' => $e]);
        }
        if (! $nuovo && $utente->is($chi) && self::conLimiti($chi) && $ruoli !== $prima) {
            throw ValidationException::withMessages(['ruoli' => 'I tuoi ruoli li può cambiare solo il Presidente.']);
        }
        if (! $nuovo && $utente->is($chi) && ! $dati['attivo']) {
            throw ValidationException::withMessages(['attivo' => 'Non puoi disattivare il tuo utente.']);
        }

        return DB::transaction(function () use ($utente, $nuovo, $delCd, $dati, $ruoli, $prima) {
            $utente ??= new User(['password' => \Illuminate\Support\Str::random(40)]);
            $campi = ['email' => mb_strtolower(trim($dati['email'])), 'attivo' => $dati['attivo']];
            // Utente di un socio: cognome, nome ed email dalla scheda (v0.44.1)
            if ($utente->socio) {
                $email = mb_strtolower(trim((string) $utente->socio->email)) ?: $utente->email;
                if (User::whereRaw('LOWER(email) = ?', [$email])->where('id', '!=', $utente->id)->exists()) {
                    throw ValidationException::withMessages(['attivo' => "L'email della scheda del socio ({$email}) è già di un altro utente: correggila nella scheda."]);
                }
                $campi = ['email' => $email, 'attivo' => $dati['attivo'],
                    'cognome' => $utente->socio->cognome, 'nome' => $utente->socio->nome];
            } elseif (! $delCd) {
                $campi += ['cognome' => trim($dati['cognome'] ?? ''), 'nome' => trim($dati['nome'] ?? '')];
            }
            $utente->fill($campi);
            $utente->save();
            $utente->assegnaRuoli($ruoli);

            // Uno solo per ruolo fra gli attivi; almeno un Presidente attivo.
            // Controllati dopo la modifica e dentro la transazione (due
            // modifiche insieme non li aggirano).
            // Solo i ruoli dell'utente modificato (revisione R2.1: dati vecchi
            // fuori regola non bloccano modifiche che non li toccano)
            foreach (array_intersect(self::UNICI, $dati['attivo'] ? $ruoli : []) as $r) {
                $altri = User::conRuolo($r)->where('attivo', true)->lockForUpdate()->get();
                if ($altri->count() > 1) {
                    $di = $altri->first(fn (User $u) => ! $u->is($utente));
                    throw ValidationException::withMessages(['ruoli' => 'Il ruolo di ' . Ruolo::etichette()[$r] . " è già di {$di?->name}: uno solo fra gli utenti attivi."]);
                }
            }
            if (in_array('presidente', $prima, true) && ! ($dati['attivo'] && in_array('presidente', $ruoli, true))
                && User::conRuolo('presidente')->where('attivo', true)->lockForUpdate()->count() === 0) {
                throw ValidationException::withMessages(['attivo' => 'Deve restare almeno un Presidente attivo.']);
            }

            if ($ruoli !== $prima) {
                $etichette = Ruolo::etichette();
                $nomi = fn (array $c) => array_map(fn ($x) => $etichette[$x] ?? $x, $c);
                AuditLogService::scrivi([
                    'evento' => 'UTENTE_RUOLI',
                    'operazione' => $nuovo ? 'created' : 'updated',
                    'entita_tipo' => 'User',
                    'entita_id' => $utente->id,
                    'livello_rischio' => 'HIGH',
                    'dettaglio' => ($nuovo ? "Nuovo utente n. {$utente->id} con i ruoli: " : "Ruoli dell'utente n. {$utente->id}: ")
                        . ($prima ? User::elenco($nomi($prima)) . ' → ' : '') . ($ruoli ? User::elenco($nomi($ruoli)) : 'nessuno') . '.',
                    'old_values' => ['ruoli' => $prima],
                    'new_values' => ['ruoli' => $ruoli],
                    'campi_modificati' => ['ruoli'],
                ]);
            }

            return $utente;
        });
    }

    /**
     * Link «Scegli la password» (D2): monouso, 24 ore (config/auth.php).
     * Restituisce lo stato del broker (Password::RESET_LINK_SENT o
     * Password::RESET_THROTTLED…).
     */
    public function inviaLink(User $utente): string
    {
        return Password::broker()->sendResetLink(['email' => $utente->email, 'attivo' => true]);
    }
}
