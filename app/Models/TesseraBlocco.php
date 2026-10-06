<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TesseraBlocco extends Model
{
    protected $table = 'gl_tessere_blocchi';

    protected $fillable = [
        'anno',
        'numero_da',
        'numero_a',
        'note',
        'inserito_da',
    ];

    public function soci(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Socio::class, 'blocco_tessera_id');
    }

    public function inseritoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'inserito_da');
    }
}
