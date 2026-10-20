<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Documento (PDF) di una seduta, di una consultazione telematica o di un
 * verbale: arriva con l'email e resta nel verbale. Non si fonde nel PDF del
 * verbale (difetto 14 del Dante): si scarica a parte.
 */
class OrganoDocumento extends Model
{
    protected $table = 'gl_organi_documenti';

    protected $fillable = ['allegabile_type', 'allegabile_id', 'path', 'nome', 'dimensione', 'ordine', 'caricato_da'];

    public function pesoTesto(): string
    {
        $b = (int) $this->dimensione;

        return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : max(1, (int) round($b / 1024)) . ' KB';
    }
}
