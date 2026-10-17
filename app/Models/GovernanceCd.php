<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GovernanceCd extends Model
{
    protected $table = 'gl_governance_cd';

    protected $fillable = [
        'socio_id',
        'carica',
        'carica_codice',
        'data_inizio_mandato',
        'data_fine_mandato',
        'motivo_fine',
        'in_carica',
        'delibera_assemblea',
        'delibera_data',
        'verbale_nomina_id',
        'dichiarazione_incompatibilita',
        'dichiarazione_incompatibilita_data',
        'curriculum',
        'note',
        'inserito_da',
    ];

    protected function casts(): array
    {
        return [
            'data_inizio_mandato' => 'date',
            'data_fine_mandato' => 'date',
            'delibera_data' => 'date',
            'dichiarazione_incompatibilita_data' => 'date',
            'in_carica' => 'boolean',
        ];
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        // Anche con la scheda eliminata (soft delete): il CD e lo storico la
        // citano (collaudo OB4, 17/10/2026: errore 500 sul nome mancante)
        return $this->belongsTo(Socio::class, 'socio_id')->withTrashed();
    }

    /** «Cognome Nome» del socio, anche con la scheda eliminata. */
    public function nomeSocio(): string
    {
        return $this->socio ? trim("{$this->socio->cognome} {$this->socio->nome}") : "socio n. {$this->socio_id}";
    }

    public function verbaleNomina(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'verbale_nomina_id');
    }

    public function inseritoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'inserito_da');
    }
}
