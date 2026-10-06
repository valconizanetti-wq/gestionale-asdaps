<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/** Giustificativo allegato a un movimento (disco 'local', prima-nota/<anno>/). */
#[ObservedBy([AuditObserver::class])]
class PrimaNotaDocumento extends Model
{
    protected $table = 'gl_prima_nota_documenti';

    protected $fillable = [
        'prima_nota_id',
        'path',
        'nome',
        'descrizione',
        'caricato_da',
    ];

    public function primaNota(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNota::class, 'prima_nota_id');
    }

    public function caricatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'caricato_da');
    }
}
