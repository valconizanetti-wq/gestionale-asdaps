<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimaNotaCategoria extends Model
{
    protected $table = 'gl_prima_nota_categorie';

    protected $fillable = [
        'tipo',
        'nome',
        'codice',
        'codice_dm39',
        'flag_ente_pubblico',
        'flag_non_istituzionale',
        'esempio',
        'attiva',
        'ordine',
        'creata_da',
    ];

    protected function casts(): array
    {
        return [
            'flag_ente_pubblico' => 'boolean',
            'flag_non_istituzionale' => 'boolean',
            'attiva' => 'boolean',
        ];
    }

    public function movimenti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrimaNota::class, 'categoria_id');
    }

    public function vociBilancio(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BilancioVoce::class, 'categoria_id');
    }

    public function creataDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creata_da');
    }
}
