<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nota di rimborso delle spese anticipate da un socio (v0.43.0,
 * specifiche/anticipi.md, tavole AN-3, AN-4, AN-4b, AN-2).
 *
 * Tre passi: la nota (emessa), la copia firmata (firmata), il pagamento
 * (pagata). Una nota non pagata si annulla con il motivo e resta nell'elenco
 * con il suo numero, che non si riusa. Tipo «rinuncia»: il socio rinuncia al
 * rimborso e dona l'importo (A6); al passo 3 si registrano l'uscita e
 * un'entrata uguale in cassa.
 *
 * Niente AuditObserver: ogni passo scrive la sua riga con un evento proprio
 * (NoteRimborso), una riga per operazione anche quando cambiano più spese.
 *
 * `dati` è la fotografia presa alla generazione:
 *   socio: {nome, cognome, codice_fiscale, carica},
 *   visto: {carica, nome},
 *   righe: list<{movimento_id, data, descrizione, categoria, codice_riga,
 *                fornitore, documento, importo, giustificativi}>,
 *   generata_il: 'Y-m-d H:i'.
 */
class NotaRimborso extends Model
{
    public const STATI = [
        'emessa' => 'Emessa · da firmare',
        'firmata' => 'Firmata · da pagare',
        'pagata' => 'Pagata',
        'annullata' => 'Annullata',
    ];

    /** Stati della rinuncia (stesse chiavi, altre parole). */
    public const STATI_RINUNCIA = [
        'emessa' => 'Emessa · da firmare',
        'firmata' => 'Firmata · da registrare',
        'pagata' => 'Registrata',
        'annullata' => 'Annullata',
    ];

    public const MODALITA = ['cassa' => 'In contanti, dalla cassa', 'banca' => 'Con bonifico bancario'];

    protected $table = 'gl_note_rimborso';

    protected $fillable = [
        'numero', 'anno', 'progressivo', 'tipo', 'socio_id', 'data_nota', 'modalita', 'iban', 'note',
        'totale', 'numero_spese', 'dati', 'stato', 'pdf_path',
        'firmata_path', 'firmata_nome', 'firmata_il', 'firmata_da',
        'pagata_il', 'conto', 'pagamento_registrato_il', 'pagata_da',
        'annullata_il', 'annullata_da', 'motivo_annullamento', 'preparata_da',
    ];

    protected function casts(): array
    {
        return [
            'data_nota' => 'date',
            'pagata_il' => 'date',
            'firmata_il' => 'datetime',
            'pagamento_registrato_il' => 'datetime',
            'annullata_il' => 'datetime',
            'totale' => 'decimal:2',
            'dati' => 'array',
        ];
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id')->withTrashed();
    }

    /** Le spese della nota (per la rinuncia anche l'entrata della donazione). */
    public function movimenti(): HasMany
    {
        return $this->hasMany(PrimaNota::class, 'nota_rimborso_id');
    }

    /** Solo le spese anticipate (senza l'entrata della rinuncia). */
    public function spese(): HasMany
    {
        return $this->movimenti()->where('sospeso', true);
    }

    public function preparataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preparata_da');
    }

    public function firmataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'firmata_da');
    }

    public function pagataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pagata_da');
    }

    public function annullataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annullata_da');
    }

    public function isRinuncia(): bool
    {
        return $this->tipo === 'rinuncia';
    }

    public function isAnnullata(): bool
    {
        return $this->stato === 'annullata';
    }

    public function isPagata(): bool
    {
        return $this->stato === 'pagata';
    }

    /** Si annulla finché non è pagata (T6). */
    public function annullabile(): bool
    {
        return in_array($this->stato, ['emessa', 'firmata'], true);
    }

    public function statoTesto(): string
    {
        $testi = $this->isRinuncia() ? self::STATI_RINUNCIA : self::STATI;
        $testo = $testi[$this->stato] ?? $this->stato;
        if ($this->isPagata() && $this->pagata_il) {
            $testo .= ' il ' . $this->pagata_il->format('d/m/Y') . ($this->isRinuncia() ? '' : ' · ' . (PrimaNota::CONTI[$this->conto] ?? ''));
        }

        return $testo;
    }

    public function statoVariante(): string
    {
        return ['emessa' => 'info', 'firmata' => 'warn', 'pagata' => 'ok', 'annullata' => 'neutral'][$this->stato] ?? 'neutral';
    }

    /** «Tascio Luigi» dalla fotografia, o dal socio se manca. */
    public function socioNome(): string
    {
        $s = $this->dati['socio'] ?? null;

        return $s ? trim($s['cognome'] . ' ' . $s['nome']) : trim(($this->socio?->cognome ?? '') . ' ' . ($this->socio?->nome ?? ''));
    }

    /** Conto fissato dalla modalità scritta nella nota (E3); la rinuncia va in cassa. */
    public function contoPrevisto(): string
    {
        return $this->isRinuncia() ? 'cassa' : (string) $this->modalita;
    }

    /** Passo corrente (1 nota, 2 firma, 3 pagamento; 4 = tutto fatto). */
    public function passo(): int
    {
        return match ($this->stato) {
            'emessa' => 2,
            'firmata' => 3,
            'pagata' => 4,
            default => 0,
        };
    }

    public function etichetta(): string
    {
        return $this->isRinuncia() ? 'Rinuncia al rimborso' : 'Nota di rimborso';
    }
}
