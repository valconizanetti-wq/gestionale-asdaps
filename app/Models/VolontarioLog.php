<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolontarioLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_volontari_log';

    protected $fillable = [
        'volontario_id',
        'user_name',
        'evento',
        'dettaglio',
    ];

    public function volontario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Volontario::class, 'volontario_id');
    }
}
