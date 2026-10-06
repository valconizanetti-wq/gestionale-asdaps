<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrimaNota extends Model
{
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
}
