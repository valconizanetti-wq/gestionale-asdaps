<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RimborsoRasdInvio extends Model
{
    protected $table = 'gl_rimborsi_rasd_invii';

    protected $fillable = [
        'anno',
        'trimestre',
        'data_invio',
        'stato',
        'ricevuta_path',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'data_invio' => 'date',
        ];
    }

    public function rimborsi(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RimborsoVolontario::class, 'rasd_invio_id');
    }
}
