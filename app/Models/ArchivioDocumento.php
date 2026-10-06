<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivioDocumento extends Model
{
    protected $table = 'gl_archivio_documenti';

    protected $fillable = [
        'fonte_tipo',
        'fonte_modello',
        'fonte_id',
        'tipo_documento',
        'ambito',
        'titolo',
        'descrizione',
        'note_interne',
        'tag',
        'anno',
        'data_documento',
        'file_path',
        'file_nome',
        'hash_file',
        'caricato_da',
    ];

    protected function casts(): array
    {
        return [
            'tag' => 'array',
            'data_documento' => 'date',
        ];
    }

    public function caricatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'caricato_da');
    }
}
