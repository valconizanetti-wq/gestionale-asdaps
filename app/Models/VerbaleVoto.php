<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Voto di ciascun consigliere su una delibera (art. 16.6), in riunione o in consultazione telematica. */
class VerbaleVoto extends Model
{
    public const VOTI = [
        'favorevole' => 'Favorevole',
        'contrario' => 'Contrario',
        'astenuto' => 'Astenuto',
        'non_partecipa' => 'Non partecipa (conflitto di interessi)',
        'riunione' => 'Chiede la discussione in riunione',
    ];

    protected $table = 'gl_verbali_voti';

    protected $fillable = ['punto_id', 'socio_id', 'nome', 'carica', 'presidente', 'voto', 'nota', 'votato_il', 'collegamento'];

    protected function casts(): array
    {
        return ['presidente' => 'boolean', 'votato_il' => 'datetime'];
    }

    public function punto(): BelongsTo
    {
        return $this->belongsTo(VerbalePunto::class, 'punto_id');
    }

    public function votoTesto(): string
    {
        return self::VOTI[$this->voto] ?? 'non ha votato';
    }
}
