<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_backup_log';

    protected $fillable = [
        'tipo',
        'esito',
        'dettaglio',
        'file_path',
        'hash',
        'size_kb',
    ];

    protected function casts(): array
    {
        return [
            'size_kb' => 'decimal:1',
        ];
    }
}
