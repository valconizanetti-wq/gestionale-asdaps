<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Voto di un membro del CD su una domanda, tramite link personale
 * (statuto v1.0, art. 16.4). Nome ed email sono un'istantanea al momento
 * dell'apertura della consultazione; 'presidente' fissa chi decide in caso
 * di parità. Un consigliere senza email ha la sua riga (conta nel quorum
 * come non votante) ma nessun link.
 *
 * Senza observer di audit, di proposito: il voto si registra con l'evento
 * di business VOTO_CD_ESPRESSO (solo id e voto, VotazioneCd), e le righe
 * create all'apertura non devono scrivere nel registro nome ed email dei
 * consiglieri.
 */
class VotoCd extends Model
{
    protected $table = 'gl_voti_cd';

    protected $fillable = [
        'domanda_id',
        'governance_cd_id',
        'token_link',
        'token_cifrato',
        'consigliere_nome',
        'consigliere_email',
        'presidente',
        'link_inviato_il',
        'voto',
        'motivo_rifiuto',
        'votato_il',
        'ip_address',
        'user_agent',
    ];

    /** La copia cifrata del token non esce mai dal modello (viste, JSON). */
    protected $hidden = ['token_link', 'token_cifrato'];

    protected function casts(): array
    {
        return [
            'presidente' => 'boolean',
            'votato_il' => 'datetime',
            'link_inviato_il' => 'datetime',
        ];
    }

    public function domanda(): BelongsTo
    {
        return $this->belongsTo(Domanda::class, 'domanda_id');
    }

    public function membroCd(): BelongsTo
    {
        return $this->belongsTo(GovernanceCd::class, 'governance_cd_id');
    }

    /**
     * Token del link di voto. In token_link si salva solo
     * Domanda::hashToken($token): con il token in chiaro nel database,
     * chiunque abbia accesso al database o a un backup potrebbe votare al
     * posto di un consigliere.
     */
    public static function generaToken(): string
    {
        return Str::random(64);
    }

    /**
     * Assegna un token nuovo: impronta per la ricerca, copia cifrata con
     * APP_KEY per rimandare lo stesso link (sollecito, "Invia di nuovo").
     */
    public function assegnaToken(string $token): void
    {
        $this->forceFill([
            'token_link' => Domanda::hashToken($token),
            'token_cifrato' => \Illuminate\Support\Facades\Crypt::encryptString($token),
        ]);
    }

    /** Token in chiaro per il link, o null se non ricostruibile (chiave cambiata). */
    public function tokenInChiaro(): ?string
    {
        if (! $this->token_cifrato) {
            return null;
        }
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($this->token_cifrato);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Cerca il voto dal token in chiaro ricevuto nel link. */
    public static function perToken(string $token): ?self
    {
        if (strlen($token) !== 64) {
            return null;
        }

        return static::where('token_link', Domanda::hashToken($token))->first();
    }

    public function haVotato(): bool
    {
        return $this->voto !== null;
    }

    /** Nome proprio per il saluto delle email ("Ciao Massimo"). */
    public function nomeProprio(): string
    {
        return $this->membroCd?->socio?->nome ?: strtok($this->consigliere_nome, ' ');
    }
}
