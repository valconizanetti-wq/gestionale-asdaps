<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GdprResponsabile extends Model
{
    protected $table = 'gl_gdpr_responsabili';

    protected $fillable = [
        'nome',
        'servizio',
        'dati_trattati',
        'paese',
        'dpa_firmato',
        'dpa_data',
        'dpa_scadenza',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'dpa_firmato' => 'boolean',
            'dpa_data' => 'date',
            'dpa_scadenza' => 'date',
        ];
    }

    public function documenti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GdprResponsabileDoc::class, 'responsabile_id');
    }
}
