<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sezione extends Model
{
    protected $table = 'gl_sezioni';

    protected $fillable = [
        'codice',
        'label',
        'ordine',
    ];

    public function ruoli(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Ruolo::class,
            'gl_ruoli_sezioni',
            'sezione_id',
            'ruolo_codice',
            'id',
            'codice'
        )->withPivot('sola_lettura');
    }

    /**
     * Vero se il ruolo indicato (codice) ha accesso a questa sezione,
     * indipendentemente dalla modalità (lettura/scrittura).
     */
    public function accessibileDaRuolo(string $ruoloCodice): bool
    {
        return $this->ruoli()->where('gl_ruoli.codice', $ruoloCodice)->exists();
    }
}
