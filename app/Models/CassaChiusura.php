<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CassaChiusura extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_cassa_chiusure';

    protected $fillable = [
        'anno',
        'mese',
        'saldo_pn',
        'saldo_fisico',
        'scostamento',
        'note_scostamento',
        'chiuso_da',
        'chiuso_il',
    ];

    protected function casts(): array
    {
        return [
            'saldo_pn' => 'decimal:2',
            'saldo_fisico' => 'decimal:2',
            'scostamento' => 'decimal:2',
            'chiuso_il' => 'datetime',
        ];
    }

    public function chiusoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'chiuso_da');
    }
}
