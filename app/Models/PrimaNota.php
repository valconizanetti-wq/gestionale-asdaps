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
        'sospeso',
        'anticipante_socio_id',
        'nota_rimborso_id',
        'data_spesa',
        'rimborsato_il',
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
            'data_spesa' => 'date',
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

    /** Socio che ha pagato di tasca propria una spesa anticipata (v0.43.0). */
    public function anticipante(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'anticipante_socio_id')->withTrashed();
    }

    public function notaRimborso(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(NotaRimborso::class, 'nota_rimborso_id');
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

    /** Spesa anticipata (aperta o rimborsata, v0.43.0). */
    public function isAnticipo(): bool
    {
        return (bool) $this->sospeso;
    }

    /**
     * Spesa anticipata dentro una nota non annullata: importo, data, conto e
     * socio non si cambiano, e non si elimina (anticipi.md §3).
     */
    public function inNota(): bool
    {
        return $this->nota_rimborso_id !== null;
    }

    /** «Cognome Nome» di chi ha anticipato. */
    public function anticipanteNome(): string
    {
        return $this->anticipante ? trim($this->anticipante->cognome . ' ' . $this->anticipante->nome) : 'socio non indicato';
    }

    public function contoLabel(): string
    {
        return self::CONTI[$this->conto] ?? '—';
    }

    /** Data della spesa: per un anticipo quella dello scontrino, anche dopo il rimborso (A1). */
    public function dataSpesa(): \Illuminate\Support\Carbon
    {
        return $this->data_spesa ?? $this->data;
    }

    // Non "anno()": l'AuditObserver legge $model->anno e lo prenderebbe per una relazione.
    public function annoMovimento(): int
    {
        return (int) $this->data->format('Y');
    }

    /**
     * Valori aggiunti alla riga dell'Audit Log (AuditObserver). Di un
     * giroconto si registra solo la riga di uscita (l'altra si salva senza
     * eventi, PrimaNotaController): qui il verso, perché l'operazione resti
     * una riga sola; all'eliminazione anche i giustificativi tolti.
     */
    public function auditContesto(string $operazione): array
    {
        $extra = [];
        if ($this->giroconto_id && $this->tipo === 'uscita') {
            $arrivo = $this->conto === 'cassa' ? 'banca' : 'cassa';
            $extra['giroconto'] = self::CONTI[$this->conto] . ' → ' . self::CONTI[$arrivo];
        }
        if ($operazione === 'deleted' && $this->relationLoaded('documenti') && $this->documenti->isNotEmpty()) {
            $extra['giustificativi'] = $this->documenti->pluck('nome')->implode(', ');
        }

        return $extra;
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
