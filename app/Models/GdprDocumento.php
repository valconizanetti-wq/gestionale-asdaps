<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprDocumento extends Model
{
    protected $table = 'gl_gdpr_documenti';

    protected $fillable = [
        'categoria',
        'nome',
        'versione',
        'path',
        'note',
        'caricato_da',
    ];

    public function caricatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'caricato_da');
    }
}
