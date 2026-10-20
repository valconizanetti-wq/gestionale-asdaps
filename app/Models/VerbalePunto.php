<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Punto dell'ordine del giorno nel verbale (tavola VB-2): la discussione e,
 * se c'è, la delibera con il tipo, il testo, i voti e l'esito calcolato
 * (App\Services\Votazioni).
 */
class VerbalePunto extends Model
{
    /** Tipi di delibera del CD (regola delle votazioni, analisi §6). */
    public const TIPI_CD = [
        'ordinaria' => 'Delibera',
        'propone_rendiconto' => 'Propone il rendiconto all\'Assemblea',
        'decadenze' => 'Prende atto delle decadenze',
        'convoca_assemblea' => 'Convoca l\'Assemblea',
        'esclusione' => 'Esclusione di un socio',
    ];

    /** Tipi che non si deliberano in consultazione telematica (art. 16.5). */
    public const SOLO_RIUNIONE = ['propone_rendiconto', 'esclusione'];

    protected $table = 'gl_verbali_punti';

    protected $fillable = [
        'verbale_id', 'ordine', 'titolo', 'discussione_html', 'ha_delibera', 'tipo_delibera', 'delibera_html',
        'esito', 'esito_testo', 'favorevoli', 'contrari', 'astenuti', 'non_partecipano',
    ];

    protected function casts(): array
    {
        return ['ha_delibera' => 'boolean'];
    }

    public function verbale(): BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'verbale_id');
    }

    public function voti(): HasMany
    {
        return $this->hasMany(VerbaleVoto::class, 'punto_id')->orderBy('id');
    }

    public function tipoTesto(): string
    {
        return self::TIPI_CD[$this->tipo_delibera] ?? 'Delibera';
    }

    public function esitoVariante(): string
    {
        return $this->esito === 'approvata' ? 'ok' : ($this->esito === 'respinta' ? 'ko' : 'neutral');
    }
}
