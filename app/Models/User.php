<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[ObservedBy([AuditObserver::class])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'gl_users';

    protected $fillable = [
        'name',
        'nome',
        'cognome',
        'socio_id',
        'email',
        'password',
        'attivo',
        'ultimo_accesso',
    ];

    /**
     * Permessi del ruolo, caricati una sola volta per richiesta:
     * codice sezione => sola_lettura. Vedi permessi.md §4.
     *
     * @var array<string, bool>|null
     */
    protected ?array $permessiSezioni = null;

    /** Codici dei ruoli, caricati una volta per richiesta. @var list<string>|null */
    protected ?array $codiciRuoli = null;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'attivo' => 'boolean',
            'eliminato_il' => 'datetime',
            'ultimo_accesso' => 'datetime',
        ];
    }

    /**
     * Cognome e nome separati (v0.42.0, O2.1). `name` resta «Nome Cognome»
     * per email, Audit Log e testi: si ricompone qui a ogni salvataggio.
     * Chi scrive solo `name` (vecchio codice, factory) ottiene nome e
     * cognome divisi alla prima parola.
     */
    protected static function booted(): void
    {
        static::saving(function (User $u) {
            if ($u->isDirty(['nome', 'cognome']) && (filled($u->nome) || filled($u->cognome))) {
                $u->name = trim($u->nome . ' ' . $u->cognome);
            } elseif ($u->isDirty('name') && blank($u->nome) && blank($u->cognome)) {
                $parti = preg_split('/\s+/u', trim((string) $u->name), 2);
                $u->nome = $parti[0] ?? '';
                $u->cognome = $parti[1] ?? '';
            }
        });
    }

    /** Socio collegato (utenti del Consiglio Direttivo, v0.42.0). */
    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    /**
     * Utenti non eliminati (v0.44.1, analisi-soci-utenti.md): l'utente di chi
     * non è più socio resta solo per i registri («caricato da…»).
     */
    public function scopePresenti(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('gl_users.eliminato_il');
    }

    public function eliminato(): bool
    {
        return $this->eliminato_il !== null;
    }

    /** «Zanetti Paolo», per gli elenchi in ordine di cognome. */
    public function cognomeNome(): string
    {
        return trim($this->cognome . ' ' . $this->nome) ?: (string) $this->name;
    }

    /**
     * Ruoli dell'utente (v0.40.0, analisi-ruoli-utenti.md §3.2): tabella
     * ponte gl_users_ruoli sul codice del ruolo, nell'ordine di gl_ruoli.
     */
    public function ruoli(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Ruolo::class, 'gl_users_ruoli', 'user_id', 'ruolo_codice', 'id', 'codice')
            ->orderBy('gl_ruoli.ordine');
    }

    /** @return list<string> codici dei ruoli, nell'ordine di gl_ruoli */
    public function codiciRuoli(): array
    {
        return $this->codiciRuoli ??= \Illuminate\Support\Facades\DB::table('gl_users_ruoli')
            ->join('gl_ruoli', 'gl_ruoli.codice', '=', 'gl_users_ruoli.ruolo_codice')
            ->where('gl_users_ruoli.user_id', $this->getKey())
            ->orderBy('gl_ruoli.ordine')
            ->pluck('gl_users_ruoli.ruolo_codice')
            ->all();
    }

    /** L'utente ha almeno uno dei ruoli. */
    public function haRuolo(string ...$codici): bool
    {
        return (bool) array_intersect($codici, $this->codiciRuoli());
    }

    /** «Segretario e Tesoriere»; «Presidente, Segretario e Tesoriere». */
    public function etichettaRuoli(): string
    {
        $label = Ruolo::etichette();
        $nomi = array_map(fn ($c) => $label[$c] ?? ucfirst($c), $this->codiciRuoli());

        return self::elenco($nomi);
    }

    /** Per la barra in alto: «Segretario · Tesoriere». */
    public function ruoliInBreve(): string
    {
        $label = Ruolo::etichette();

        return implode(' · ', array_map(fn ($c) => $label[$c] ?? ucfirst($c), $this->codiciRuoli()));
    }

    /** «a», «a e b», «a, b e c». @param list<string> $voci */
    public static function elenco(array $voci): string
    {
        $ultimo = array_pop($voci);

        return $voci ? implode(', ', $voci) . ' e ' . $ultimo : (string) $ultimo;
    }

    /**
     * Assegna i ruoli (sostituisce quelli che c'erano) e svuota le copie in
     * memoria. Le regole sulle combinazioni sono in App\Services\Utenti.
     *
     * @param list<string> $codici
     */
    public function assegnaRuoli(array $codici): void
    {
        $this->ruoli()->sync(array_values(array_unique($codici)));
        $this->codiciRuoli = null;
        $this->permessiSezioni = null;
    }

    /** Utenti con almeno uno dei ruoli: per le email «al ruolo», una riga per persona. */
    public function scopeConRuolo(\Illuminate\Database\Eloquent\Builder $query, string ...$codici): void
    {
        $query->whereExists(fn ($q) => $q->select(\Illuminate\Support\Facades\DB::raw(1))
            ->from('gl_users_ruoli')
            ->whereColumn('gl_users_ruoli.user_id', 'gl_users.id')
            ->whereIn('gl_users_ruoli.ruolo_codice', $codici));
    }

    /**
     * Mappa dei permessi: codice sezione => sola_lettura, unione dei ruoli.
     * Con più ruoli una sezione è in sola lettura solo se lo è per TUTTI i
     * ruoli che la vedono: la scrittura prevale (MIN di sola_lettura).
     * Una sola query per richiesta, poi memorizzata nell'istanza.
     *
     * @return array<string, bool>
     */
    public function permessiSezioni(): array
    {
        return $this->permessiSezioni ??= \Illuminate\Support\Facades\DB::table('gl_ruoli_sezioni')
            ->join('gl_sezioni', 'gl_sezioni.id', '=', 'gl_ruoli_sezioni.sezione_id')
            ->join('gl_users_ruoli', 'gl_users_ruoli.ruolo_codice', '=', 'gl_ruoli_sezioni.ruolo_codice')
            ->where('gl_users_ruoli.user_id', $this->getKey())
            ->groupBy('gl_sezioni.codice')
            ->selectRaw('gl_sezioni.codice, MIN(gl_ruoli_sezioni.sola_lettura) AS sola_lettura')
            ->pluck('sola_lettura', 'codice')
            ->map(fn ($soloLettura) => (bool) $soloLettura)
            ->all();
    }

    /**
     * Link «Scegli la password» (v0.40.0): email nello stile del sito,
     * registrata in gl_email_log, al posto della notifica di Laravel.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        \App\Support\InvioEmail::invia(
            new \App\Mail\LinkPassword($this->name, route('password.scegli', ['token' => $token, 'email' => $this->email])),
            $this->email, $this->name, 'link_password'
        );
    }

    /** I ruoli vedono la sezione (in qualunque modalità). */
    public function puoAccedere(string $sezione): bool
    {
        return array_key_exists($sezione, $this->permessiSezioni());
    }

    /** Almeno un ruolo vede la sezione e può modificarla. */
    public function puoModificare(string $sezione): bool
    {
        return $this->puoAccedere($sezione) && ! $this->permessiSezioni()[$sezione];
    }

    /** I ruoli vedono la sezione solo in lettura (es. Revisore su Prima Nota). */
    public function soloLettura(string $sezione): bool
    {
        return $this->puoAccedere($sezione) && $this->permessiSezioni()[$sezione];
    }
}
