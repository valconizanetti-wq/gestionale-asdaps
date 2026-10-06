<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetteraInvio extends Model
{
    protected $table = 'gl_lettere_invii';

    protected $fillable = [
        'lettera_id',
        'destinatario_email',
        'stato',
        'errore_messaggio',
        'inviata_da',
        'inviata_il',
    ];

    protected function casts(): array
    {
        return [
            'inviata_il' => 'datetime',
        ];
    }

    public function lettera(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Lettera::class, 'lettera_id');
    }

    public function inviataDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'inviata_da');
    }
}
