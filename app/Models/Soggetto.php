<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Soggetto extends Model
{
    protected $table = 'gl_soggetti';

    protected $fillable = [
        'nome',
        'email',
        'categoria_id',
    ];

    public function erogazioni(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ErogazioneLiberale::class, 'soggetto_id');
    }
}
