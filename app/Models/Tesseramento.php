<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tesseramento extends Model
{
    protected $table = 'gl_tesseramenti';

    protected $fillable = [
        'socio_id',
        'stagione_sportiva',
        'ente_affiliante',
        'numero_tessera_csen',
        'data_inserimento',
        'data_rilascio',
        'pacchetto_tesseramento',
        'tecnico_riferimento_id',
        'stato',
        'note',
        'inserito_da',
    ];

    protected function casts(): array
    {
        return [
            'data_inserimento' => 'date',
            'data_rilascio' => 'date',
        ];
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function tecnicoRiferimento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tecnico::class, 'tecnico_riferimento_id');
    }

    public function inseritoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'inserito_da');
    }

    public function qualifiche(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Qualifica::class, 'gl_tesseramenti_qualifiche', 'tesseramento_id', 'qualifica_id');
    }

    public function divisioni(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DivisioneArco::class, 'gl_tesseramenti_divisioni', 'tesseramento_id', 'divisione_id');
    }

    public function specialita(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SpecialitaGara::class, 'gl_tesseramenti_specialita', 'tesseramento_id', 'specialita_id');
    }

    public function certificatiMedici(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CertificatoMedico::class, 'tesseramento_id');
    }
}
