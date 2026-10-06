<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditArchivio extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_audit_archivi';

    protected $fillable = [
        'data_archiviazione',
        'file_archivio',
        'periodo_label',
        'soglia_data',
        'record_eliminati',
        'hash_righe_eliminate',
    ];

    protected function casts(): array
    {
        return [
            'data_archiviazione' => 'datetime',
            'soglia_data' => 'datetime',
        ];
    }
}
