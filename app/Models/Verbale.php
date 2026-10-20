<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Verbale di un organo (v0.44.0, specifiche/verbali.md). Sta in uno dei due
 * libri, «cd» (Consiglio Direttivo) o «assemblee», con il numero
 * progressivo nell'anno (V1) assegnato quando diventa definitivo.
 *
 * Tipi: cd_riunione (da una seduta, con presenze e punti), cd_consultazione
 * (da una consultazione telematica), cd_ammissione (domanda di adesione,
 * testo generato da VerbaleAmmissione), assemblea. Registrato da fuori
 * (da_fuori): nasce già firmato, con il file caricato.
 *
 * Stati: bozza (si modifica) → definitivo (numero, PDF salvato con
 * l'impronta, non si modifica più) → firmato (copia a firma digitale, V2).
 * Un errore dopo il definitivo si corregge con una rettifica.
 *
 * Niente AuditObserver: ogni passo scrive il suo evento (App\Services\Verbali).
 */
class Verbale extends Model
{
    public const LIBRI = ['cd' => 'Consiglio Direttivo', 'assemblee' => 'Assemblee'];

    public const STATI = [
        'bozza' => ['Bozza', 'warn'],
        'definitivo' => ['Definitivo · da firmare', 'warn'],
        'firmato' => ['Firmato', 'ok'],
    ];

    public const TIPI = [
        'cd_riunione' => 'Riunione',
        'cd_consultazione' => 'Consultazione telematica',
        'cd_ammissione' => 'Domanda di adesione',
        'assemblea' => 'Assemblea',
    ];

    protected $table = 'gl_verbali';

    protected $fillable = [
        'tipo',
        'libro',
        'organo',
        'assemblea_id',
        'domanda_id',
        'periodo_da',
        'periodo_a',
        'titolo',
        'presidente_assemblea',
        'segretario_verbalizzante',
        'soci_presenti',
        'soci_deleghe',
        'presenze_cd',
        'data_seduta',
        'luogo',
        'ora_inizio',
        'ora_chiusura',
        'corpo_html',
        'approva_rendiconto_anno',
    ];

    protected function casts(): array
    {
        return [
            'periodo_da' => 'date',
            'periodo_a' => 'date',
            'presenze_cd' => 'array',
            'data_seduta' => 'date',
            'approvato_il' => 'datetime',
            'archiviato_il' => 'datetime',
            'chiuso_il' => 'datetime',
            'definitivo_il' => 'datetime',
            'firmato_il' => 'datetime',
            'da_fuori' => 'boolean',
        ];
    }

    public function assemblea(): BelongsTo
    {
        return $this->belongsTo(Assemblea::class, 'assemblea_id');
    }

    /** La seduta (riunione del CD, Assemblea) da cui viene il verbale. */
    public function seduta(): BelongsTo
    {
        return $this->belongsTo(Seduta::class, 'assemblea_id');
    }

    public function consultazione(): HasOne
    {
        return $this->hasOne(Consultazione::class, 'verbale_id');
    }

    public function domanda(): BelongsTo
    {
        return $this->belongsTo(Domanda::class, 'domanda_id');
    }

    public function domandeChiuse(): HasMany
    {
        return $this->hasMany(Domanda::class, 'verbale_cd_id');
    }

    public function chiusoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chiuso_da');
    }

    public function redattoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'redatto_da');
    }

    public function definitivoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'definitivo_da');
    }

    public function firmatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmato_da');
    }

    public function allegati(): HasMany
    {
        return $this->hasMany(VerbaleAllegato::class, 'verbale_id');
    }

    public function punti(): HasMany
    {
        return $this->hasMany(VerbalePunto::class, 'verbale_id')->orderBy('ordine')->orderBy('id');
    }

    public function rettifiche(): HasMany
    {
        return $this->hasMany(VerbaleRettifica::class, 'verbale_id')->orderBy('numero');
    }

    public function movimentiPrimaNotaChiusi(): HasMany
    {
        return $this->hasMany(PrimaNota::class, 'chiuso_da_verb_id');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, OrganoDocumento> */
    public function documenti()
    {
        return OrganoDocumento::where('allegabile_type', 'verbale')->where('allegabile_id', $this->id)->orderBy('ordine')->orderBy('id')->get();
    }

    // ----------------------------------------------------------------
    // Stato
    // ----------------------------------------------------------------

    public function isBozza(): bool
    {
        return $this->stato === 'bozza';
    }

    public function isDefinitivo(): bool
    {
        return $this->stato === 'definitivo';
    }

    public function isFirmato(): bool
    {
        return $this->stato === 'firmato';
    }

    /** Dal definitivo in poi il testo non cambia più (difetto 2 del Dante). */
    public function isImmutabile(): bool
    {
        return in_array($this->stato, ['definitivo', 'firmato'], true);
    }

    public function statoTesto(): string
    {
        return self::STATI[$this->stato][0] ?? $this->stato;
    }

    public function statoVariante(): string
    {
        return self::STATI[$this->stato][1] ?? 'neutral';
    }

    public function tipoTesto(): string
    {
        if ($this->da_fuori) {
            return 'Registrato da fuori';
        }
        if ($this->tipo === 'assemblea') {
            return Seduta::TIPI[$this->organo] ?? 'Assemblea';
        }

        return self::TIPI[$this->tipo] ?? $this->tipo;
    }

    /** «CD 2027/5», «Assemblee 2027/1», o null se non ha ancora il numero. */
    public function codice(): ?string
    {
        if (! $this->numero_libro) {
            return null;
        }

        return ($this->libro === 'cd' ? 'CD' : 'Assemblee') . ' ' . $this->anno_libro . '/' . $this->numero_libro;
    }

    /** «2027/5» */
    public function numeroTesto(): ?string
    {
        return $this->numero_libro ? $this->anno_libro . '/' . $this->numero_libro : null;
    }

    /** Manca la firma del presidente della seduta (una sola firma, del verbalizzante: V14). */
    public function mancaFirmaPresidente(): bool
    {
        return $this->isFirmato() && $this->firma_dichiarata === 'segretario';
    }

    public function firmaTesto(): ?string
    {
        if (! $this->isFirmato()) {
            return null;
        }
        $n = (int) $this->firme_numero;
        $testo = $this->firma_tipo === 'CAdES'
            ? 'CAdES (.p7m) · dichiarate ' . ($this->firma_dichiarata === 'entrambi' ? '2 firme' : '1 firma')
            : $n . ' ' . ($n === 1 ? 'firma digitale' : 'firme digitali') . ' (PAdES)';

        return $testo;
    }

    /** Verbale di una domanda di adesione con la consultazione già decisa (V11). */
    public function ammissioneChiusa(): bool
    {
        return $this->tipo === 'cd_ammissione' && $this->isBozza()
            && $this->domanda && $this->domanda->votazione_chiusa_il
            && in_array($this->domanda->stato, ['approvata', 'rifiutata', 'scaduta'], true);
    }

    /** Verbali del libro: senza quelli delle domande di prova. */
    public function scopeNelLibro(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('domanda_id')->orWhereHas('domanda', fn ($d) => $d->where('is_test', false)));
    }

    public function scopeLibro(Builder $q, string $libro, int $anno): Builder
    {
        return $q->where('libro', $libro)->whereYear('data_seduta', $anno);
    }
}
