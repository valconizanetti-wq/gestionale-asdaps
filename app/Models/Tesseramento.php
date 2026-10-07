<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Tessera dell'EPS di un socio (tesseramento-sportivo.md; tessere CSEN
 * v0.37.0, tavole TS-1…TS-5). Vale un anno dall'emissione (data_rilascio →
 * scadenza) e comprende l'assicurazione base: scaduta, il socio non è
 * coperto. Il rinnovo è una tessera nuova con precedente_id.
 *
 * origine: «emessa» dal gestionale (con pacchetto) o «caricata» da un
 * elenco del CSEN (tessere già emesse, senza pacchetto).
 */
#[ObservedBy([AuditObserver::class])]
class Tesseramento extends Model
{
    /** Giorni prima della scadenza in cui la tessera è «in scadenza». */
    public const GIORNI_PREAVVISO = 30;

    public const STATI = [
        'valida' => ['Valida', 'ok'],
        'in_scadenza' => ['In scadenza', 'warn'],
        'scaduta' => ['Tessera scaduta', 'ko'],
        'mancante' => ['Senza tessera', 'ko'],
        'rinnovata' => ['Rinnovata', 'neutral'],
        'futura' => ['Dal giorno di emissione', 'info'],
    ];

    protected $table = 'gl_tesseramenti';

    protected $fillable = [
        'socio_id',
        'stagione_sportiva',
        'ente_affiliante',
        'numero_tessera_csen',
        'progressivo',
        'data_inserimento',
        'data_rilascio',
        'scadenza',
        'pacchetto_tesseramento',
        'tipo_id',
        'pacchetto_id',
        'precedente_id',
        'origine',
        'discipline',
        'portale_csen_il',
        'senza_quota',
        'tecnico_riferimento_id',
        'stato',
        'note',
        'inserito_da',
    ];

    protected function casts(): array
    {
        return [
            'data_inserimento' => 'date',
            'data_rilascio' => 'date',
            'scadenza' => 'date',
            'portale_csen_il' => 'datetime',
            'senza_quota' => 'boolean',
            'progressivo' => 'integer',
        ];
    }

    public function socio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function tipo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TesseraTipo::class, 'tipo_id');
    }

    public function pacchetto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TesseraPacchetto::class, 'pacchetto_id');
    }

    public function precedente(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'precedente_id');
    }

    public function successiva(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(self::class, 'precedente_id');
    }

    public function tecnicoRiferimento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Tecnico::class, 'tecnico_riferimento_id');
    }

    public function inseritoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'inserito_da');
    }

    public function qualifiche(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Qualifica::class, 'gl_tesseramenti_qualifiche', 'tesseramento_id', 'qualifica_id');
    }

    public function divisioni(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DivisioneArco::class, 'gl_tesseramenti_divisioni', 'tesseramento_id', 'divisione_id');
    }

    public function specialita(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(SpecialitaGara::class, 'gl_tesseramenti_specialita', 'tesseramento_id', 'specialita_id');
    }

    public function certificatiMedici(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CertificatoMedico::class, 'tesseramento_id');
    }

    /** Ultimo giorno di validità: un anno dall'emissione (dal 09/01/2026 al 08/01/2027). */
    public static function scadenzaDa(Carbon|string $emessa): Carbon
    {
        return Carbon::parse($emessa)->startOfDay()->addYearNoOverflow()->subDay();
    }

    /** Tessere che coprono una data (di norma oggi). */
    public function scopeValideIl(Builder $q, Carbon|string $data): Builder
    {
        $d = Carbon::parse($data)->toDateString();

        return $q->where('data_rilascio', '<=', $d)->where('scadenza', '>=', $d);
    }

    /**
     * Stato della tessera oggi: valida, in scadenza (entro 30 giorni),
     * scaduta, rinnovata (ce n'è una dopo), futura (emessa con una data che
     * deve ancora venire, es. rinnovo anticipato).
     */
    public function statoOggi(?Carbon $oggi = null): string
    {
        $oggi = ($oggi ?? today())->copy()->startOfDay();
        if ($this->relationLoaded('successiva') ? $this->successiva : $this->successiva()->exists()) {
            return 'rinnovata';
        }
        if (! $this->scadenza) {
            return 'valida';
        }

        return match (true) {
            $this->data_rilascio && $this->data_rilascio->gt($oggi) => 'futura',
            $this->scadenza->lt($oggi) => 'scaduta',
            $this->scadenza->lte($oggi->copy()->addDays(self::GIORNI_PREAVVISO)) => 'in_scadenza',
            default => 'valida',
        };
    }

    /** Giorni alla scadenza (negativi se scaduta). */
    public function giorniAllaScadenza(?Carbon $oggi = null): ?int
    {
        return $this->scadenza ? (int) ($oggi ?? today())->copy()->startOfDay()->diffInDays($this->scadenza, false) : null;
    }

    /** Nome del tipo: dalla tabella dei tipi o, per le righe vecchie, dal campo libero. */
    public function tipoNome(): string
    {
        return $this->tipo?->nome ?? $this->pacchetto_tesseramento ?? '—';
    }
}
