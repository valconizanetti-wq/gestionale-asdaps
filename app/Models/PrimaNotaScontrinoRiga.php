<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riga di uno scontrino (v0.39.0, analisi-scontrini.md §8): descrizione,
 * importo IVA compresa (negativo per uno sconto), categoria scelta da
 * Presidente o Tesoriere e movimento di Prima Nota in cui è finita.
 * Senza Audit Log proprio: le righe si riscrivono insieme a «Registra la
 * divisione», che ha la sua riga (SCONTRINO_DIVISO).
 */
class PrimaNotaScontrinoRiga extends Model
{
    protected $table = 'gl_prima_nota_scontrini_righe';

    protected $fillable = ['scontrino_id', 'ordine', 'descrizione', 'importo', 'categoria_id', 'prima_nota_id'];

    protected function casts(): array
    {
        return ['importo' => 'decimal:2'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(PrimaNotaCategoria::class, 'categoria_id');
    }

    public function movimento(): BelongsTo
    {
        return $this->belongsTo(PrimaNota::class, 'prima_nota_id');
    }
}
