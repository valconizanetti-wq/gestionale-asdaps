<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RasdVerifica extends Model
{
    protected $table = 'gl_rasd_verifiche';

    protected $fillable = [
        'data_verifica',
        'anomalie_riscontrate',
        'scadenza_regolarizzazione',
        'stato',
        'regolarizzata_il',
        'azioni_correttive',
        'note',
        'gestita_da',
    ];

    protected function casts(): array
    {
        return [
            'data_verifica' => 'date',
            'scadenza_regolarizzazione' => 'date',
            'regolarizzata_il' => 'date',
        ];
    }

    public function gestitaDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'gestita_da');
    }
}
