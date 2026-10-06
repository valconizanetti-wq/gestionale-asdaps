<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificatoMedico extends Model
{
    protected $table = 'gl_certificati_medici';

    protected $fillable = [
        'socio_id',
        'tesseramento_id',
        'tipo',
        'data_visita',
        'data_scadenza',
        'medico_nome',
        'struttura',
        'stato',
        'file_path',
        'file_originale',
        'note',
        'caricato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_visita' => 'date',
            'data_scadenza' => 'date',
        ];
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function tesseramento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tesseramento::class, 'tesseramento_id');
    }

    public function caricatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'caricato_da');
    }
}
