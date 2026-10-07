<?php

namespace App\Models;

use App\Observers\AuditObserver;
use App\Services\Tessere;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Pacchetto di tessere comprato dall'EPS (tavola TS-2): tipo, progressivi
 * dal… al… con il prefisso di stagione («26B»), prezzo e uscita in Prima
 * Nota. Le tessere emesse lo indicano con pacchetto_id.
 */
#[ObservedBy([AuditObserver::class])]
class TesseraPacchetto extends Model
{
    protected $table = 'gl_tessere_pacchetti';

    protected $fillable = [
        'tipo_id', 'acquistato_il', 'prefisso', 'dal', 'al', 'cifre',
        'prezzo_unitario', 'totale', 'prima_nota_id', 'note', 'inserito_da',
    ];

    protected function casts(): array
    {
        return [
            'acquistato_il' => 'date',
            'dal' => 'integer',
            'al' => 'integer',
            'cifre' => 'integer',
            'prezzo_unitario' => 'decimal:2',
            'totale' => 'decimal:2',
        ];
    }

    public function tipo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TesseraTipo::class, 'tipo_id');
    }

    public function tessere(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tesseramento::class, 'pacchetto_id');
    }

    public function movimento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNota::class, 'prima_nota_id');
    }

    public function quantita(): int
    {
        return $this->al - $this->dal + 1;
    }

    /** Numero completo di un progressivo del pacchetto, es. «26B2749167». */
    public function numero(int $progressivo): string
    {
        return Tessere::formatta($this->prefisso, $progressivo, $this->cifre);
    }

    /** «26B2749167–2749185» o «1201–1230». */
    public function intervallo(): string
    {
        return $this->numero($this->dal) . '–' . str_pad((string) $this->al, $this->cifre, '0', STR_PAD_LEFT);
    }

    public function contiene(int $progressivo): bool
    {
        return $progressivo >= $this->dal && $progressivo <= $this->al;
    }

}
