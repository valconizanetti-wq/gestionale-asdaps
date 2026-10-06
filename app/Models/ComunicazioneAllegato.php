<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComunicazioneAllegato extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_comunicazioni_allegati';

    protected $fillable = [
        'comunicazione_id',
        'file_path',
        'file_originale',
    ];

    public function comunicazione(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Comunicazione::class, 'comunicazione_id');
    }
}
