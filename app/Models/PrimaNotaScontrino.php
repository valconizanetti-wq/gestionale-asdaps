<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimaNotaScontrino extends Model
{
    protected $table = 'gl_prima_nota_scontrini';

    protected $fillable = [
        'data_scontrino',
        'importo_totale',
        'anno',
        'soggetto_id',
        'ragione_sociale_raw',
        'ocr_utilizzato',
        'ocr_testo_grezzo',
        'allegato_path',
        'allegato_originale',
        'quadrato',
        'note',
        'registrato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_scontrino' => 'date',
            'importo_totale' => 'decimal:2',
            'ocr_utilizzato' => 'boolean',
            'quadrato' => 'boolean',
        ];
    }

    public function soggetto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Soggetto::class, 'soggetto_id');
    }

    public function registratoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'registrato_da');
    }

    public function movimenti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrimaNota::class, 'scontrino_id');
    }

    /**
     * Ricalcola e salva il flag 'quadrato' confrontando la somma dei
     * movimenti collegati con l'importo totale dello scontrino. Da
     * chiamare ogni volta che un movimento collegato viene
     * aggiunto/modificato/rimosso.
     */
    public function ricalcolaQuadratura(): bool
    {
        $somma = $this->movimenti()->sum('importo');
        $quadrato = bccomp((string) $somma, (string) $this->importo_totale, 2) === 0;

        $this->update(['quadrato' => $quadrato]);

        return $quadrato;
    }
}
