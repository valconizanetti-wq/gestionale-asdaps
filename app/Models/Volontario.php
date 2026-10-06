<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Volontario extends Model
{
    use SoftDeletes;

    protected $table = 'gl_volontari';

    protected $fillable = [
        'socio_id',
        'mansione',
        'mansioni_extra',
        'data_inizio_attivita',
        'data_fine_attivita',
        'cessazione_vidimata',
        'vidimato_pec_il',
        'assicurazione_compagnia',
        'assicurazione_numero',
        'assicurazione_scadenza',
        'path_polizza',
        'path_documento_identita',
        'scadenza_documento_identita',
        'consenso_trattamento',
        'consenso_comunicazioni',
        'note',
        'status',
        'sessione_id',
        'sessione_cessazione_id',
        'verbale_autorizzazione_id',
        'numero_registro',
        'pagina_registro',
        'pagina_cessazione',
        'hash_record',
    ];

    protected function casts(): array
    {
        return [
            'mansioni_extra' => 'array',
            'data_inizio_attivita' => 'date',
            'data_fine_attivita' => 'date',
            'cessazione_vidimata' => 'boolean',
            'vidimato_pec_il' => 'date',
            'assicurazione_scadenza' => 'date',
            'scadenza_documento_identita' => 'date',
            'consenso_trattamento' => 'boolean',
            'consenso_comunicazioni' => 'boolean',
        ];
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function sessione(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VolontarioSessione::class, 'sessione_id');
    }

    public function sessioneCessazione(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VolontarioSessione::class, 'sessione_cessazione_id');
    }

    public function verbaleAutorizzazione(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'verbale_autorizzazione_id');
    }

    public function log(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VolontarioLog::class, 'volontario_id');
    }

    public function rimborsi(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RimborsoVolontario::class, 'volontario_id');
    }

    public function autodichiarazioni(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VolontarioAutodichiarazione::class, 'volontario_id');
    }
}
