<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto (o PDF) di uno scontrino, con il nome codificato
 * SC<anno>-<n.>.<est> e, per le aggiunte, SC<anno>-<n.>-<progr.>.<est>.
 */
class PrimaNotaScontrinoFile extends Model
{
    protected $table = 'gl_prima_nota_scontrini_file';

    protected $fillable = ['scontrino_id', 'path', 'nome', 'originale', 'caricato_da'];

    public function scontrino(): BelongsTo
    {
        return $this->belongsTo(PrimaNotaScontrino::class, 'scontrino_id');
    }

    public function immagine(): bool
    {
        return in_array(strtolower(pathinfo($this->path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true);
    }
}
