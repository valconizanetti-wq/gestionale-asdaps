<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprRegistroFirmato extends Model
{
    const UPDATED_AT = null;

    protected $table = 'gl_gdpr_registro_firmato';

    protected $fillable = [
        'data_firma',
        'note_modifiche',
        'pdf_path',
        'pdf_filename',
        'caricato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_firma' => 'date',
        ];
    }

    public function caricatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'caricato_da');
    }
}
