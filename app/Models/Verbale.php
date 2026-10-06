<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Verbale extends Model
{
    protected $table = 'gl_verbali';

    protected $fillable = [
        'tipo',
        'assemblea_id',
        'domanda_id',
        'periodo_da',
        'periodo_a',
        'titolo',
        'presidente_assemblea',
        'segretario_verbalizzante',
        'soci_presenti',
        'soci_deleghe',
        'presenze_cd',
        'data_seduta',
        'ora_inizio',
        'ora_chiusura',
        'corpo_html',
        'stato',
        'approvato_il',
        'archiviato_il',
        'hash_contabilita',
        'hash_file_verbale',
        'verbale_firmato_path',
        'anno_locked',
        'chiuso_il',
        'chiuso_da',
        'pdf_path',
        'redatto_da',
    ];

    protected function casts(): array
    {
        return [
            'periodo_da' => 'date',
            'periodo_a' => 'date',
            'presenze_cd' => 'array',
            'data_seduta' => 'date',
            'approvato_il' => 'datetime',
            'archiviato_il' => 'datetime',
            'chiuso_il' => 'datetime',
        ];
    }

    public function assemblea(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Assemblea::class, 'assemblea_id');
    }

    public function domanda(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Domanda::class, 'domanda_id');
    }

    public function domandeChiuse(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Domanda::class, 'verbale_cd_id');
    }

    public function chiusoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'chiuso_da');
    }

    public function redattoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'redatto_da');
    }

    public function allegati(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VerbaleAllegato::class, 'verbale_id');
    }

    public function movimentiPrimaNotaChiusi(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrimaNota::class, 'chiuso_da_verb_id');
    }
}
