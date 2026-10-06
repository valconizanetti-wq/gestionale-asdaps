<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprTrattamento extends Model
{
    protected $table = 'gl_gdpr_trattamenti';

    protected $fillable = [
        'nome',
        'finalita',
        'base_giuridica',
        'categorie_speciali',
        'categorie_interessati',
        'categorie_dati',
        'responsabili_esterni',
        'trasferimenti_esteri',
        'conservazione',
        'misure_sicurezza',
        'dpia_note',
        'responsabile_interno',
        'ultima_revisione',
        'note_modifica',
        'cessato_il',
        'creato_da',
    ];

    protected function casts(): array
    {
        return [
            'ultima_revisione' => 'date',
            'cessato_il' => 'date',
        ];
    }

    public function creatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da');
    }
}
