<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Progetto extends Model
{
    protected $table = 'gl_progetti';

    protected $fillable = [
        'nome',
        'anno',
        'descrizione',
        'stato',
        'relazione_attivita',
        'codice_documento',
    ];

    public function preventivo(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProgettoPreventivo::class, 'progetto_id');
    }

    public function movimentiPrimaNota(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrimaNota::class, 'progetto_id');
    }
}
