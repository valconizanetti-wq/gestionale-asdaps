<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_audit_log';

    protected $fillable = [
        'evento',
        'operazione',
        'anno',
        'entita_tipo',
        'modello',
        'entita_id',
        'utente_nome',
        'ip_address',
        'dettaglio',
        'old_values',
        'new_values',
        'livello_rischio',
        'campi_modificati',
        'hash',
        'previous_hash',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'campi_modificati' => 'array',
        ];
    }
}
