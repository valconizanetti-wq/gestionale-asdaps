<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VerbaleAllegato extends Model
{
    protected $table = 'gl_verbali_allegati';

    protected $fillable = [
        'verbale_id',
        'path',
        'nome_originale',
        'dimensione',
        'ordine',
        'caricato_da',
    ];

    public function verbale(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'verbale_id');
    }

    public function caricatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'caricato_da');
    }
}
