<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Seduta di un organo (v0.44.0, specifiche/verbali.md): riunione del
 * Consiglio Direttivo; dalla v0.45.0 anche l'Assemblea. Stessa tabella del
 * modello Assemblea (gl_assemblee), che resta per i collegamenti vecchi.
 *
 * Stati: bozza → convocata → svolta; da convocata si rinvia (rinviata →
 * bozza con «Riconvoca») o si annulla. Una seduta svolta ha il suo verbale.
 */
class Seduta extends Model
{
    public const TIPI = ['cd' => 'Consiglio Direttivo', 'ordinaria' => 'Assemblea ordinaria', 'straordinaria' => 'Assemblea straordinaria'];

    public const STATI = [
        'bozza' => ['Bozza', 'warn'],
        'convocata' => ['Convocata', 'info'],
        'svolta' => ['Svolta', 'ok'],
        'rinviata' => ['Rinviata', 'neutral'],
        'annullata' => ['Annullata', 'neutral'],
    ];

    /** Giorni minimi tra convocazione e riunione del CD; ore se urgente (art. 16.1, V4). */
    public const GIORNI_CD = 3;

    public const ORE_URGENTE = 24;

    protected $table = 'gl_assemblee';

    protected $fillable = [
        'tipo', 'data', 'ora_inizio', 'luogo', 'videoconferenza', 'collegamento', 'ordine_del_giorno',
        'urgente', 'motivo_urgenza', 'note',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'ordine_del_giorno' => 'array',
            'videoconferenza' => 'boolean',
            'urgente' => 'boolean',
            'convocazione_inviata_il' => 'datetime',
            'svolta_il' => 'datetime',
            'rinviata_il' => 'datetime',
            'annullata_il' => 'datetime',
        ];
    }

    public function presenze(): HasMany
    {
        return $this->hasMany(SedutaPresenza::class, 'seduta_id')->orderBy('id');
    }

    public function verbale(): HasOne
    {
        return $this->hasOne(Verbale::class, 'assemblea_id');
    }

    public function presidenteSocio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'presidente_socio_id')->withTrashed();
    }

    public function segretarioSocio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'segretario_socio_id')->withTrashed();
    }

    public function creataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creata_da');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, OrganoDocumento> */
    public function documenti()
    {
        return OrganoDocumento::where('allegabile_type', 'seduta')->where('allegabile_id', $this->id)->orderBy('ordine')->orderBy('id')->get();
    }

    public function isCd(): bool
    {
        return $this->tipo === 'cd';
    }

    public function tipoTesto(): string
    {
        return self::TIPI[$this->tipo] ?? $this->tipo;
    }

    public function statoTesto(): string
    {
        return self::STATI[$this->stato][0] ?? $this->stato;
    }

    public function statoVariante(): string
    {
        return self::STATI[$this->stato][1] ?? 'neutral';
    }

    /** Data e ora d'inizio della seduta. */
    public function inizio(): Carbon
    {
        return Carbon::parse($this->data->toDateString() . ' ' . substr((string) ($this->ora_inizio ?: '00:00'), 0, 5));
    }

    /** Punti dell'ordine del giorno, senza i vuoti. @return list<string> */
    public function punti(): array
    {
        return array_values(array_filter(array_map('trim', (array) $this->ordine_del_giorno), fn ($p) => $p !== ''));
    }

    /** Si modifica (data, luogo, ordine del giorno) solo in bozza. */
    public function modificabile(): bool
    {
        return $this->stato === 'bozza';
    }

    /** Presenze dal giorno della seduta, con la seduta convocata. */
    public function presenzeAperte(): bool
    {
        return in_array($this->stato, ['convocata', 'svolta'], true) && ! $this->data->isFuture()
            && (! $this->verbale || $this->verbale->stato === 'bozza');
    }
}
