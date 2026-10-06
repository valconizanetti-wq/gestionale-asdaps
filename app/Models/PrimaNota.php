<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Movimento di Prima Nota (prima-nota.md). Conti: 'cassa' e 'banca'
 * (stessi valori di gestionale-acv; il Gestionale APS usa 'cc').
 * Un giroconto è una coppia di righe (uscita dal conto di partenza,
 * entrata in quello di arrivo) collegate da giroconto_id, senza categoria.
 */
#[ObservedBy([AuditObserver::class])]
class PrimaNota extends Model
{
    public const CONTI = ['cassa' => 'Cassa', 'banca' => 'Banca'];

    public const TIPI_DOCUMENTO = [
        'fattura' => 'Fattura', 'scontrino' => 'Scontrino', 'ricevuta' => 'Ricevuta',
        'bonifico' => 'Bonifico', 'f24' => 'F24', 'altro' => 'Altro',
    ];

    protected $table = 'gl_prima_nota';

    protected $fillable = [
        'data',
        'tipo',
        'conto',
        'importo',
        'descrizione',
        'categoria_id',
        'socio_id',
        'rinnovo_id',
        'scontrino_id',
        'giustificativo_path',
        'giustificativo_nome',
        'note',
        'soggetto',
        'tipo_documento',
        'numero_documento',
        'data_documento',
        'anticipato_da',
        'sospeso',
        'tipo_anticipante',
        'rimborsato_il',
        'rimborso_nota_numero',
        'data_nota',
        'iban_rimborso',
        'giroconto_id',
        'progetto_id',
        'tag_id',
        'chiuso_da_verb_id',
        'storno_di_id',
        'banca_import_id',
        'is_locked',
        'riconciliato_banca',
        'descrizione_progetto',
        'registrato_da',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'data_documento' => 'date',
            'rimborsato_il' => 'date',
            'data_nota' => 'date',
            'importo' => 'decimal:2',
            'sospeso' => 'boolean',
            'is_locked' => 'boolean',
            'riconciliato_banca' => 'boolean',
        ];
    }

    public function categoria(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNotaCategoria::class, 'categoria_id');
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function rinnovo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Rinnovo::class, 'rinnovo_id');
    }

    public function scontrino(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNotaScontrino::class, 'scontrino_id');
    }

    public function registratoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'registrato_da');
    }

    public function progetto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Progetto::class, 'progetto_id');
    }

    public function verbaleChiusura(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'chiuso_da_verb_id');
    }

    public function tag(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tag::class, 'tag_id');
    }

    public function documenti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrimaNotaDocumento::class, 'prima_nota_id');
    }

    // storno_di_id, giroconto_id: collegamenti alla stessa tabella,
    // ancora senza FK a livello DB (vedi nota nella migration)
    public function stornoDi(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'storno_di_id');
    }

    public function giroconto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'giroconto_id');
    }

    // ----------------------------------------------------------------
    // Stato del movimento
    // ----------------------------------------------------------------

    public function isGiroconto(): bool
    {
        return $this->giroconto_id !== null;
    }

    public function isEntrata(): bool
    {
        return $this->tipo === 'entrata';
    }

    /** Anticipo non ancora rimborsato: non conta nel saldo (v0.37.0). */
    public function isAnticipoAperto(): bool
    {
        return $this->sospeso && $this->rimborsato_il === null;
    }

    public function contoLabel(): string
    {
        return self::CONTI[$this->conto] ?? '—';
    }

    // Non "anno()": l'AuditObserver legge $model->anno e lo prenderebbe per una relazione.
    public function annoMovimento(): int
    {
        return (int) $this->data->format('Y');
    }

    /** Movimenti che contano nel saldo: tutti tranne gli anticipi non rimborsati. */
    public function scopeEffettivi(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->where('sospeso', false)->orWhereNotNull('rimborsato_il'));
    }

    public function scopeAnno(Builder $q, int $anno): Builder
    {
        return $q->whereBetween('data', ["{$anno}-01-01", "{$anno}-12-31"]);
    }
}
