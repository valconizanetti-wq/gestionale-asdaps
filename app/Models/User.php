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
        'email',
        'password',
        'ruolo',
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

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'attivo' => 'boolean',
            'ultimo_accesso' => 'datetime',
        ];
    }

    /**
     * Attenzione: la colonna e questa relazione hanno lo stesso nome.
     * Eloquent dà precedenza alla colonna, quindi $user->ruolo restituisce
     * SEMPRE il codice (stringa), anche dopo load('ruolo'). Per il model
     * Ruolo (es. l'etichetta) usare $user->ruolo()->first().
     */
    public function ruolo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Ruolo::class, 'ruolo', 'codice');
    }

    /**
     * Mappa dei permessi del ruolo corrente: codice sezione => sola_lettura.
     * Una sola query per richiesta, poi memorizzata nell'istanza.
     *
     * @return array<string, bool>
     */
    public function permessiSezioni(): array
    {
        return $this->permessiSezioni ??= \Illuminate\Support\Facades\DB::table('gl_ruoli_sezioni')
            ->join('gl_sezioni', 'gl_sezioni.id', '=', 'gl_ruoli_sezioni.sezione_id')
            ->where('gl_ruoli_sezioni.ruolo_codice', $this->ruolo)
            ->pluck('gl_ruoli_sezioni.sola_lettura', 'gl_sezioni.codice')
            ->map(fn ($soloLettura) => (bool) $soloLettura)
            ->all();
    }

    /** Il ruolo vede la sezione (in qualunque modalità). */
    public function puoAccedere(string $sezione): bool
    {
        return array_key_exists($sezione, $this->permessiSezioni());
    }

    /** Il ruolo vede la sezione e può modificarla. */
    public function puoModificare(string $sezione): bool
    {
        return $this->puoAccedere($sezione) && ! $this->permessiSezioni()[$sezione];
    }

    /** Il ruolo vede la sezione solo in lettura (es. Revisore su Prima Nota). */
    public function soloLettura(string $sezione): bool
    {
        return $this->puoAccedere($sezione) && $this->permessiSezioni()[$sezione];
    }
}
