<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunicazioneDestinatario extends Model
{
    protected $table = 'gl_comunicazioni_destinatari';

    protected $fillable = [
        'comunicazione_id',
        'socio_id',
        'nome',
        'email',
        'esito',
        'inviata_il',
        'errore',
        'tentativi',
        'data_notifica_manuale',
        'note_consegna',
    ];

    protected function casts(): array
    {
        return [
            'inviata_il' => 'datetime',
            'data_notifica_manuale' => 'date',
        ];
    }

    public function comunicazione(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Comunicazione::class, 'comunicazione_id');
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }
}
