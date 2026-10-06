<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimaNotaDocumento extends Model
{
    protected $table = 'gl_prima_nota_documenti';

    protected $fillable = [
        'prima_nota_id',
        'path',
        'nome',
        'descrizione',
        'caricato_da',
    ];

    public function primaNota(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNota::class, 'prima_nota_id');
    }

    public function caricatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'caricato_da');
    }
}
