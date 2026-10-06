<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgettoPreventivo extends Model
{
    protected $table = 'gl_progetti_preventivo';

    protected $fillable = [
        'progetto_id',
        'categoria_id',
        'tipo',
        'codice_dm39',
        'descrizione',
        'importo',
        'ordine',
    ];

    protected function casts(): array
    {
        return [
            'importo' => 'decimal:2',
        ];
    }

    public function progetto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Progetto::class, 'progetto_id');
    }

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNotaCategoria::class, 'categoria_id');
    }
}
