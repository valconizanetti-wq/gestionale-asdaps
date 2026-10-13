<?php

namespace App\Services;

use App\Models\Ruolo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Gestione degli utenti del gestionale (v0.40.0, analisi-ruoli-utenti.md
 * §3.3–3.5, decisioni di Paolo del 13/10/2026).
 *
 * Regole:
 * - almeno un ruolo; il Revisore da solo (statuto art. 20.1: Organo di
 *   controllo fuori dal CD); il Consigliere non insieme a Presidente,
 *   Segretario o Tesoriere (ne hanno già i permessi);
 * - resta sempre almeno un Presidente attivo; nessuno disattiva sé stesso;
 * - il Segretario (chi gestisce gli utenti senza essere Presidente) non dà
 *   né toglie il ruolo di Presidente, non modifica l'utente del Presidente,
 *   non cambia i propri ruoli;
 * - nessuna eliminazione: un utente si disattiva;
 * - cambi di ruoli nell'Audit Log (HIGH, prima e dopo), che manda l'avviso
 *   agli altri (AvvisiOperazioni); nome, email e stato li registra
 *   l'observer del model.
 */
class Utenti
{
    public const CARICHE = ['presidente', 'segretario', 'tesoriere'];

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
        if (in_array('consigliere', $codici, true) && array_intersect(self::CARICHE, $codici)) {
            return 'Consigliere è il ruolo di chi è nel CD senza cariche: con Presidente, Segretario o Tesoriere non serve.';
        }

        return null;
    }

    /** Chi gestisce senza essere Presidente: ha i limiti del Segretario. */
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
        if (self::conLimiti($chi) && $utente->haRuolo('presidente')) {
            return "L'utente del Presidente lo modifica solo il Presidente.";
        }

        return null;
    }

    /**
     * Crea o aggiorna un utente con i suoi ruoli.
     *
     * @param array{name: string, email: string, ruoli: list<string>, attivo: bool} $dati
     */
    public function salva(?User $utente, array $dati, User $chi): User
    {
        $nuovo = $utente === null;
        $ruoli = array_values(array_unique($dati['ruoli']));
        $ruoli = array_values(array_intersect(array_keys(Ruolo::etichette()), $ruoli)); // ordine e solo ruoli esistenti

        if ($e = self::erroreCombinazione($ruoli)) {
            throw ValidationException::withMessages(['ruoli' => $e]);
        }
        if (! $nuovo && ($e = self::motivoBlocco($chi, $utente))) {
            throw ValidationException::withMessages(['ruoli' => $e]);
        }

        $prima = $nuovo ? [] : $utente->codiciRuoli();
        if (self::conLimiti($chi)) {
            if (in_array('presidente', $ruoli, true) !== in_array('presidente', $prima, true)) {
                throw ValidationException::withMessages(['ruoli' => 'Il ruolo di Presidente lo dà o lo toglie solo il Presidente.']);
            }
            if (! $nuovo && $utente->is($chi) && $ruoli !== $prima) {
                throw ValidationException::withMessages(['ruoli' => 'I tuoi ruoli li può cambiare solo il Presidente.']);
            }
        }
        if (! $nuovo && $utente->is($chi) && ! $dati['attivo']) {
            throw ValidationException::withMessages(['attivo' => 'Non puoi disattivare il tuo utente.']);
        }

        return DB::transaction(function () use ($utente, $nuovo, $dati, $ruoli, $prima) {
            $utente ??= new User(['password' => \Illuminate\Support\Str::random(40)]);
            $utente->fill(['name' => trim($dati['name']), 'email' => mb_strtolower(trim($dati['email'])), 'attivo' => $dati['attivo']]);
            $utente->save();
            $utente->assegnaRuoli($ruoli);

            // Almeno un Presidente attivo, controllato dopo la modifica e
            // dentro la transazione (due modifiche insieme non lo aggirano).
            $presidenti = User::conRuolo('presidente')->where('attivo', true)->lockForUpdate()->count();
            if ($presidenti === 0) {
                throw ValidationException::withMessages(['ruoli' => "Deve restare almeno un Presidente attivo: prima dai il ruolo a un altro utente."]);
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
                        . ($prima ? User::elenco($nomi($prima)) . ' → ' : '') . User::elenco($nomi($ruoli)) . '.',
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
        return Password::broker()->sendResetLink(['email' => $utente->email]);
    }
}
