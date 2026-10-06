<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprRichiesta extends Model
{
    protected $table = 'gl_gdpr_richieste';

    protected $fillable = [
        'tipo',
        'nominativo',
        'contatto',
        'data_ricezione',
        'scadenza',
        'identita_verificata',
        'descrizione',
        'stato',
        'risposta',
        'data_risposta',
    ];

    protected function casts(): array
    {
        return [
            'data_ricezione' => 'date',
            'scadenza' => 'date',
            'identita_verificata' => 'boolean',
            'data_risposta' => 'date',
        ];
    }
}
