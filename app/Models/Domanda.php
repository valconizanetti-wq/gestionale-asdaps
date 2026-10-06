<?php

namespace App\Models;

use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Domanda di iscrizione (scheda domande-iscrizione.md).
 *
 * Regole dell'esito (statuto v1.0 artt. 6.4 e 16.4, come modificati su
 * decisione di Paolo il 29/09/2026, domande-iscrizione.md §3):
 * - il CD decide entro 5 giorni dalla presentazione della domanda;
 * - se alla scadenza non ha votato la maggioranza dei componenti, la
 *   domanda è accolta d'ufficio;
 * - altrimenti decide la maggioranza dei voti espressi; in parità prevale
 *   il voto del Presidente e, se non ha votato, decide il Presidente;
 * - la votazione si chiude alla scadenza, o prima quando hanno votato
 *   tutti; il Presidente non la chiude (interviene solo sulla parità e
 *   per scrivere il diniego).
 */
#[ObservedBy([AuditObserver::class])]
class Domanda extends Model
{
    protected $table = 'gl_domande';

    /** Giorni per la decisione del CD dalla conferma (statuto art. 6.4). */
    public const GIORNI_DECISIONE_CD = 5;

    /** Giorni per comunicare il diniego (art. 6.5). */
    public const GIORNI_COMUNICAZIONE_DINIEGO = 10;

    /** Giorni per versare la quota dopo l'accoglimento (art. 6.6). */
    public const GIORNI_VERSAMENTO_QUOTA = 5; // statuto art. 6.6 (5 giorni: decisione di Paolo, 30/09/2026; prima 30)

    /**
     * Margine dopo il termine prima della decadenza automatica: il termine
     * riguarda il bonifico disposto, e l'accredito e la registrazione della
     * segreteria possono arrivare qualche giorno dopo (Paolo, 30/09/2026).
     */
    public const GIORNI_MARGINE_DECADENZA = 5;

    /**
     * Età da cui il minore presta personalmente l'assenso al tesseramento,
     * che altrimenti non può avvenire (art. 16, c. 2, D.Lgs. 36/2021;
     * statuto art. 6.3). Decisione di Paolo del 04/10/2026: soglia di
     * legge, 14 anni compiuti al giorno dell'invio.
     */
    public const ETA_ASSENSO_MINORE = 14;

    /** Riferimenti normativi in piccolo sotto la dichiarazione (Paolo, 04/10/2026: "riporta sempre il riferimento alla normativa"). */
    public const NORMA_ASSENSO = "Art. 16, c. 2, D.Lgs. 36/2021: chi ha compiuto 14 anni non può essere tesserato senza il proprio assenso.";

    public const NORMA_IMMAGINE = "Foto e video: art. 10 c.c. e art. 96 L. 633/1941 (diritto all'immagine).";

    /**
     * Campi di cui l'AuditObserver registra il valore; degli altri
     * (anagrafica, recapiti, documento, IP, motivazioni) registra solo il
     * nome. Le domande non accolte o non confermate vanno cancellate entro
     * i termini dell'informativa (§7): i loro dati personali non devono
     * restare nel registro delle operazioni.
     *
     * @var list<string>
     */
    public array $auditCampiConValore = [
        'stato', 'tipo_socio', 'cartacea', 'is_test', 'silenzio_assenso',
        'consenso_privacy', 'consenso_arbitrato', 'consenso_extra_ue', 'consenso_foto_video',
        'assenso_minore_il', 'informativa_versione', 'statuto_versione',
        'scadenza_cd', 'esito_il', 'esito_comunicato_il', 'quota_scadenza', 'quota_versata_il',
        'verbale_cd_id', 'socio_id',
        'votazione_chiusa_il', 'votazione_chiusura', 'sollecito_inviato_il', 'promemoria_diniego_il',
        'decisione_presidente_il', 'quota_importo', 'quota_anno', 'quota_promo',
    ];

    /** Possibili esiti di una consultazione. */
    public const ESITO_APPROVATA = 'approvata';
    public const ESITO_RIFIUTATA = 'rifiutata';
    public const ESITO_D_UFFICIO = 'accolta_d_ufficio';
    /** Voti pari e Presidente che non ha votato: decide lui. */
    public const ESITO_PARITA = 'parita';

    /** Giorno (dalla presentazione) del sollecito a chi non ha votato. */
    public const GIORNO_SOLLECITO = 3;

    /** Giorni dopo la delibera per il promemoria del diniego al Presidente. */
    public const GIORNI_PROMEMORIA_DINIEGO = 7;

    /**
     * Modi di chiusura della votazione (gl_domande.votazione_chiusura): alla
     * scadenza, o prima quando hanno votato tutti. Il Presidente non chiude
     * la votazione: interviene solo sulla parità e per il diniego (Paolo,
     * 29/09/2026).
     */
    public const CHIUSURA_SCADENZA = 'scadenza';
    public const CHIUSURA_TUTTI = 'tutti';

    protected $fillable = [
        'token',
        'cognome',
        'nome',
        'data_nascita',
        'luogo_nascita',
        'codice_fiscale',
        'sesso',
        'email',
        'telefono',
        'indirizzo',
        'cap',
        'citta',
        'provincia',
        'tipo_socio',
        'genitore_nome',
        'genitore_codice_fiscale',
        'genitore_email',
        'genitore_data_nascita',
        'genitore_luogo_nascita',
        'genitore_telefono',
        'ip_address',
        'user_agent',
        'informativa_aperta_il',
        'statuto_aperto_il',
        'informativa_hash',
        'informativa_versione',
        'statuto_versione',
        'documento_tipo',
        'documento_numero',
        'documento_rilasciato_da',
        'documento_data_rilascio',
        'consenso_privacy',
        'consenso_arbitrato',
        'consenso_extra_ue',
        'consenso_foto_video',
        'assenso_minore_il',
        'stato',
        'token_conferma',
        'token_conferma_scadenza',
        'scadenza_cd',
        'votazione_chiusa_il',
        'votazione_chiusura',
        'sollecito_inviato_il',
        'esito_il',
        'esito_comunicato_il',
        'promemoria_diniego_il',
        'decisione_presidente_il',
        'quota_scadenza',
        'quota_importo',
        'quota_anno',
        'quota_promo',
        'quota_versata_il',
        'motivo_rifiuto',
        'commento_domanda',
        'cartacea',
        'is_test',
        'silenzio_assenso',
        'verbale_cd_id',
        'ricevuta_path',
        'ricevuta_at',
        'socio_id',
    ];

    protected function casts(): array
    {
        return [
            'data_nascita' => 'date',
            'genitore_data_nascita' => 'date',
            'documento_data_rilascio' => 'date',
            'informativa_aperta_il' => 'datetime',
            'statuto_aperto_il' => 'datetime',
            'consenso_privacy' => 'boolean',
            'consenso_arbitrato' => 'boolean',
            'consenso_extra_ue' => 'boolean',
            'consenso_foto_video' => 'boolean',
            'assenso_minore_il' => 'datetime',
            'token_conferma_scadenza' => 'datetime',
            'scadenza_cd' => 'datetime',
            'votazione_chiusa_il' => 'datetime',
            'sollecito_inviato_il' => 'datetime',
            'promemoria_diniego_il' => 'datetime',
            'decisione_presidente_il' => 'datetime',
            'quota_importo' => 'decimal:2',
            'quota_promo' => 'boolean',
            'esito_il' => 'datetime',
            'esito_comunicato_il' => 'datetime',
            'quota_scadenza' => 'date',
            'quota_versata_il' => 'date',
            'cartacea' => 'boolean',
            'is_test' => 'boolean',
            'silenzio_assenso' => 'boolean',
            'ricevuta_at' => 'datetime',
        ];
    }

    // ── Relazioni ────────────────────────────────────────────────────────

    public function verbaleCd(): BelongsTo
    {
        return $this->belongsTo(Verbale::class, 'verbale_cd_id');
    }

    public function socio(): BelongsTo
    {
        return $this->belongsTo(Socio::class, 'socio_id');
    }

    public function voti(): HasMany
    {
        return $this->hasMany(VotoCd::class, 'domanda_id');
    }

    public function emailLog(): HasMany
    {
        return $this->hasMany(EmailLog::class, 'domanda_id');
    }

    // ── Utilità ──────────────────────────────────────────────────────────

    /**
     * Token dei link pubblici (conferma email, annullamento). Nel database
     * si salva solo hashToken($token); il token in chiaro esiste solo
     * nell'email. Chi legge il database o un backup non può usare i link.
     * Stessa regola già decisa per il link del consenso privacy.
     */
    public static function generaToken(): string
    {
        return Str::random(64);
    }

    /** SHA-256 esadecimale: 64 caratteri, entra nelle colonne esistenti. */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function nomeCompleto(): string
    {
        return trim($this->cognome . ' ' . $this->nome);
    }

    /** Minorenne alla data della domanda: decide tipo_socio e chi firma. */
    public function richiedenteMinorenne(): bool
    {
        if (! $this->data_nascita) {
            return false;
        }

        $riferimento = $this->created_at ?? now();

        return $this->data_nascita->copy()->addYears(18)->greaterThan($riferimento);
    }

    // ── Assenso del minore (art. 16 D.Lgs. 36/2021, statuto art. 6.3) ────

    /**
     * Il minore ha compiuto 14 anni (ma non 18) alla data indicata: serve
     * il suo assenso. Il giorno del 14° compleanno conta già. Usata sia sui
     * dati in sessione del modulo (prima che la domanda esista) sia sulla
     * domanda salvata, sempre con la stessa regola.
     */
    public static function assensoRichiesto(\DateTimeInterface|string|null $nascita, ?\DateTimeInterface $alla = null): bool
    {
        if (! $nascita) {
            return false;
        }

        $nascita = \Illuminate\Support\Carbon::parse($nascita)->startOfDay();
        $giorno = \Illuminate\Support\Carbon::parse($alla ?? now())->startOfDay();

        return $nascita->copy()->addYears(self::ETA_ASSENSO_MINORE)->lessThanOrEqualTo($giorno)
            && $nascita->copy()->addYears(18)->greaterThan($giorno);
    }

    /** Anni compiuti alla data indicata (per il testo "ha 15 anni"). */
    public static function anniCompiuti(\DateTimeInterface|string $nascita, ?\DateTimeInterface $alla = null): int
    {
        return (int) \Illuminate\Support\Carbon::parse($nascita)->startOfDay()
            ->diffInYears(\Illuminate\Support\Carbon::parse($alla ?? now())->startOfDay());
    }

    /**
     * Testo della dichiarazione del minore, uguale in modulo, ricevuta e
     * documentazione: [testo base, clausola su foto e video]. La clausola
     * c'è solo se il genitore ha dato il consenso a foto e video.
     *
     * @return array{0: string, 1: string}
     */
    public static function partiTestoAssenso(string $nomeCognome): array
    {
        return [
            "Io, {$nomeCognome}, ho letto con il mio genitore lo statuto e l'informativa e sono d'accordo con la mia iscrizione alla Compagnia, con il tesseramento al CSEN",
            " e, con il consenso del mio genitore, alla pubblicazione di foto e video che mi ritraggono",
        ];
    }

    public static function testoAssenso(string $nomeCognome, bool $fotoVideo): string
    {
        [$base, $foto] = self::partiTestoAssenso($nomeCognome);

        return $base . ($fotoVideo ? $foto : '') . '.';
    }

    /** Questa domanda richiedeva l'assenso del minore (età alla data dell'invio). */
    public function richiedeAssensoMinore(): bool
    {
        return $this->tipo_socio === 'minorenne'
            && self::assensoRichiesto($this->data_nascita, $this->created_at ?? now());
    }

    /** Testo dichiarato dal minore, o null se l'assenso non c'è. */
    public function testoAssensoMinore(): ?string
    {
        return $this->assenso_minore_il ? self::testoAssenso($this->nomeCognome(), (bool) $this->consenso_foto_video) : null;
    }

    /** Anni del minore alla data della domanda. */
    public function etaAllaDomanda(): ?int
    {
        return $this->data_nascita ? self::anniCompiuti($this->data_nascita, $this->created_at ?? now()) : null;
    }

    /**
     * Cancellazione alla scadenza della conservazione (informativa §7):
     * domande non confermate dopo 30 giorni, non accolte dopo 12 mesi.
     * Un solo punto che sa dove finiscono i dati di una domanda:
     * - gl_voti_cd: cancellati con la domanda (FK cascadeOnDelete);
     * - gl_email_log: la riga resta come prova dell'invio, ma senza
     *   indirizzo, nome, oggetto (contiene il nominativo) ed eventuale
     *   messaggio d'errore (può contenere l'indirizzo); la FK diventa NULL;
     * - ricevuta PDF: file eliminato dal disco;
     * - gl_verbali: il verbale resta (libro sociale); la FK diventa NULL;
     * - gl_audit_log: nessun dato personale da togliere, grazie a
     *   $auditCampiConValore.
     * Da non usare per le domande accolte: il loro dato passa al socio.
     */
    public function cancellaPerFineConservazione(): void
    {
        if ($this->socio_id !== null) {
            throw new \LogicException("Domanda {$this->id}: collegata a un socio, non si cancella.");
        }

        $ricevuta = $this->ricevuta_path;

        DB::transaction(function () {
            EmailLog::where('domanda_id', $this->id)->update([
                'destinatario_email' => '[cancellato]',
                'destinatario_nome'  => null,
                'oggetto'            => null,
                'errore'             => null,
            ]);

            $this->delete();
        });

        // Dopo il commit: se la transazione fallisse, il file resterebbe
        // insieme alla domanda, come deve.
        if ($ricevuta) {
            Storage::disk('local')->delete($ricevuta);
        }
    }

    // ── Stati ────────────────────────────────────────────────────────────
    // in_verifica  domanda del vecchio flusso, in attesa della conferma via
    //              email (prima della v0.30.0; oggi non si crea più)
    // in_attesa    votazione del CD in corso
    // approvata    accolta (anche d'ufficio): quota da versare entro 5 giorni
    // da_rifiutare maggioranza contraria: il Presidente scrive il diniego
    // in_parita    voti pari senza il voto del Presidente: decide lui
    // rifiutata    diniego motivato comunicato al richiedente
    // decaduta     accolta, quota non versata nel termine
    // scaduta      vecchio flusso: mai confermata entro 48 ore

    public function isInVerifica(): bool  { return $this->stato === 'in_verifica'; }
    public function isInAttesa(): bool    { return $this->stato === 'in_attesa'; }
    public function isApprovata(): bool   { return $this->stato === 'approvata'; }
    public function isDaRifiutare(): bool { return $this->stato === 'da_rifiutare'; }
    public function isInParita(): bool    { return $this->stato === 'in_parita'; }
    public function isRifiutata(): bool   { return $this->stato === 'rifiutata'; }
    public function isDecaduta(): bool    { return $this->stato === 'decaduta'; }

    /** Termine del CD passato (art. 6.4). */
    public function terminiCdScaduti(): bool
    {
        return $this->scadenza_cd !== null && $this->scadenza_cd->isPast();
    }

    /** Accolta ma quota non ancora versata, entro o oltre il termine (GIORNI_VERSAMENTO_QUOTA). */
    public function inAttesaDiQuota(): bool
    {
        return $this->isApprovata() && $this->socio_id === null;
    }

    /** Il richiedente può ancora ritirarla dal link della ricevuta. */
    public function annullabile(): bool
    {
        if ($this->socio_id !== null) {
            return false;
        }
        // Accolta: si ritira solo fino al termine per versare la quota
        // (Paolo, 30/09/2026). Dopo, il link porta alla pagina "non è più
        // possibile annullare" (iscrizione.gia-decisa).
        if ($this->stato === 'approvata') {
            return $this->quota_scadenza === null || today()->lte($this->quota_scadenza);
        }

        return in_array($this->stato, ['in_verifica', 'in_attesa', 'da_rifiutare', 'in_parita'], true);
    }

    /** Giorno dal quale la domanda accolta decade se la quota non risulta registrata. */
    public function dataDecadenza(): ?\Illuminate\Support\Carbon
    {
        return $this->quota_scadenza?->copy()->addDays(self::GIORNI_MARGINE_DECADENZA + 1);
    }

    /** Termine per comunicare il diniego (art. 6.5): 10 giorni dalla delibera. */
    public function scadenzaDiniego(): ?\Illuminate\Support\Carbon
    {
        return $this->esito_il?->copy()->addDays(self::GIORNI_COMUNICAZIONE_DINIEGO);
    }

    /** Chi riceve le email della domanda: il genitore, per un minore. */
    public function emailDestinatario(): string
    {
        return $this->genitore_email ?: $this->email;
    }

    public function nomeDestinatario(): string
    {
        return $this->genitore_nome ?: $this->nomeCompleto();
    }

    /** "Luca Verdi" (nome prima del cognome), per i testi rivolti a persone. */
    public function nomeCognome(): string
    {
        return trim($this->nome . ' ' . $this->cognome);
    }

    // ── Voti ─────────────────────────────────────────────────────────────
    // I conteggi per le viste usano la relazione caricata ($this->voti),
    // così elenco e scheda non fanno una query per ogni numero. Attenzione:
    // dopo aver salvato un voto nella stessa richiesta, la relazione già
    // caricata è vecchia; va ricaricata con $domanda->load('voti').
    // Le decisioni (esitoAttuale, tuttiHannoVotato) invece leggono sempre
    // dal database, perché un conteggio vecchio chiuderebbe la votazione
    // con un esito sbagliato.

    public function votiEspressi(): int
    {
        return $this->voti->whereNotNull('voto')->count();
    }

    public function votiFavorevoli(): int
    {
        return $this->voti->where('voto', 'favorevole')->count();
    }

    public function votiContrari(): int
    {
        return $this->voti->where('voto', 'contrario')->count();
    }

    /** "2 favorevoli, 1 contrario, 1 non votante" (dalla relazione caricata). */
    public function riepilogoVoti(bool $conNonVotanti = true): string
    {
        $parola = fn (int $n, string $uno, string $piu) => $n . ' ' . ($n === 1 ? $uno : $piu);
        $parti = [
            $parola($this->votiFavorevoli(), 'favorevole', 'favorevoli'),
            $parola($this->votiContrari(), 'contrario', 'contrari'),
        ];
        $mancanti = $this->voti->count() - $this->votiEspressi();
        if ($conNonVotanti && $mancanti > 0) {
            $parti[] = $parola($mancanti, 'non votante', 'non votanti');
        }

        return implode(', ', $parti);
    }

    /** Maggioranza dei componenti del CD (art. 16.4, lett. c). */
    public function quorum(): int
    {
        return intdiv($this->voti->count(), 2) + 1;
    }

    /** Esito se la votazione si chiudesse adesso, con i voti espressi. */
    public function esitoAttuale(): string
    {
        return self::calcolaEsito($this->votiPerEsito());
    }

    /** Hanno votato tutti i consiglieri convocati (chiusura anticipata, art. 16.4 lett. d). */
    public function tuttiHannoVotato(): bool
    {
        $voti = $this->voti()->get(['voto']);

        return $voti->isNotEmpty() && $voti->whereNull('voto')->isEmpty();
    }

    /** Voti letti dal database nella forma usata da calcolaEsito(). */
    private function votiPerEsito(): Collection
    {
        return $this->voti()
            ->get(['voto', 'presidente'])
            ->map(fn (VotoCd $v) => ['voto' => $v->voto, 'presidente' => (bool) $v->presidente])
            ->values();
    }

    /**
     * Regola dell'esito, indipendente dal database (testabile a parte).
     * - Sotto il quorum (maggioranza dei componenti) → accolta d'ufficio.
     * - Maggioranza dei voti espressi → approvata o rifiutata.
     * - Parità: prevale il voto del Presidente; se non ha votato → parita
     *   (decide il Presidente, decisione di Paolo del 29/09/2026).
     *
     * @param  Collection<int, array{voto: ?string, presidente: bool}>  $voti
     */
    public static function calcolaEsito(Collection $voti): string
    {
        $totale     = $voti->count();
        $espressi   = $voti->whereNotNull('voto')->count();
        $favorevoli = $voti->where('voto', 'favorevole')->count();
        $contrari   = $voti->where('voto', 'contrario')->count();

        if ($totale === 0 || $espressi < intdiv($totale, 2) + 1) {
            return self::ESITO_D_UFFICIO;
        }

        if ($favorevoli !== $contrari) {
            return $favorevoli > $contrari ? self::ESITO_APPROVATA : self::ESITO_RIFIUTATA;
        }

        $votoPresidente = $voti->firstWhere('presidente', true)['voto'] ?? null;

        return match ($votoPresidente) {
            'favorevole' => self::ESITO_APPROVATA,
            'contrario'  => self::ESITO_RIFIUTATA,
            default      => self::ESITO_PARITA,
        };
    }
}
