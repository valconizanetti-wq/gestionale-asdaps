<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Tag trasversale dei movimenti di Prima Nota (prima-nota.md §2), ripreso
 * da gestionale-acv: un'etichetta libera, una per movimento, valida per
 * tutti gli anni.
 */
#[ObservedBy([AuditObserver::class])]
class Tag extends Model
{
    protected $table = 'gl_tag';

    protected $fillable = ['nome'];

    public function movimenti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrimaNota::class, 'tag_id');
    }

    /**
     * Trova un tag per nome (senza distinguere maiuscole e minuscole, dopo
     * aver tolto gli spazi ai lati) o lo crea. Va chiamata dentro la stessa
     * transazione del movimento: se il movimento non si salva, il tag non
     * resta "fantasma" (bug trovato in gestionale-acv, prima-nota.php 1.2.0).
     * Nome vuoto: nessun tag.
     */
    public static function trovaOCrea(?string $nome): ?self
    {
        $nome = trim(preg_replace('/\s+/u', ' ', (string) $nome));
        if ($nome === '') {
            return null;
        }
        $nome = mb_substr($nome, 0, 100);

        return static::whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])->first()
            ?? static::create(['nome' => $nome]);
    }
}
