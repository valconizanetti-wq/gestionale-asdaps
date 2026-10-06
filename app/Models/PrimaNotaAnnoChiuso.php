<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimaNotaAnnoChiuso extends Model
{
    protected $table = 'gl_prima_nota_anni_chiusi';

    protected $fillable = [
        'anno',
        'chiuso_il',
        'chiuso_da',
    ];

    protected function casts(): array
    {
        return [
            'chiuso_il' => 'datetime',
        ];
    }

    public function chiusoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'chiuso_da');
    }
}
