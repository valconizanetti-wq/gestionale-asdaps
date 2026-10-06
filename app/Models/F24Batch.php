<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class F24Batch extends Model
{
    protected $table = 'gl_f24_batch';

    protected $fillable = [
        'mese',
        'anno',
        'totale_lordi',
        'totale_ritenute',
        'codice_tributo',
        'stato',
        'scadenza',
        'data_versamento',
        'quietanza_path',
        'quietanza_nome_originale',
        'prima_nota_id',
        'versato_da',
        'versato_il',
    ];

    protected function casts(): array
    {
        return [
            'totale_lordi' => 'decimal:2',
            'totale_ritenute' => 'decimal:2',
            'scadenza' => 'date',
            'data_versamento' => 'date',
            'versato_il' => 'datetime',
        ];
    }

    public function primaNota(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNota::class, 'prima_nota_id');
    }

    public function versatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'versato_da');
    }
}
