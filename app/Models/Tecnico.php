<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tecnico extends Model
{
    protected $table = 'gl_tecnici';

    protected $fillable = [
        'socio_id',
        'nome_esterno',
        'note',
    ];

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function tesseramentiSeguiti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tesseramento::class, 'tecnico_riferimento_id');
    }

    // Nome da mostrare in UI: quello del socio collegato, se presente,
    // altrimenti il nominativo esterno inserito a mano.
    public function getNomeVisualizzatoAttribute(): ?string
    {
        return $this->socio
            ? trim($this->socio->cognome.' '.$this->socio->nome)
            : $this->nome_esterno;
    }
}
