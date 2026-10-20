<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rettifica di un verbale definitivo (tavola VB-3): una nota datata e
 * motivata in fondo al verbale, che non tocca il testo originale (art. 23.2).
 * Ha il suo PDF, da firmare digitalmente come il verbale.
 */
class VerbaleRettifica extends Model
{
    protected $table = 'gl_verbali_rettifiche';

    protected $fillable = ['verbale_id', 'numero', 'testo_html', 'motivo'];

    protected function casts(): array
    {
        return ['firmato_il' => 'datetime'];
    }

    public function verbale(): BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'verbale_id');
    }

    public function creataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creata_da');
    }
}
