<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RimborsoVolontarioVoce extends Model
{
    protected $table = 'gl_rimborsi_volontari_voci';

    protected $fillable = [
        'rimborso_id',
        'tipo',
        'descrizione',
        'tratta',
        'mezzo',
        'km',
        'tariffa_aci',
        'persone',
        'data_pasto',
        'note',
        'quantita',
        'importo_unitario',
        'importo',
        'data_voce',
    ];

    protected function casts(): array
    {
        return [
            'km' => 'decimal:2',
            'tariffa_aci' => 'decimal:4',
            'data_pasto' => 'date',
            'quantita' => 'decimal:2',
            'importo_unitario' => 'decimal:4',
            'importo' => 'decimal:2',
            'data_voce' => 'date',
        ];
    }

    public function rimborso(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RimborsoVolontario::class, 'rimborso_id');
    }
}
