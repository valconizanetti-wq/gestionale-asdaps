<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rinnovo extends Model
{
    protected $table = 'gl_rinnovi';

    protected $fillable = [
        'socio_id',
        'anno',
        'importo',
        'data_pagamento',
        'metodo_pagamento',
        'numero_ricevuta',
        'pdf_path',
        'ricevuta_inviata_il',
        'ricevuta_stato',
        'note',
        'registrato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_pagamento' => 'date',
            'ricevuta_inviata_il' => 'datetime',
            'importo' => 'decimal:2',
        ];
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function registratoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'registrato_da');
    }

    public function movimentoPrimaNota(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrimaNota::class, 'rinnovo_id');
    }
}
