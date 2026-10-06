<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destinatario extends Model
{
    protected $table = 'gl_destinatari';

    protected $fillable = [
        'nome',
        'indirizzo',
        'cap',
        'citta',
        'provincia',
        'email',
        'note',
        'attivo',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }

    public function lettere(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Lettera::class, 'destinatario_id');
    }
}
