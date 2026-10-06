<?php

namespace App\Services;

use App\Models\AuditLogIntegrity;
use App\Models\Impostazione;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Consultazione dell'Audit Log (audit-log.md §9): filtri, etichette
 * leggibili, stato della catena per la fascia dell'elenco, riepilogo di un
 * anno per il certificato. La verifica vera resta in AuditIntegrityService.
 */
class AuditConsultazione
{
    /** Impostazione con l'esito dell'ultima verifica (automatica o a mano). */
    public const ULTIMA_VERIFICA = 'audit_ultima_verifica';

    /** Filtri dalla richiesta, già normalizzati. */
    public static function filtri(Request $request): array
    {
        $anno = (int) $request->query('anno', now()->year);
        $mese = (int) $request->query('mese', 0);

        return [
            'anno' => $anno >= 2000 && $anno <= 2100 ? $anno : now()->year,
            'mese' => $mese >= 1 && $mese <= 12 ? $mese : 0,
            'modello' => (string) $request->query('modello', ''),
            'rischio' => in_array($request->query('rischio'), ['HIGH', 'MEDIUM', 'LOW'], true) ? $request->query('rischio') : '',
            'evento' => (string) $request->query('evento', ''),
            'utente' => (string) $request->query('utente', ''),
            'cerca' => trim((string) $request->query('cerca', '')),
        ];
    }

    public static function attivi(array $f): bool
    {
        return $f['mese'] || $f['modello'] !== '' || $f['rischio'] !== '' || $f['evento'] !== '' || $f['utente'] !== '' || $f['cerca'] !== '';
    }

    public static function query(array $f): Builder
    {
        $q = DB::table('gl_audit_log')->where('anno', $f['anno']);

        if ($f['mese']) {
            $q->whereMonth('created_at', $f['mese']);
        }
        if ($f['modello'] !== '') {
            $q->where('modello', $f['modello']);
        }
        if ($f['rischio'] !== '') {
            $q->where('livello_rischio', $f['rischio']);
        }
        if ($f['evento'] !== '') {
            $q->where('evento', $f['evento']);
        }
        if ($f['utente'] !== '') {
            $q->where('utente_nome', $f['utente']);
        }
        if ($f['cerca'] !== '') {
            // % e _ scritti da chi cerca valgono come caratteri, non come jolly
            // (nel Gestionale APS no).
            $testo = '%' . addcslashes($f['cerca'], '%_\\') . '%';
            $numero = ltrim($f['cerca'], '#n. ');
            $q->where(function ($w) use ($testo, $numero) {
                $w->where('dettaglio', 'like', $testo);
                if (ctype_digit($numero)) {
                    $w->orWhere('id', (int) $numero)->orWhere('entita_id', (int) $numero);
                }
            });
        }

        return $q->orderByDesc('id');
    }

    /** Anni presenti nel registro, più quello in corso. */
    public static function anni(): Collection
    {
        return DB::table('gl_audit_log')->distinct()->orderByDesc('anno')->pluck('anno')
            ->push(now()->year)->map(fn ($a) => (int) $a)->unique()->sortDesc()->values();
    }

    public static function statistiche(int $anno): array
    {
        $righe = DB::table('gl_audit_log')->where('anno', $anno)
            ->selectRaw('livello_rischio, count(*) as n')->groupBy('livello_rischio')->pluck('n', 'livello_rischio');

        return [
            'totale' => (int) $righe->sum(),
            'HIGH' => (int) ($righe['HIGH'] ?? 0),
            'MEDIUM' => (int) ($righe['MEDIUM'] ?? 0),
            'LOW' => (int) (($righe['LOW'] ?? 0) + ($righe[''] ?? 0)),
            'operatori' => DB::table('gl_audit_log')->where('anno', $anno)->where('utente_nome', '!=', 'sistema')
                ->distinct()->count('utente_nome'),
        ];
    }

    /** Valori presenti nell'anno per le tendine dei filtri. */
    public static function opzioni(int $anno): array
    {
        $base = fn () => DB::table('gl_audit_log')->where('anno', $anno);

        return [
            'modelli' => $base()->whereNotNull('modello')->distinct()->orderBy('modello')->pluck('modello')
                ->mapWithKeys(fn ($m) => [$m => self::modello($m)])->sort(),
            'eventi' => $base()->whereNotNull('evento')->distinct()->pluck('evento')
                ->mapWithKeys(fn ($e) => [$e => self::evento($e)])->sort(),
            'operatori' => $base()->whereNotNull('utente_nome')->distinct()->orderBy('utente_nome')->pluck('utente_nome'),
        ];
    }

    /**
     * Stato per la fascia dell'elenco, senza ricalcolare la catena:
     * ultima verifica salvata e certificato dell'anno.
     *
     * @return array{stato: string, verifica: ?array, certificato: ?AuditLogIntegrity}
     */
    public static function stato(int $anno): array
    {
        $verifica = Impostazione::get(self::ULTIMA_VERIFICA);
        $certificato = AuditLogIntegrity::where('anno', $anno)->orderByDesc('id')->first();

        $stato = match (true) {
            $verifica && ($verifica['anomalie'] ?? 0) > 0 => 'anomalia',
            $certificato !== null => 'certificato',
            default => 'non_certificato',
        };

        return ['stato' => $stato, 'verifica' => $verifica ?: null, 'certificato' => $certificato];
    }

    /** Salva l'esito di una verifica: lo leggono la fascia dell'elenco e la pagina di verifica. */
    public static function registraVerifica(array $esito, string $modo): array
    {
        $dati = [
            'il' => now()->toIso8601String(),
            'modo' => $modo, // 'automatica' | 'manuale'
            'righe' => $esito['records_totali'],
            'anomalie' => count($esito['anomalie']),
            'primo_anomalo' => $esito['anomalie'][0]['id'] ?? null,
            'hash_finale' => $esito['hash_finale'],
        ];
        Impostazione::set(self::ULTIMA_VERIFICA, $dati, 'json', 'Esito dell\'ultima verifica della catena dell\'Audit Log (audit-log.md §9)');

        return $dati;
    }

    /**
     * Riepilogo di un anno per il certificato: righe per livello, prima e
     * ultima riga, hash dell'ultima riga dell'anno (verificato dalla catena).
     */
    public static function riepilogoAnno(int $anno): ?array
    {
        $prima = DB::table('gl_audit_log')->where('anno', $anno)->orderBy('id')->first();
        $ultima = DB::table('gl_audit_log')->where('anno', $anno)->orderByDesc('id')->first();
        if (! $prima) {
            return null;
        }

        return self::statistiche($anno) + [
            'prima_riga' => $prima->id,
            'ultima_riga' => $ultima->id,
            'hash_ultima' => $ultima->hash,
        ];
    }

    // ── Etichette ─────────────────────────────────────────────────────

    /** @return array{0: string, 1: string} etichetta e variante del badge */
    public static function rischio(?string $livello): array
    {
        return config('audit.rischi.' . ($livello ?: 'LOW'), ['Basso', 'neutral']);
    }

    public static function modello(?string $modello): string
    {
        return $modello ? (config('audit.modelli.' . $modello) ?? Str::headline($modello)) : '—';
    }

    public static function evento(?string $evento): string
    {
        if (! $evento) {
            return '—';
        }

        if ($etichetta = config('audit.eventi.' . $evento)) {
            return $etichetta;
        }

        // Eventi dell'observer (SOCIO_UPDATED, USER_CREATED…): "Socio: modifica".
        if (preg_match('/^([A-Z]+)_(CREATED|UPDATED|DELETED|RESTORED)$/', $evento, $m)) {
            $modello = collect(config('audit.modelli'))->first(fn ($label, $codice) => strtoupper($codice) === $m[1]) ?? Str::ucfirst(Str::lower($m[1]));

            // Sostantivo, non participio: "Domanda: eliminazione", non "eliminato".
            $azione = ['CREATED' => 'creazione', 'UPDATED' => 'modifica', 'DELETED' => 'eliminazione', 'RESTORED' => 'ripristino'][$m[2]];

            return "{$modello}: {$azione}";
        }

        return Str::ucfirst(Str::lower(str_replace('_', ' ', $evento)));
    }

    /** Come si chiama l'operazione di una riga: quella dell'observer o l'evento di business. */
    public static function operazione(object $riga): string
    {
        return $riga->operazione
            ? (config('audit.operazioni.' . $riga->operazione) ?? $riga->operazione)
            : self::evento($riga->evento);
    }

    /** Valore leggibile nella colonna prima/dopo. */
    public static function valore(mixed $v): string
    {
        return match (true) {
            $v === null || $v === '' => '—',
            is_bool($v) => $v ? 'sì' : 'no',
            is_array($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
            default => (string) $v,
        };
    }
}
