<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialitaGara extends Model
{
    protected $table = 'gl_specialita_gara';

    protected $fillable = [
        'codice',
        'nome',
    ];

    public function tesseramenti(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tesseramento::class, 'gl_tesseramenti_specialita', 'specialita_id', 'tesseramento_id');
    }
}
