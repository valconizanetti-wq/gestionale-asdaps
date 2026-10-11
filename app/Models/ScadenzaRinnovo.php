<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Una «Rinnovata» o «Fatta» dello Scadenzario (scadenzario.md §2): quale
 * scadenza ha soddisfatto, quando, con quale pagamento e quale prossima
 * scadenza ha fissato.
 */
#[ObservedBy([AuditObserver::class])]
class ScadenzaRinnovo extends Model
{
    protected $table = 'gl_scadenzario_rinnovi';

    protected $fillable = [
        'scadenza_id',
        'data_scadenza',
        'fatto_il',
        'importo',
        'conto',
        'prima_nota_id',
        'prossima_scadenza',
        'note',
        'registrato_da',
    ];

    protected function casts(): array
    {
        return [
            'data_scadenza' => 'date',
            'fatto_il' => 'date',
            'prossima_scadenza' => 'date',
            'importo' => 'decimal:2',
        ];
    }

    public function scadenza(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Scadenza::class, 'scadenza_id');
    }

    public function movimento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PrimaNota::class, 'prima_nota_id');
    }

    public function registratoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'registrato_da');
    }
}
