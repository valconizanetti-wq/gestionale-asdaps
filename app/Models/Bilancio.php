<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bilancio extends Model
{
    protected $table = 'gl_bilancio';

    protected $fillable = [
        'anno',
        'tipo',
        'stato',
        'approvato_il',
        'data_assemblea',
        'verbale_approvazione_id',
        'numero_verbale',
        'note_integrative',
        'pdf_approvato_path',
        'codice_documento',
        'note',
        'creato_da',
    ];

    protected function casts(): array
    {
        return [
            'approvato_il' => 'date',
            'data_assemblea' => 'date',
        ];
    }

    public function voci(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BilancioVoce::class, 'bilancio_id');
    }

    public function verbaleApprovazione(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'verbale_approvazione_id');
    }

    public function creatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da');
    }
}
