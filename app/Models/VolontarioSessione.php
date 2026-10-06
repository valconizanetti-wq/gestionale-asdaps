<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VolontarioSessione extends Model
{
    protected $table = 'gl_volontari_sessioni';

    protected $fillable = [
        'numero',
        'anno',
        'data_generazione',
        'periodo_dal',
        'periodo_al',
        'pagina_inizio',
        'pagina_fine',
        'file_pdf_path',
        'file_firmato_path',
        'file_pec_ricevuta_path',
        'timestamp_consegna',
        'hash_controllo',
        'protocollo_pec',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data_generazione' => 'datetime',
            'periodo_dal' => 'date',
            'periodo_al' => 'date',
            'timestamp_consegna' => 'datetime',
        ];
    }

    public function volontari(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Volontario::class, 'sessione_id');
    }

    public function cessazioni(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Volontario::class, 'sessione_cessazione_id');
    }
}
