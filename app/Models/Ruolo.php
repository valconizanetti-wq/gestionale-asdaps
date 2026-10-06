<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ruolo extends Model
{
    protected $table = 'gl_ruoli';

    public $timestamps = false;

    protected $fillable = [
        'codice',
        'label',
        'colore_avatar',
        'badge_class',
        'ordine',
        'attivo',
    ];

    protected function casts(): array
    {
        return [
            'attivo' => 'boolean',
        ];
    }

    public function sezioni(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Sezione::class,
            'gl_ruoli_sezioni',
            'ruolo_codice',
            'sezione_id',
            'codice',
            'id'
        )->withPivot('sola_lettura');
    }
}
