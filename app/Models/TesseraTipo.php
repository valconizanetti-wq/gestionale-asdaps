<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Tipo di tessera dell'EPS (listino CSEN: Silver Base, Gold Base…), con il
 * prezzo per tessera proposto nei pacchetti (v0.37.0). Un tipo usato non si
 * elimina, si disattiva. Prezzo vuoto: tipo arrivato dal carico di un
 * elenco del CSEN, da prezzare prima di comprarne un pacchetto.
 */
#[ObservedBy([AuditObserver::class])]
class TesseraTipo extends Model
{
    protected $table = 'gl_tessere_tipi';

    protected $fillable = ['nome', 'prezzo', 'attivo', 'ordine'];

    protected function casts(): array
    {
        return ['prezzo' => 'decimal:2', 'attivo' => 'boolean'];
    }

    public function pacchetti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TesseraPacchetto::class, 'tipo_id');
    }

    public function tessere(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tesseramento::class, 'tipo_id');
    }

    public function scopeOrdinati($q)
    {
        return $q->orderBy('ordine')->orderBy('nome');
    }
}
