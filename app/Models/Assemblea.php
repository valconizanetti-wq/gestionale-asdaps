<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assemblea extends Model
{
    protected $table = 'gl_assemblee';

    protected $fillable = [
        'tipo',
        'data',
        'ora_inizio',
        'luogo',
        'ordine_del_giorno',
        'stato',
        'note',
        'creata_da',
        'convocazione_inviata_il',
        'svolta_il',
        'svolta_da',
        'rinviata_il',
        'rinvio_note',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'ordine_del_giorno' => 'array',
            'convocazione_inviata_il' => 'datetime',
            'svolta_il' => 'datetime',
            'rinviata_il' => 'datetime',
        ];
    }

    public function creataDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creata_da');
    }

    public function svoltaDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'svolta_da');
    }

    public function rsvp(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AssembleaRsvp::class, 'assemblea_id');
    }

    public function verbali(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Verbale::class, 'assemblea_id');
    }

    public function comunicazioni(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comunicazione::class, 'assemblea_id');
    }
}
