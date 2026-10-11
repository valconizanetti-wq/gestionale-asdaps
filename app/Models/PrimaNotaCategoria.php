<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Categoria di Prima Nota (prima-nota.md §2). Le 33 categorie iniziali
 * vengono da gestionale-acv (PrimaNotaCategoriaSeeder). Il tipo non cambia
 * dopo la creazione; una categoria usata non si elimina, si disattiva.
 */
#[ObservedBy([AuditObserver::class])]
class PrimaNotaCategoria extends Model
{
    /** Codici con un significato nel codice: non si cambiano. */
    public const QUOTE = 'QUOTE';
    public const DA_CLASSIFICARE = 'DA_CLASSIFICARE';
    /** Uscite delle scadenze dell'associazione (v0.38.0, scadenzario.md). */
    public const AFFILIAZIONE = 'AFFILIAZIONE_EPS';
    public const POLIZZE = 'POLIZZE';

    public const USI = [
        'movimenti' => 'Movimenti',
        'solo_bilancio' => 'Solo bilancio',
        'sistema' => 'Di sistema',
    ];

    protected $table = 'gl_prima_nota_categorie';

    protected $fillable = [
        'tipo',
        'nome',
        'codice',
        'codice_dm39',
        'uso',
        'flag_ente_pubblico',
        'flag_non_istituzionale',
        'natura_da_verificare',
        'esempio',
        'attiva',
        'ordine',
        'creata_da',
    ];

    protected function casts(): array
    {
        return [
            'flag_ente_pubblico' => 'boolean',
            'flag_non_istituzionale' => 'boolean',
            'natura_da_verificare' => 'boolean',
            'attiva' => 'boolean',
        ];
    }

    public function movimenti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrimaNota::class, 'categoria_id');
    }

    public function vociBilancio(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BilancioVoce::class, 'categoria_id');
    }

    public function creataDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creata_da');
    }

    public static function perCodice(string $codice): ?self
    {
        return static::where('codice', $codice)->first();
    }

    /** Categorie che si possono scegliere in un movimento del tipo dato. */
    public static function perMovimenti(string $tipo, ?int $anche = null)
    {
        return static::where('tipo', $tipo)
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('attiva', true)->where('uso', 'movimenti'))
                ->when($anche, fn ($w) => $w->orWhere('id', $anche)))
            ->orderBy('ordine')->orderBy('nome')->get();
    }

    public function isSistema(): bool
    {
        return $this->uso === 'sistema';
    }

    public function naturaLabel(): string
    {
        return match (true) {
            $this->flag_non_istituzionale => 'Commerciale',
            $this->natura_da_verificare => 'Da verificare (398)',
            default => 'Istituzionale',
        };
    }
}
