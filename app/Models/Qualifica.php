<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Qualifica extends Model
{
    protected $table = 'gl_qualifiche';

    protected $fillable = [
        'codice',
        'label',
    ];

    public function tesseramenti(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Tesseramento::class, 'gl_tesseramenti_qualifiche', 'qualifica_id', 'tesseramento_id');
    }
}
