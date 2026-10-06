<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLogIntegrity extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_audit_log_integrity';

    protected $fillable = [
        'anno',
        'computed_at',
        'hash_chain',
        'records_count',
        'esito',
        'generato_da',
        'uuid_documento',
    ];

    protected function casts(): array
    {
        return [
            'computed_at' => 'datetime',
        ];
    }
}
