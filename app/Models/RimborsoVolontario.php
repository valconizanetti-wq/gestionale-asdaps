<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RimborsoVolontario extends Model
{
    protected $table = 'gl_rimborsi_volontari';

    protected $fillable = [
        'numero',
        'anno',
        'progressivo',
        'volontario_id',
        'tipo_rimborso',
        'evento_nome',
        'evento_data',
        'data_rimborso',
        'descrizione',
        'attivita_svolta',
        'categoria_id',
        'totale',
        'stato',
        'conto_pagamento',
        'note',
        'data_pagamento',
        'data_annullamento',
        'motivo_annullamento',
        'prima_nota_id',
        'rasd_invio_id',
        'allegato_path',
        'allegato_nome',
        'allegato_caricato_il',
        'allegato_caricato_da',
        'registrato_da',
        'creato_da',
    ];

    protected function casts(): array
    {
        return [
            'evento_data' => 'date',
            'data_rimborso' => 'date',
            'totale' => 'decimal:2',
            'data_pagamento' => 'date',
            'data_annullamento' => 'date',
            'allegato_caricato_il' => 'datetime',
        ];
    }

    public function volontario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Volontario::class, 'volontario_id');
    }

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNotaCategoria::class, 'categoria_id');
    }

    public function movimentoPrimaNota(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNota::class, 'prima_nota_id');
    }

    public function rasdInvio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RimborsoRasdInvio::class, 'rasd_invio_id');
    }

    public function registratoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'registrato_da');
    }

    public function voci(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RimborsoVolontarioVoce::class, 'rimborso_id');
    }
}
