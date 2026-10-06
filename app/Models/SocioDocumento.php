<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([AuditObserver::class])]
class SocioDocumento extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_soci_documenti';

    protected $fillable = [
        'socio_id',
        'categoria',
        'descrizione',
        'file_path',
        'file_originale',
        'caricato_da',
    ];

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function caricatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'caricato_da');
    }
}
