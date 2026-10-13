<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Quota associativa di un socio per un anno sociale, con la ricevuta
 * numerata per anno di pagamento (rinnovi-quota.md, v0.36.0). La logica
 * (situazione, registrazione, annullamento) sta in App\Services\RinnoviQuota.
 *
 * Un rinnovo annullato resta nel registro (numero della ricevuta compreso):
 * per lo stesso socio e anno può esserci una sola riga valida (scope valido).
 */
#[ObservedBy([AuditObserver::class])]
class Rinnovo extends Model
{
    public const METODI = [
        'contante' => 'Contanti',
        'bonifico' => 'Bonifico',
        'carta' => 'Carta o POS',
        'altro' => 'Altro',
    ];

    public const CATEGORIE = ['ordinario' => 'Ordinario', 'giovane' => 'Giovane'];

    protected $table = 'gl_rinnovi';

    protected $fillable = [
        'socio_id',
        'anno',
        'importo',
        'categoria',
        'data_pagamento',
        'metodo_pagamento',
        'versato_da_nome',
        'versato_da_cf',
        'assenso_minore_il',
        'assenso_minore_da',
        'numero_ricevuta',
        'anno_ricevuta',
        'progressivo',
        'pdf_path',
        'ricevuta_inviata_il',
        'ricevuta_stato',
        'annullata_il',
        'annullata_da',
        'annullamento_motivo',
        'note',
        'registrato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_pagamento' => 'date',
            'ricevuta_inviata_il' => 'datetime',
            'assenso_minore_il' => 'datetime',
            'annullata_il' => 'datetime',
            'importo' => 'decimal:2',
        ];
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id')->withTrashed();
    }

    public function registratoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'registrato_da');
    }

    public function annullataDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'annullata_da');
    }

    public function movimentoPrimaNota(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrimaNota::class, 'rinnovo_id');
    }

    public function scopeValido(Builder $q): Builder
    {
        return $q->whereNull('annullata_il');
    }

    public function isAnnullato(): bool
    {
        return $this->annullata_il !== null;
    }

    /**
     * Quota del vecchio gestionale caricata con l'importazione dei soci
     * (v0.40.0, analisi-importazione-soci.md §3.6, D4): niente ricevuta né
     * movimento, serve solo a dire che l'anno è pagato.
     */
    public function senzaRicevuta(): bool
    {
        return $this->numero_ricevuta === null;
    }

    /** «ricevuta n. 3/2027» oppure «quota 2027 del vecchio gestionale». */
    public function riferimento(): string
    {
        return $this->senzaRicevuta() ? "quota {$this->anno} del vecchio gestionale" : "ricevuta n. {$this->numero_ricevuta}";
    }

    public function metodoLabel(): string
    {
        return self::METODI[$this->metodo_pagamento] ?? (string) $this->metodo_pagamento;
    }

    /** Conto della Prima Nota: contanti in cassa, tutto il resto in banca. */
    public static function contoPer(string $metodo): string
    {
        return $metodo === 'contante' ? 'cassa' : 'banca';
    }
}
