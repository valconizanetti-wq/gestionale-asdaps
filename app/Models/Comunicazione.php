<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comunicazione extends Model
{
    protected $table = 'gl_comunicazioni';

    protected $fillable = [
        'tipo',
        'oggetto',
        'testo',
        'odg',
        'assemblea_id',
        'gruppo_destinatari',
        'soci_ids_custom',
        'data_invio',
        'inviata_da',
        'n_destinatari',
        'n_consegnate',
        'n_fallite',
        'n_cartaceo',
        'hash_registro',
        'hash_allegati',
        'canale_primario',
        'stato',
    ];

    protected function casts(): array
    {
        return [
            'data_invio' => 'datetime',
        ];
    }

    public function assemblea(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Assemblea::class, 'assemblea_id');
    }

    public function inviataDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'inviata_da');
    }

    public function allegati(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ComunicazioneAllegato::class, 'comunicazione_id');
    }

    public function destinatari(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ComunicazioneDestinatario::class, 'comunicazione_id');
    }
}
