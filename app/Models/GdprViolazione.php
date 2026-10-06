<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprViolazione extends Model
{
    protected $table = 'gl_gdpr_violazioni';

    protected $fillable = [
        'data_scoperta',
        'tipologia',
        'descrizione',
        'categorie_dati',
        'num_interessati',
        'livello_rischio',
        'notificato_garante',
        'protocollo_garante',
        'data_notifica_garante',
        'comunicato_interessati',
        'misure_adottate',
        'stato',
    ];

    protected function casts(): array
    {
        return [
            'data_scoperta' => 'datetime',
            'notificato_garante' => 'boolean',
            'data_notifica_garante' => 'datetime',
            'comunicato_interessati' => 'boolean',
        ];
    }
}
