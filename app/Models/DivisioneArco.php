<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DivisioneArco extends Model
{
    protected $table = 'gl_divisioni_arco';

    protected $fillable = [
        'codice',
        'nome',
    ];

    public function tesseramenti(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tesseramento::class, 'gl_tesseramenti_divisioni', 'divisione_id', 'tesseramento_id');
    }
}
