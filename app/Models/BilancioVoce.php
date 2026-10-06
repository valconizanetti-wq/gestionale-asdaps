<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BilancioVoce extends Model
{
    protected $table = 'gl_bilancio_voci';

    protected $fillable = [
        'bilancio_id',
        'tipo',
        'codice',
        'descrizione',
        'importo_preventivo',
        'ordine',
        'categoria_id',
    ];

    protected function casts(): array
    {
        return [
            'importo_preventivo' => 'decimal:2',
        ];
    }

    public function bilancio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Bilancio::class, 'bilancio_id');
    }

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNotaCategoria::class, 'categoria_id');
    }
}
