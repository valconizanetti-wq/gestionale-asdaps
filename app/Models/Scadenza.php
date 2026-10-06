<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Scadenza extends Model
{
    protected $table = 'gl_scadenzario';

    protected $fillable = [
        'titolo',
        'descrizione',
        'tipo',
        'categoria',
        'data_scadenza',
        'giorno_ricorrenza',
        'mese_ricorrenza',
        'giorni_preavviso',
        'completato',
        'completato_il',
        'note_completamento',
        'assegnato_a',
        'creato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_scadenza' => 'date',
            'completato' => 'boolean',
            'completato_il' => 'date',
        ];
    }

    public function assegnatoA(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assegnato_a');
    }

    public function creatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da');
    }
}
