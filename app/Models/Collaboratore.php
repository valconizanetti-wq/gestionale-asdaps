<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Collaboratore extends Model
{
    protected $table = 'gl_collaboratori';

    protected $fillable = [
        'cognome',
        'nome',
        'codice_fiscale',
        'email',
        'indirizzo',
        'cap',
        'comune',
        'provincia',
        'tipo',
        'specializzazione',
        'attivo',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }
}
