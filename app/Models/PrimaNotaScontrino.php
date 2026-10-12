<?php

namespace App\Models;

use App\Observers\AuditObserver;
use App\Support\Importo;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scontrino caricato dal telefono (v0.39.0, specifiche/scontrini.md).
 * Nasce con un movimento su «Uscite da classificare» per il totale; dalla
 * scheda Presidente e Tesoriere danno una categoria a ogni riga e
 * «Registra la divisione» fa un movimento per categoria.
 *
 * Stato (ricalcolato da ricalcolaStato(), mai scritto a mano):
 * - da_quadrare: i movimenti non sommano al totale dello scontrino;
 * - da_verificare: almeno un movimento è ancora da classificare;
 * - verificato: categorie e totale a posto.
 */
#[ObservedBy([AuditObserver::class])]
class PrimaNotaScontrino extends Model
{
    public const STATI = [
        'da_verificare' => 'Da verificare',
        'da_quadrare' => 'Da quadrare',
        'verificato' => 'Verificato',
    ];

    public const ESITI_OCR = [
        'letto' => 'Letto dalla foto',
        'non_letto' => 'Foto non letta',
        'non_disponibile' => 'Lettura non disponibile',
        'errore' => 'Servizio di lettura non raggiungibile',
    ];

    protected $table = 'gl_prima_nota_scontrini';

    protected $fillable = [
        'numero',
        'data_scontrino',
        'importo_totale',
        'conto',
        'nota',
        'anno',
        'soggetto_id',
        'ragione_sociale_raw',
        'partita_iva',
        'ocr_utilizzato',
        'ocr_testo_grezzo',
        'ocr_esito',
        'ocr_ms',
        'righe_lette',
        'allegato_path',
        'allegato_originale',
        'anteprima_path',
        'miniatura_path',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'data_scontrino' => 'date',
            'importo_totale' => 'decimal:2',
            'ocr_utilizzato' => 'boolean',
            'quadrato' => 'boolean',
            'righe_lette' => 'array',
        ];
    }

    public function soggetto(): BelongsTo
    {
        return $this->belongsTo(Soggetto::class, 'soggetto_id');
    }

    public function registratoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrato_da');
    }

    public function movimenti(): HasMany
    {
        return $this->hasMany(PrimaNota::class, 'scontrino_id');
    }

    public function righe(): HasMany
    {
        return $this->hasMany(PrimaNotaScontrinoRiga::class, 'scontrino_id')->orderBy('ordine')->orderBy('id');
    }

    public function file(): HasMany
    {
        return $this->hasMany(PrimaNotaScontrinoFile::class, 'scontrino_id')->orderBy('id');
    }

    /** «SC2027-012»: il codice che dà il nome alle foto. */
    public function codice(): string
    {
        return sprintf('SC%d-%03d', $this->anno, $this->numero);
    }

    public function statoTesto(): string
    {
        return self::STATI[$this->stato] ?? $this->stato;
    }

    public function statoVariante(): string
    {
        return ['da_verificare' => 'warn', 'da_quadrare' => 'ko', 'verificato' => 'ok'][$this->stato] ?? 'neutral';
    }

    public function contoLabel(): string
    {
        return PrimaNota::CONTI[$this->conto] ?? '—';
    }

    /** Nome del fornitore come si vede: quello in elenco o, se tolto, quello letto. */
    public function fornitoreNome(): ?string
    {
        return $this->soggetto?->nome ?? $this->ragione_sociale_raw;
    }

    /**
     * Ricalcola quadrato e stato dai movimenti collegati (dentro la
     * transazione di chi cambia i movimenti). Salva senza eventi: lo stato
     * è una conseguenza, l'operazione che lo cambia ha già la sua riga
     * nell'Audit Log.
     */
    public function ricalcolaStato(): string
    {
        $movimenti = $this->movimenti()->with('categoria:id,codice')->get();
        $somma = $movimenti->sum(fn ($m) => Importo::centesimi($m->importo));
        $quadrato = $movimenti->isNotEmpty() && $somma === Importo::centesimi($this->importo_totale);
        $daClassificare = $movimenti->contains(fn ($m) => $m->categoria?->codice === PrimaNotaCategoria::DA_CLASSIFICARE);

        $stato = ! $quadrato ? 'da_quadrare' : ($daClassificare ? 'da_verificare' : 'verificato');
        $this->forceFill(['quadrato' => $quadrato, 'stato' => $stato]);
        if ($this->isDirty()) {
            static::withoutEvents(fn () => $this->save());
        }

        return $stato;
    }

    /** Valori aggiunti all'Audit Log: il codice dello scontrino. */
    public function auditContesto(string $operazione): array
    {
        return ['scontrino' => $this->codice()];
    }
}
