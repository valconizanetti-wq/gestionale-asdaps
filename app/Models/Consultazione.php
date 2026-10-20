<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Consultazione telematica del Consiglio Direttivo su una proposta di
 * delibera (statuto art. 16.4, tavole CT-1…CT-3; analisi V8, V16, V18).
 *
 * Bozza (la prepara il Segretario o il Presidente) → in corso (la avvia il
 * Presidente, o il Vicepresidente in sostituzione) → chiusa alla scadenza o
 * quando hanno votato tutti, oppure ritirata. Alla chiusura il verbale
 * nasce da solo nel libro del CD (App\Services\Consultazioni).
 */
class Consultazione extends Model
{
    public const STATI = [
        'bozza' => ['Bozza', 'warn'],
        'in_corso' => ['In corso', 'info'],
        'chiusa' => ['Chiusa', 'ok'],
        'ritirata' => ['Ritirata', 'neutral'],
    ];

    public const ESITI = [
        'approvata' => ['Approvata', 'ok'],
        'respinta' => ['Respinta', 'ko'],
        'non_valida' => ['Non valida', 'neutral'],
        'riunione' => ['Da discutere in riunione', 'warn'],
        'ritirata' => ['Ritirata', 'neutral'],
    ];

    /** Giorni minimi tra l'avvio e il termine (art. 16.4 a). */
    public const GIORNI_MINIMI = 3;

    protected $table = 'gl_consultazioni';

    protected $fillable = ['oggetto', 'testo_html', 'termine'];

    protected function casts(): array
    {
        return [
            'termine' => 'datetime',
            'avviata_il' => 'datetime',
            'chiusa_il' => 'datetime',
            'in_sostituzione' => 'boolean',
        ];
    }

    public function voti(): HasMany
    {
        return $this->hasMany(ConsultazioneVoto::class, 'consultazione_id')->orderBy('id');
    }

    public function verbale(): BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'verbale_id');
    }

    public function preparataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preparata_da');
    }

    public function avviataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'avviata_da');
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, OrganoDocumento> */
    public function documenti()
    {
        return OrganoDocumento::where('allegabile_type', 'consultazione')->where('allegabile_id', $this->id)->orderBy('ordine')->orderBy('id')->get();
    }

    public function isBozza(): bool
    {
        return $this->stato === 'bozza';
    }

    public function isInCorso(): bool
    {
        return $this->stato === 'in_corso';
    }

    public function statoTesto(): string
    {
        if (in_array($this->stato, ['chiusa', 'ritirata'], true) && $this->esito) {
            return self::ESITI[$this->esito][0];
        }

        return self::STATI[$this->stato][0] ?? $this->stato;
    }

    public function statoVariante(): string
    {
        if (in_array($this->stato, ['chiusa', 'ritirata'], true) && $this->esito) {
            return self::ESITI[$this->esito][1];
        }

        return self::STATI[$this->stato][1] ?? 'neutral';
    }
}
