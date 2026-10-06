<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ErogazioneLiberale extends Model
{
    protected $table = 'gl_erogazioni_liberali';

    protected $fillable = [
        'numero',
        'prima_nota_id',
        'soggetto_id',
        'data_erogazione',
        'importo',
        'conto',
        'causale',
        'note',
        'stato',
        'motivo_annullamento',
        'annullata_il',
        'storno_prima_nota_id',
        'inviata_email_il',
        'registrato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_erogazione' => 'date',
            'importo' => 'decimal:2',
            'annullata_il' => 'date',
            'inviata_email_il' => 'datetime',
        ];
    }

    public function primaNota(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNota::class, 'prima_nota_id');
    }

    public function soggetto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Soggetto::class, 'soggetto_id');
    }

    public function stornoPrimaNota(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNota::class, 'storno_prima_nota_id');
    }

    public function registratoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'registrato_da');
    }
}
