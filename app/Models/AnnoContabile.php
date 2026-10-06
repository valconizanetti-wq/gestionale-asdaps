<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Anno contabile della Prima Nota (prima-nota.md §2). La logica (saldi,
 * chiusura, riapertura) sta nel servizio App\Services\AnniContabili.
 */
#[ObservedBy([AuditObserver::class])]
class AnnoContabile extends Model
{
    protected $table = 'gl_anni_contabili';

    protected $fillable = [
        'anno', 'stato',
        'saldo_apertura_cassa', 'saldo_apertura_banca', 'saldi_fonte',
        'saldi_documento_path', 'saldi_documento_nome',
        'saldo_chiusura_cassa', 'saldo_chiusura_banca',
        'approvazione_data', 'approvazione_rif', 'approvazione_verbale_id', 'chiuso_il', 'chiuso_da',
    ];

    protected function casts(): array
    {
        return [
            'saldo_apertura_cassa' => 'decimal:2',
            'saldo_apertura_banca' => 'decimal:2',
            'saldo_chiusura_cassa' => 'decimal:2',
            'saldo_chiusura_banca' => 'decimal:2',
            'approvazione_data' => 'date',
            'chiuso_il' => 'datetime',
        ];
    }

    public function chiusoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'chiuso_da');
    }

    public function verbaleApprovazione(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'approvazione_verbale_id');
    }

    public function isChiuso(): bool
    {
        return $this->stato === 'chiuso';
    }
}
