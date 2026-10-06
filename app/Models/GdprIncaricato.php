<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprIncaricato extends Model
{
    protected $table = 'gl_gdpr_incaricati';

    protected $fillable = [
        'user_id',
        'nome',
        'cognome',
        'ruolo',
        'email',
        'sesso',
        'testo_lettera',
        'trattamenti_autorizzati',
        'istruzioni',
        'data_nomina',
        'stato',
        'lettera_firmata_path',
        'revocato_il',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'data_nomina' => 'date',
            'revocato_il' => 'date',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
