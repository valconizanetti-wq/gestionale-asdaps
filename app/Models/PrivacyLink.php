<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Link personale per l'informativa privacy e il consenso a foto e video
 * (privacy-link.md). Monouso, senza scadenza; un invio più recente lo
 * annulla. Nel database solo l'impronta del token.
 */
class PrivacyLink extends Model
{
    protected $table = 'gl_privacy_link';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'inviato_il' => 'datetime',
            'annullato_il' => 'datetime',
            'usato_il' => 'datetime',
            'foto_video' => 'boolean',
        ];
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function inviatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviato_da');
    }

    /** Link non ancora usato né sostituito. */
    public function scopeAperti(Builder $query): Builder
    {
        return $query->whereNull('usato_il')->whereNull('annullato_il');
    }

    /** Link dal token in chiaro dell'email (stessa regola dei link della domanda e del voto). */
    public static function perToken(string $token): ?self
    {
        if (strlen($token) !== 64) {
            return null;
        }

        return static::where('token_hash', Domanda::hashToken($token))->first();
    }

    public function isUsato(): bool
    {
        return $this->usato_il !== null;
    }

    /** Si può ancora confermare: non usato, non annullato, socio attivo e non pseudonimizzato. */
    public function isValido(): bool
    {
        $socio = $this->socio;

        return ! $this->usato_il && ! $this->annullato_il
            && $socio && ! $socio->trashed() && $socio->stato === 'attivo' && ! $socio->pseudonimizzato;
    }

    public function perTutore(): bool
    {
        return $this->destinatario === 'tutore';
    }
}
