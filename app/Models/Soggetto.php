<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fornitore o soggetto (gl_soggetti). Dalla v0.39.0 (scontrini.md §5) anche
 * partita IVA (unica se c'è), indirizzo, comune e «attivo»: un fornitore
 * usato non si elimina, si toglie dalla lista. Il movimento di Prima Nota
 * ne copia il nome nel campo `soggetto`; lo scontrino lo collega per id.
 */
#[ObservedBy([AuditObserver::class])]
class Soggetto extends Model
{
    protected $table = 'gl_soggetti';

    protected $fillable = [
        'nome',
        'partita_iva',
        'indirizzo',
        'comune',
        'attivo',
        'email',
        'categoria_id',
    ];

    protected function casts(): array
    {
        return ['attivo' => 'boolean'];
    }

    public function erogazioni(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ErogazioneLiberale::class, 'soggetto_id');
    }

    public function scontrini(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrimaNotaScontrino::class, 'soggetto_id');
    }

    public function scopeAttivi(Builder $q): Builder
    {
        return $q->where('attivo', true);
    }

    /** Riga breve per le liste: «Orvieto · P.IVA 01234567890». */
    public function dettaglio(): string
    {
        return implode(' · ', array_filter([$this->comune, $this->partita_iva ? 'P.IVA ' . $this->partita_iva : null]));
    }
}
