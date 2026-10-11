<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Scadenza dell'associazione (scadenzario.md, tavola SC-1): affiliazione,
 * polizza, adempimenti. Ricorrente ogni anno (giorno e mese fissi) o una
 * tantum. Con una categoria di Prima Nota è un pagamento: «Rinnovata»
 * registra l'uscita; senza, è un adempimento: «Fatta».
 */
#[ObservedBy([AuditObserver::class])]
class Scadenza extends Model
{
    /** Scadenze che il gestionale conosce per nome (riquadri, avvisi): non si eliminano. */
    public const AFFILIAZIONE = 'affiliazione_eps';

    public const POLIZZA = 'polizza_rc';

    public const RENDICONTO = 'rendiconto';

    /** runts resta nell'enum ma non si propone (l'ASD non è nel RUNTS). */
    public const CATEGORIE = [
        'rete_affiliazione' => 'Affiliazione',
        'assicurazione' => 'Assicurazione',
        'assemblea' => 'Assemblea',
        'contabile' => 'Contabile',
        'altro' => 'Altro',
        'runts' => 'RUNTS',
    ];

    public const STATI = [
        'lontana' => 'neutral',
        'in_scadenza' => 'warn',
        'scaduta' => 'ko',
        'fatta' => 'ok',
    ];

    protected $table = 'gl_scadenzario';

    protected $fillable = [
        'chiave',
        'titolo',
        'descrizione',
        'tipo',
        'categoria',
        'prima_nota_categoria_id',
        'data_scadenza',
        'giorno_ricorrenza',
        'mese_ricorrenza',
        'giorni_preavviso',
        'completato',
        'completato_il',
        'note_completamento',
        'assegnato_a',
        'creato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_scadenza' => 'date',
            'completato' => 'boolean',
            'completato_il' => 'date',
            'giorni_preavviso' => 'integer',
            'giorno_ricorrenza' => 'integer',
            'mese_ricorrenza' => 'integer',
        ];
    }

    public function assegnatoA(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assegnato_a');
    }

    public function creatoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da');
    }

    public function categoriaPrimaNota(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNotaCategoria::class, 'prima_nota_categoria_id');
    }

    public function rinnovi(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ScadenzaRinnovo::class, 'scadenza_id');
    }

    public function ultimoRinnovo(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ScadenzaRinnovo::class, 'scadenza_id')->ofMany(['fatto_il' => 'max', 'id' => 'max']);
    }

    /** Scadenze ancora da soddisfare: le ricorrenti sempre, le una tantum finché non sono fatte. */
    public function scopeAperte(Builder $q): Builder
    {
        return $q->where('completato', false);
    }

    public static function perChiave(string $chiave): ?self
    {
        return static::where('chiave', $chiave)->first();
    }

    public function ricorrente(): bool
    {
        return $this->tipo === 'ricorrente';
    }

    public function conPagamento(): bool
    {
        return (bool) $this->prima_nota_categoria_id;
    }

    /** Categoria mostrata nell'elenco: quella di Prima Nota per i pagamenti. */
    public function categoriaTesto(): string
    {
        return $this->categoriaPrimaNota?->nome ?? (self::CATEGORIE[$this->categoria] ?? 'Altro');
    }

    /** Data dell'anno dopo, con giorno e mese della ricorrenza (29/02 → 28/02 negli anni non bisestili). */
    public function prossimaData(): Carbon
    {
        $mese = $this->mese_ricorrenza ?: $this->data_scadenza->month;
        $giorno = $this->giorno_ricorrenza ?: $this->data_scadenza->day;
        $primo = Carbon::create($this->data_scadenza->year + 1, $mese, 1);

        return $primo->day(min($giorno, $primo->daysInMonth))->startOfDay();
    }

    /** Giorni alla scadenza (negativi se passata). */
    public function giorni(?Carbon $oggi = null): int
    {
        return (int) ($oggi ?? today())->copy()->startOfDay()->diffInDays($this->data_scadenza, false);
    }

    /**
     * Stato oggi: fatta (una tantum chiusa), scaduta, in scadenza (entro il
     * preavviso), lontana; con il testo del badge.
     *
     * @return array{stato: string, giorni: ?int, testo: string, variante: string}
     */
    public function stato(?Carbon $oggi = null): array
    {
        if ($this->completato) {
            return ['stato' => 'fatta', 'giorni' => null, 'variante' => 'ok',
                'testo' => 'Fatta' . ($this->completato_il ? ' il ' . $this->completato_il->format('d/m/Y') : '')];
        }
        $g = $this->giorni($oggi);
        $stato = match (true) {
            $g < 0 => 'scaduta',
            $g <= $this->giorni_preavviso => 'in_scadenza',
            default => 'lontana',
        };
        $testo = match (true) {
            $g < -1 => 'scaduta da ' . abs($g) . ' giorni',
            $g === -1 => 'scaduta ieri',
            $g === 0 => 'scade oggi',
            $g === 1 => 'scade domani',
            default => "tra {$g} giorni",
        };

        return ['stato' => $stato, 'giorni' => $g, 'testo' => $testo, 'variante' => self::STATI[$stato]];
    }
}
