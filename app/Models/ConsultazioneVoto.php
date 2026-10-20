<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Voto di un membro del CD in una consultazione telematica, dal suo link
 * personale (tavola CT-2). Come per le domande (VotoCd): nel database solo
 * l'impronta del token e una copia cifrata con APP_KEY per il sollecito.
 * Per ogni voto data, ora, indirizzo e collegamento (art. 16.4 e).
 */
class ConsultazioneVoto extends Model
{
    public const VOTI = [
        'favorevole' => 'Favorevole',
        'contrario' => 'Contrario',
        'astenuto' => 'Astenuto',
        'riunione' => 'Ne discutiamo in riunione',
    ];

    protected $table = 'gl_consultazioni_voti';

    protected $hidden = ['token_hash', 'token_cifrato'];

    protected $fillable = ['consultazione_id', 'governance_cd_id', 'socio_id', 'nome', 'carica', 'email', 'presidente'];

    protected function casts(): array
    {
        return ['presidente' => 'boolean', 'votato_il' => 'datetime', 'sollecitato_il' => 'datetime'];
    }

    public function consultazione(): BelongsTo
    {
        return $this->belongsTo(Consultazione::class, 'consultazione_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id')->withTrashed();
    }

    public static function generaToken(): string
    {
        return Str::random(64);
    }

    public function assegnaToken(string $token): void
    {
        $this->forceFill(['token_hash' => hash('sha256', $token), 'token_cifrato' => Crypt::encryptString($token)]);
    }

    public function tokenInChiaro(): ?string
    {
        try {
            return $this->token_cifrato ? Crypt::decryptString($this->token_cifrato) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function perToken(string $token): ?self
    {
        return strlen($token) === 64 ? static::where('token_hash', hash('sha256', $token))->first() : null;
    }

    public function haVotato(): bool
    {
        return $this->voto !== null;
    }

    /** «…4f9a»: l'identificativo del collegamento mostrato nel verbale. */
    public function collegamento(): ?string
    {
        return $this->token_hash ? '…' . substr($this->token_hash, -4) : null;
    }

    public function votoTesto(): string
    {
        return self::VOTI[$this->voto] ?? 'non ha votato';
    }

    public function nomeProprio(): string
    {
        return $this->socio?->nome ?: (string) strtok($this->nome, ' ');
    }
}
