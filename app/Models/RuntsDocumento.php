<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuntsDocumento extends Model
{
    protected $table = 'gl_runts_documenti';

    protected $fillable = [
        'nome_file',
        'nome_originale',
        'tipo_documento',
        'data_documento',
        'note',
        'uploaded_da',
    ];

    protected function casts(): array
    {
        return [
            'data_documento' => 'date',
        ];
    }

    public function uploadedDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_da');
    }
}
