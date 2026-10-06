<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprResponsabileDoc extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_gdpr_responsabili_docs';

    protected $fillable = [
        'responsabile_id',
        'nome',
        'descrizione',
        'path',
    ];

    public function responsabile(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(GdprResponsabile::class, 'responsabile_id');
    }
}
