<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolontarioAutodichiarazione extends Model
{
    protected $table = 'gl_volontari_autodichiarazioni';

    protected $fillable = [
        'volontario_id',
        'anno',
        'importo_dichiarato',
        'data_dichiarazione',
        'sopra_soglia',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'importo_dichiarato' => 'decimal:2',
            'data_dichiarazione' => 'date',
            'sopra_soglia' => 'boolean',
        ];
    }

    public function volontario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Volontario::class, 'volontario_id');
    }
}
