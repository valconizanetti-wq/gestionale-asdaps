<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([AuditObserver::class])]
class Socio extends Model
{
    use SoftDeletes;

    protected $table = 'gl_soci';

    protected $fillable = [
        'numero_tessera',
        'numero_tessera_int',
        'blocco_tessera_id',
        'anno_iscrizione',
        'tipo',
        'fondatore',
        'stato',
        'data_cancellazione',
        'motivo_cancellazione',
        'pseudonimizzato',
        'cognome',
        'nome',
        'data_nascita',
        'luogo_nascita',
        'codice_fiscale',
        'sesso',
        'nazionalita',
        'indirizzo',
        'cap',
        'citta',
        'provincia',
        'email',
        'telefono',
        'tutore_nome',
        'tutore_codice_fiscale',
        'tutore_relazione',
        'tutore_telefono',
        'tutore_email',
        'data_prima_iscrizione',
        'quota_scadenza',
        'note',
        'scheda_registrazione_presente',
        'inserito_da',
        'importato',
        'privacy_accettata_il',
        'privacy_ip',
        'privacy_versione',
        'privacy_metodo',
        'privacy_doc_path',
        'foto_video_consenso',
        'foto_video_il',
        'foto_video_origine',
    ];

    /** Da dove viene lo stato attuale del consenso a foto e video (privacy-link.md §2). */
    public const ORIGINI_FOTO_VIDEO = [
        'domanda' => 'domanda online',
        'link' => 'link personale',
        'revoca_email' => 'richiesta per email',
        'revoca_persona' => 'richiesta di persona',
        'revoca_lettera' => 'richiesta per lettera',
    ];

    /** Modi della revoca registrata dalla segreteria. */
    public const MODI_REVOCA = [
        'revoca_email' => 'Per email',
        'revoca_persona' => 'Di persona',
        'revoca_lettera' => 'Per lettera',
    ];

    protected function casts(): array
    {
        return [
            'data_cancellazione' => 'date',
            'data_nascita' => 'date',
            'data_prima_iscrizione' => 'date',
            'quota_scadenza' => 'date',
            'privacy_accettata_il' => 'datetime',
            'foto_video_il' => 'datetime',
            'foto_video_consenso' => 'boolean',
            'fondatore' => 'boolean',
            'pseudonimizzato' => 'boolean',
            'scheda_registrazione_presente' => 'boolean',
            'importato' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Il default di anno_iscrizione nella migration (date('Y')) viene
        // calcolato una volta sola, quando la migration gira: nel database
        // resta fisso a quell'anno. L'anno lo decide quindi sempre
        // l'applicazione, dalla data di prima iscrizione. Vedi soci.md §4.
        static::creating(function (Socio $socio) {
            $socio->anno_iscrizione ??= (int) ($socio->data_prima_iscrizione?->year ?? now()->year);
        });
    }

    /**
     * Minore età calcolata dalla data di nascita, non dal campo `tipo`
     * (che è solo la categoria di quota). Vedi soci.md §3.
     */
    public function isMinorenne(?\DateTimeInterface $alla = null): bool
    {
        if (! $this->data_nascita) {
            return false;
        }

        return $this->data_nascita->copy()->addYears(18)->isAfter($alla ?? now());
    }

    /**
     * Vero se la categoria di quota non corrisponde più all'età reale
     * (es. registrato come minorenne ma ormai maggiorenne, o viceversa).
     * Le viste lo segnalano con un badge "tipo da aggiornare".
     */
    public function tipoIncoerenteConEta(): bool
    {
        if (! $this->data_nascita) {
            return false;
        }

        return ($this->tipo === 'minorenne') !== $this->isMinorenne();
    }

    /**
     * Documenti necessari per l'iscrizione e loro stato (soci.md §3).
     * Unica regola, usata da scheda ed elenco.
     * 'stato': true = presente, false = mancante, null = non richiesto.
     * 'categoria': categoria di gl_soci_documenti da proporre al caricamento.
     *
     * Nell'elenco, per evitare una query per riga: caricare prima
     * ->with('documenti:id,socio_id,categoria') e, se il certificato è
     * richiesto, ->withExists(['certificatiMedici as certificato_valido' => …]).
     */
    public function requisitiIscrizione(bool $certificatoRichiesto): array
    {
        $caricati = $this->documenti->pluck('categoria')->unique();

        $requisiti = [
            ['chiave' => 'identita', 'label' => "Documento d'identità",
             'stato' => $caricati->contains('documento_identita'), 'categoria' => 'documento_identita'],
            ['chiave' => 'scheda', 'label' => 'Scheda di iscrizione',
             'stato' => $this->scheda_registrazione_presente || $caricati->contains('scheda_iscrizione'), 'categoria' => 'scheda_iscrizione'],
            ['chiave' => 'privacy', 'label' => 'Consenso privacy',
             'stato' => (bool) $this->privacy_accettata_il, 'categoria' => null],
        ];

        if ($this->isMinorenne()) {
            $requisiti[] = ['chiave' => 'tutore', 'label' => 'Modulo del tutore',
                'stato' => $caricati->contains('modulo_tutore'), 'categoria' => 'modulo_tutore'];
        }

        // Certificato medico: previsto ma non richiesto finché l'impostazione
        // certificato_medico_richiesto è spenta (attività non agonistica).
        $requisiti[] = ['chiave' => 'certificato', 'label' => 'Certificato medico',
            'stato' => $certificatoRichiesto
                ? (bool) ($this->certificato_valido ?? self::queryCertificatoValido($this->certificatiMedici())->exists())
                : null,
            'categoria' => null];

        return $requisiti;
    }

    /** Numero di requisiti dell'iscrizione mancanti (0 = completa). */
    public function documentiMancanti(bool $certificatoRichiesto): int
    {
        return collect($this->requisitiIscrizione($certificatoRichiesto))->where('stato', false)->count();
    }

    /** Condizione di "certificato valido", condivisa tra scheda ed elenco. */
    public static function queryCertificatoValido($query)
    {
        return $query->where('stato', 'idoneo')->whereDate('data_scadenza', '>=', today());
    }

    /** Soci minorenni per età alla data odierna (per KPI e filtri). */
    public function scopeMinorenniPerEta(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('data_nascita', '>', now()->subYears(18)->toDateString());
    }

    public function bloccoTessera(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TesseraBlocco::class, 'blocco_tessera_id');
    }

    public function inseritoDa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'inserito_da');
    }

    public function documenti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SocioDocumento::class, 'socio_id');
    }

    public function rinnovi(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Rinnovo::class, 'socio_id');
    }

    public function movimentiPrimaNota(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrimaNota::class, 'socio_id');
    }

    public function privacyLink(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PrivacyLink::class, 'socio_id');
    }

    /** Ultimo link inviato e non ancora usato né sostituito. */
    public function privacyLinkAperto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PrivacyLink::class, 'socio_id')->ofMany(['id' => 'max'], fn ($q) => $q->aperti());
    }

    /**
     * Stato del consenso privacy rispetto all'informativa in vigore
     * (privacy-link.md §3): 'presente', 'da_aggiornare' (letta una versione
     * precedente), 'in_attesa' (link inviato e non usato), 'mancante'.
     * Con un link aperto e una versione precedente vale 'in_attesa'.
     * Per l'elenco: caricare prima ->with('privacyLinkAperto').
     */
    public function statoPrivacy(?string $versioneInVigore = null): string
    {
        $versioneInVigore ??= \App\Services\ProfiloEnte::versione('informativa');

        if ($this->privacy_accettata_il && $this->privacy_versione === $versioneInVigore) {
            return 'presente';
        }
        if ($this->privacyLinkAperto) {
            return 'in_attesa';
        }

        return $this->privacy_accettata_il ? 'da_aggiornare' : 'mancante';
    }

    /**
     * A chi va il link (privacy-link.md §3): al tutore se il socio è
     * minorenne oggi, altrimenti al socio. Null senza indirizzo.
     *
     * @return array{destinatario: string, email: string, nome: string}|null
     */
    public function destinatarioPrivacy(): ?array
    {
        if ($this->isMinorenne()) {
            return filled($this->tutore_email)
                ? ['destinatario' => 'tutore', 'email' => $this->tutore_email, 'nome' => (string) ($this->tutore_nome ?: 'Genitore')]
                : null;
        }

        return filled($this->email)
            ? ['destinatario' => 'socio', 'email' => $this->email, 'nome' => trim("{$this->nome} {$this->cognome}")]
            : null;
    }

    public function domandaOrigine(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Domanda::class, 'socio_id');
    }

    public function volontario(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Volontario::class, 'socio_id');
    }

    public function comunicazioniRicevute(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ComunicazioneDestinatario::class, 'socio_id');
    }

    public function tesseramenti(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tesseramento::class, 'socio_id');
    }

    public function certificatiMedici(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CertificatoMedico::class, 'socio_id');
    }

    public function ruoloTecnico(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Tecnico::class, 'socio_id');
    }

    public function caricheCd(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GovernanceCd::class, 'socio_id');
    }
}
