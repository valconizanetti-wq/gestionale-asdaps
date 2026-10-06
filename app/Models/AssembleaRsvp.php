<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssembleaRsvp extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_assemblee_rsvp';

    protected $fillable = [
        'assemblea_id',
        'destinatario_email',
        'destinatario_nome',
        'token',
        'is_test',
        'risposta',
        'risposta_il',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'is_test' => 'boolean',
            'risposta_il' => 'datetime',
        ];
    }

    public function assemblea(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Assemblea::class, 'assemblea_id');
    }
}
