<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttestazioneTecnica extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_attestazioni_tecniche';

    protected $fillable = [
        'oggetto',
        'data_firma',
        'note',
        'eccezioni_ids',
        'eccezioni_count',
        'anomalie_count',
        'records_totali',
        'record_pre_catena',
        'corretti',
        'gap_count',
        'pdf_path',
        'pdf_filename',
        'relazione_path',
        'relazione_filename',
        'caricato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_firma' => 'date',
        ];
    }
}
