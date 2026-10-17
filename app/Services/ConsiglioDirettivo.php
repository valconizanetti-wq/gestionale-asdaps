<?php

namespace App\Services;

use App\Models\GovernanceCd;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Composizione del Consiglio Direttivo e utenti che ne discendono (v0.42.0,
 * analisi-collaudo-O.md §15, scelta B di Paolo del 17/10/2026; tavole
 * CD-1…CD-4).
 *
 * Una sola fonte: i soci in carica in gl_governance_cd. Da lì nascono gli
 * utenti del CD, con i ruoli della carica; il voto sulle domande e il nome
 * del Tesoriere leggono la stessa tabella.
 *
 * Regole (statuto artt. 15.1, 15.2):
 * - da tre a cinque membri: sopra cinque non si aggiunge, sotto tre solo
 *   avviso (il CD si compone un membro alla volta);
 * - cariche uniche Presidente, Vicepresidente, Segretario, Tesoriere;
 *   «Segretario e Tesoriere» le occupa tutte e due;
 * - un socio ha una sola carica; solo soci attivi, con l'email;
 * - il Presidente non si toglie e non cambia carica da solo: si passa a un
 *   altro con «Cambia carica» su chi lo riceve;
 * - la carica di Presidente la gestiscono il Presidente e l'Amministratore
 *   (per quando il Presidente non c'è più); le altre anche il Segretario.
 *
 * Utenti: il socio che entra ha già un utente (per socio_id o per la stessa
 * email) oppure ne nasce uno con il link per la password. I ruoli del CD
 * seguono la carica; quelli fuori dal CD (Amministratore) restano. Chi esce
 * perde i ruoli del CD e, senza altri ruoli, si disattiva. Un ruolo unico
 * dato a un membro si toglie a ogni altro utente che lo avesse ancora (es.
 * gli utenti di prova dopo l'azzeramento).
 */
class ConsiglioDirettivo
{
    /** codice => [etichetta, ruoli degli utenti]. */
    public const CARICHE = [
        'presidente' => ['Presidente', ['presidente']],
        'vicepresidente' => ['Vicepresidente', ['vicepresidente']],
        'segretario' => ['Segretario', ['segretario']],
        'tesoriere' => ['Tesoriere', ['tesoriere']],
        'segretario_tesoriere' => ['Segretario e Tesoriere', ['segretario', 'tesoriere']],
        'consigliere' => ['Consigliere', ['consigliere']],
    ];

    /** Ruoli che vengono dal CD (gli altri, Amministratore e Revisore, no). */
    public const RUOLI_CD = ['presidente', 'vicepresidente', 'segretario', 'tesoriere', 'consigliere'];

    /** Ruoli di una sola persona fra i membri in carica. */
    public const UNICI = ['presidente', 'vicepresidente', 'segretario', 'tesoriere'];

    public const MOTIVI_FINE = [
        'dimissioni' => 'Dimissioni',
        'decadenza' => 'Decadenza',
        'fine_mandato' => 'Fine mandato',
        'altro' => 'Altro',
    ];

    public const MINIMO = 3;

    public const MASSIMO = 5;

    /** @return Collection<int, GovernanceCd> membri in carica, nell'ordine delle cariche */
    public static function membri(): Collection
    {
        $ordine = array_flip(array_keys(self::CARICHE));

        return GovernanceCd::with('socio')->where('in_carica', true)->get()
            ->sortBy(fn (GovernanceCd $m) => [$ordine[$m->carica_codice] ?? 99, $m->socio?->cognome, $m->id])
            ->values();
    }

    /** @return list<string> ruoli degli utenti per la carica */
    public static function ruoliCarica(?string $carica): array
    {
        return self::CARICHE[$carica][1] ?? [];
    }

    public static function etichetta(?string $carica): string
    {
        return self::CARICHE[$carica][0] ?? (string) $carica;
    }

    /** Ruolo unico => membro in carica che lo ha. @return array<string, GovernanceCd> */
    public static function occupati(?int $tranne = null): array
    {
        $occupati = [];
        foreach (self::membri() as $m) {
            if ($m->id === $tranne) {
                continue;
            }
            foreach (array_intersect(self::ruoliCarica($m->carica_codice), self::UNICI) as $r) {
                $occupati[$r] = $m;
            }
        }

        return $occupati;
    }

    /** La carica tocca il Presidente? */
    private static function diPresidente(?string $carica): bool
    {
        return $carica === 'presidente';
    }

    /** Chi può gestire la carica: messaggio se no. */
    public static function motivoBlocco(User $chi, ?string $carica): ?string
    {
        if (self::diPresidente($carica)) {
            return $chi->haRuolo('presidente', 'amministratore') ? null
                : 'La carica di Presidente la gestiscono solo il Presidente e l\'Amministratore.';
        }

        return $chi->puoModificare('governance_cd') ? null : 'Il tuo ruolo può solo consultare il Consiglio Direttivo.';
    }

    /** Utente del socio: collegato, o con la stessa email. */
    public static function utenteDelSocio(Socio $socio): ?User
    {
        return User::where('socio_id', $socio->id)->first()
            ?? ($socio->email ? User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($socio->email))])->first() : null);
    }

    // ----------------------------------------------------------------
    // Aggiungi
    // ----------------------------------------------------------------

    /**
     * @return array{membro: GovernanceCd, utente: User, nuovo: bool, link: bool}
     */
    public function aggiungi(Socio $socio, string $carica, Carbon $dal, ?string $nominatoDa, bool $link, User $chi): array
    {
        if (! isset(self::CARICHE[$carica])) {
            throw ValidationException::withMessages(['carica' => 'Scegli la carica.']);
        }
        if ($m = self::motivoBlocco($chi, $carica)) {
            throw ValidationException::withMessages(['carica' => $m]);
        }
        if ($socio->stato !== 'attivo' || $socio->trashed()) {
            throw ValidationException::withMessages(['socio_id' => 'Nel CD entrano solo i soci attivi.']);
        }
        if ($socio->caricheCd()->where('in_carica', true)->exists()) {
            throw ValidationException::withMessages(['socio_id' => "{$socio->cognome} {$socio->nome} è già nel Consiglio Direttivo: per cambiare carica usa la matita sulla sua riga."]);
        }
        if (! filled($socio->email)) {
            throw ValidationException::withMessages(['socio_id' => "{$socio->cognome} {$socio->nome} non ha l'email nella scheda: aggiungila prima, serve per entrare nel gestionale e per i voti del CD."]);
        }
        $occupati = self::occupati();
        foreach (self::ruoliCarica($carica) as $r) {
            if (isset($occupati[$r])) {
                $di = $occupati[$r]->socio;
                throw ValidationException::withMessages(['carica' => 'La carica di ' . self::etichetta($r) . " è già di {$di?->cognome} {$di?->nome}: aggiungi il socio con un'altra carica, poi usa «Cambia carica»."]);
            }
        }
        if (self::membri()->count() >= self::MASSIMO) {
            throw ValidationException::withMessages(['socio_id' => 'Il Consiglio Direttivo ha già cinque membri, il massimo dello statuto (art. 15.1).']);
        }

        $utente = self::utenteDelSocio($socio);
        if ($utente && $utente->socio_id && $utente->socio_id !== $socio->id) {
            throw ValidationException::withMessages(['socio_id' => "L'utente con l'email del socio ({$utente->email}) è collegato a un altro socio: correggi l'email nella scheda o nella pagina Utenti."]);
        }
        if ($utente && $utente->haRuolo('revisore')) {
            throw ValidationException::withMessages(['socio_id' => "{$socio->cognome} {$socio->nome} è Revisore: l'Organo di controllo è fuori dal Consiglio Direttivo (statuto, art. 20.1)."]);
        }

        $esito = DB::transaction(function () use ($socio, $carica, $dal, $nominatoDa, $chi, $utente) {
            $membro = GovernanceCd::create([
                'socio_id' => $socio->id,
                'carica' => self::etichetta($carica),
                'data_inizio_mandato' => $dal->toDateString(),
                'in_carica' => true,
                'delibera_assemblea' => filled($nominatoDa) ? mb_substr(trim($nominatoDa), 0, 255) : null,
                'inserito_da' => $chi->id,
            ]);
            $membro->forceFill(['carica_codice' => $carica])->save();

            $nuovo = $utente === null;
            $utente ??= new User(['password' => \Illuminate\Support\Str::random(40), 'email' => mb_strtolower(trim($socio->email))]);
            $utente->fill(['nome' => $socio->nome, 'cognome' => $socio->cognome, 'socio_id' => $socio->id, 'attivo' => true]);
            $utente->save();
            $prima = $nuovo ? [] : $utente->codiciRuoli();
            $this->ruoliCd($utente, self::ruoliCarica($carica));
            $tolti = $this->togliUniciAgliAltri($utente, self::ruoliCarica($carica));

            AuditLogService::scrivi([
                'evento' => 'CD_MEMBRO_AGGIUNTO',
                'operazione' => 'created',
                'entita_tipo' => 'GovernanceCd',
                'entita_id' => $membro->id,
                'livello_rischio' => 'HIGH',
                'dettaglio' => "Socio n. {$socio->id} nel Consiglio Direttivo come " . self::etichetta($carica)
                    . " dal {$dal->format('d/m/Y')}; utente n. {$utente->id}" . ($nuovo ? ' creato' : ' esistente')
                    . ($tolti ? '; ruolo tolto agli utenti n. ' . implode(', ', $tolti) : '') . '.',
                'old_values' => ['ruoli_utente' => $prima],
                'new_values' => ['carica' => $carica, 'dal' => $dal->toDateString(), 'nominato_da' => $nominatoDa, 'ruoli_utente' => $utente->codiciRuoli()],
            ]);

            return ['membro' => $membro, 'utente' => $utente, 'nuovo' => $nuovo];
        });

        $esito['link'] = $link && $this->inviaLink($esito['utente']);

        return $esito;
    }

    // ----------------------------------------------------------------
    // Cambia carica (con il passaggio)
    // ----------------------------------------------------------------

    /**
     * Nuova carica per un membro. Chi aveva una delle cariche uniche che
     * servono la lascia: tiene la parte che resta libera (es. «Segretario e
     * Tesoriere» che cede il Tesoriere resta Segretario), altrimenti resta
     * Consigliere ($altri = 'consigliere') o esce dal CD ($altri = 'esce').
     */
    public function cambiaCarica(GovernanceCd $membro, string $carica, Carbon $dal, string $motivo, string $altri, User $chi): GovernanceCd
    {
        if (! $membro->in_carica) {
            throw ValidationException::withMessages(['carica' => 'Il membro non è più nel Consiglio Direttivo.']);
        }
        if (! isset(self::CARICHE[$carica])) {
            throw ValidationException::withMessages(['carica' => 'Scegli la carica.']);
        }
        if ($carica === $membro->carica_codice) {
            throw ValidationException::withMessages(['carica' => 'È già la sua carica.']);
        }
        if (self::diPresidente($membro->carica_codice)) {
            throw ValidationException::withMessages(['carica' => 'Il Presidente non cambia carica da solo: usa «Cambia carica» sul membro che diventa Presidente.']);
        }
        $occupati = self::occupati($membro->id);
        $cedono = collect(self::ruoliCarica($carica))->filter(fn ($r) => isset($occupati[$r]))
            ->map(fn ($r) => $occupati[$r])->unique('id')->values();

        foreach ([$carica, ...$cedono->pluck('carica_codice')->all()] as $c) {
            if ($m = self::motivoBlocco($chi, $c)) {
                throw ValidationException::withMessages(['carica' => $m]);
            }
        }
        // La propria carica (anche quando la si cede a un altro) la cambiano
        // solo il Presidente e l'Amministratore (revisione R1.2)
        if (! $chi->haRuolo('presidente', 'amministratore')
            && collect([$membro, ...$cedono])->contains(fn ($m) => $chi->socio_id !== null && $m->socio_id === $chi->socio_id)) {
            throw ValidationException::withMessages(['carica' => 'La tua carica la cambia il Presidente.']);
        }

        return DB::transaction(function () use ($membro, $carica, $dal, $motivo, $altri, $cedono) {
            $prima = $membro->carica_codice;
            $membro->forceFill(['carica_codice' => $carica, 'carica' => self::etichetta($carica)])->save();
            $righe = ['membro' => ['da' => $prima, 'a' => $carica]];

            foreach ($cedono as $altro) {
                $resto = array_values(array_diff(self::ruoliCarica($altro->carica_codice), self::ruoliCarica($carica)));
                $nuova = match (true) {
                    $resto === ['segretario'] => 'segretario',
                    $resto === ['tesoriere'] => 'tesoriere',
                    $altri === 'consigliere' => 'consigliere',
                    default => null,
                };
                $righe['altri'][$altro->id] = ['da' => $altro->carica_codice, 'a' => $nuova ?? 'esce'];
                if ($nuova) {
                    $altro->forceFill(['carica_codice' => $nuova, 'carica' => self::etichetta($nuova)])->save();
                    $this->allineaUtente($altro);
                } else {
                    $this->chiudi($altro, $dal, 'altro', 'Ha lasciato la carica di ' . self::etichetta($altro->carica_codice)
                        . " al membro n. {$membro->id}.");
                }
            }
            $utente = $this->allineaUtente($membro);
            $tolti = $utente ? $this->togliUniciAgliAltri($utente, self::ruoliCarica($carica)) : [];

            AuditLogService::scrivi([
                'evento' => 'CD_CARICA',
                'operazione' => 'updated',
                'entita_tipo' => 'GovernanceCd',
                'entita_id' => $membro->id,
                'livello_rischio' => 'HIGH',
                'dettaglio' => "Carica del membro n. {$membro->id} (socio n. {$membro->socio_id}): " . self::etichetta($prima)
                    . ' → ' . self::etichetta($carica) . " dal {$dal->format('d/m/Y')}"
                    . collect($righe['altri'] ?? [])->map(fn ($r, $id) => "; membro n. {$id}: " . self::etichetta($r['da']) . ' → ' . ($r['a'] === 'esce' ? 'fuori dal CD' : self::etichetta($r['a'])))->implode('')
                    . ($tolti ? '; ruolo tolto agli utenti n. ' . implode(', ', $tolti) : '') . ', con motivazione.',
                'old_values' => ['carica' => $prima],
                'new_values' => ['carica' => $carica, 'dal' => $dal->toDateString(), 'motivazione' => $motivo, 'altri' => $righe['altri'] ?? []],
            ]);

            return $membro;
        });
    }

    // ----------------------------------------------------------------
    // Togli dal CD
    // ----------------------------------------------------------------

    public function togli(GovernanceCd $membro, Carbon $al, string $motivo, ?string $nota, User $chi): void
    {
        if (! $membro->in_carica) {
            throw ValidationException::withMessages(['al' => 'Il membro non è più nel Consiglio Direttivo.']);
        }
        if (self::diPresidente($membro->carica_codice)) {
            throw ValidationException::withMessages(['al' => 'Il Presidente non si toglie: prima passa la carica a un altro membro con «Cambia carica».']);
        }
        if ($m = self::motivoBlocco($chi, $membro->carica_codice)) {
            throw ValidationException::withMessages(['al' => $m]);
        }
        if (User::where('socio_id', $membro->socio_id)->first()?->is($chi)) {
            throw ValidationException::withMessages(['al' => 'Non puoi toglierti dal CD da solo: lo fa il Presidente.']);
        }
        if ($al->lt($membro->data_inizio_mandato)) {
            throw ValidationException::withMessages(['al' => 'La data non può essere prima dell\'ingresso nel CD (' . $membro->data_inizio_mandato->format('d/m/Y') . ').']);
        }
        if (! isset(self::MOTIVI_FINE[$motivo])) {
            throw ValidationException::withMessages(['motivo' => 'Scegli il motivo.']);
        }

        DB::transaction(function () use ($membro, $al, $motivo, $nota) {
            $utente = $this->chiudi($membro, $al, $motivo, $nota);
            AuditLogService::scrivi([
                'evento' => 'CD_MEMBRO_TOLTO',
                'operazione' => 'updated',
                'entita_tipo' => 'GovernanceCd',
                'entita_id' => $membro->id,
                'livello_rischio' => 'HIGH',
                'dettaglio' => "Membro n. {$membro->id} (socio n. {$membro->socio_id}, " . self::etichetta($membro->carica_codice)
                    . ") fuori dal Consiglio Direttivo dal {$al->format('d/m/Y')}: " . mb_strtolower(self::MOTIVI_FINE[$motivo])
                    . ($utente ? "; utente n. {$utente->id} " . ($utente->attivo ? 'con i soli ruoli fuori dal CD' : 'disattivato') : '') . '.',
                'new_values' => ['al' => $al->toDateString(), 'motivo' => $motivo, 'nota' => $nota],
            ]);
        });
    }

    // ----------------------------------------------------------------
    // Utenti
    // ----------------------------------------------------------------

    /** Membro fuori dal CD; il suo utente perde i ruoli del CD. */
    private function chiudi(GovernanceCd $membro, Carbon $al, string $motivo, ?string $nota = null): ?User
    {
        $membro->forceFill([
            'in_carica' => false,
            'data_fine_mandato' => $al->toDateString(),
            'motivo_fine' => $motivo,
            'note' => filled($nota) ? trim(($membro->note ? $membro->note . "\n" : '') . $nota) : $membro->note,
        ])->save();

        return $this->allineaUtente($membro);
    }

    /** Ruoli del CD dell'utente del membro secondo la sua carica (nessuno se fuori). */
    private function allineaUtente(GovernanceCd $membro): ?User
    {
        $utente = User::where('socio_id', $membro->socio_id)->first();
        if ($utente) {
            $this->ruoliCd($utente, $membro->in_carica ? self::ruoliCarica($membro->carica_codice) : []);
        }

        return $utente;
    }

    /**
     * Sostituisce i ruoli del CD dell'utente, tenendo gli altri. Senza ruoli
     * l'utente si disattiva.
     *
     * @param list<string> $ruoli
     */
    private function ruoliCd(User $utente, array $ruoli): void
    {
        $altri = array_values(array_diff($utente->codiciRuoli(), self::RUOLI_CD));
        // L'Amministratore non si unisce al Presidente (§10)
        if (in_array('presidente', $ruoli, true)) {
            $altri = array_values(array_diff($altri, ['amministratore']));
        }
        $tutti = [...$ruoli, ...$altri];
        $utente->assegnaRuoli($tutti);
        if (! $tutti && $utente->attivo) {
            $utente->forceFill(['attivo' => false])->save();
        }
    }

    /**
     * Toglie i ruoli unici dati al membro a ogni altro utente che li avesse
     * ancora (utenti di prova, ruoli dati prima della v0.42.0).
     *
     * @param list<string> $ruoli
     * @return list<int> utenti toccati
     */
    private function togliUniciAgliAltri(User $utente, array $ruoli): array
    {
        $unici = array_values(array_intersect($ruoli, self::UNICI));
        if (! $unici) {
            return [];
        }
        $toccati = [];
        foreach (User::conRuolo(...$unici)->where('id', '!=', $utente->id)->get() as $altro) {
            $resto = array_values(array_diff($altro->codiciRuoli(), $unici));
            $altro->assegnaRuoli($resto);
            if (! $resto && $altro->attivo) {
                $altro->forceFill(['attivo' => false])->save();
            }
            $toccati[] = $altro->id;
        }

        return $toccati;
    }

    /** Link per la password, se l'utente è attivo. */
    public function inviaLink(User $utente): bool
    {
        if (! $utente->attivo) {
            return false;
        }
        $ok = Password::broker()->sendResetLink(['email' => $utente->email, 'attivo' => true]) === Password::RESET_LINK_SENT;
        if ($ok) {
            AuditLogService::scrivi([
                'evento' => 'UTENTE_LINK_PASSWORD',
                'operazione' => 'updated',
                'entita_tipo' => 'User',
                'entita_id' => $utente->id,
                'livello_rischio' => 'LOW',
                'dettaglio' => "Link per la password inviato all'utente n. {$utente->id}.",
            ]);
        }

        return $ok;
    }
}
