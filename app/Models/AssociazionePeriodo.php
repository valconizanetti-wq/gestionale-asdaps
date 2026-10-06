<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Periodo dell'ente (profilo-ente.md): denominazione, forma giuridica,
 * sede legale, RASD, regime fiscale, affiliazione e testi in vigore dal
 * giorno `dal` fino al giorno prima del periodo successivo.
 */
class AssociazionePeriodo extends Model
{
    protected $table = 'gl_associazione_periodi';

    protected $fillable = [
        'dal', 'denominazione', 'sigla', 'forma_giuridica', 'forma_descrizione',
        'sede_legale', 'rasd_numero', 'rasd_data_iscrizione', 'regime_fiscale',
        'ente_affiliazione', 'statuto_versione', 'informativa_versione', 'atto',
    ];

    /** Forme giuridiche: etichetta, descrizione predefinita e riferimento normativo. */
    public const FORME = [
        'associazione' => ['Associazione non riconosciuta', null, 'Artt. 36 e segg. c.c.'],
        'asd' => ['Associazione sportiva dilettantistica (A.S.D.)', 'Associazione Sportiva Dilettantistica', 'D.Lgs. 36/2021, art. 7; D.Lgs. 39/2021'],
        'ssd' => ['Società sportiva dilettantistica (S.S.D.)', 'Società Sportiva Dilettantistica', 'D.Lgs. 36/2021, art. 7'],
        'aps' => ['Associazione di promozione sociale (A.P.S.)', 'Associazione di Promozione Sociale', 'D.Lgs. 117/2017'],
    ];

    /** Regimi fiscali: etichetta e riferimento normativo. */
    public const REGIMI = [
        'non_commerciale' => ['Ente non commerciale', 'Art. 148 TUIR'],
        'l398' => ['Opzione L. 398/1991', 'L. 398/1991'],
        'ordinario' => ['Ordinario', null],
    ];

    /** Voci della lista di controllo che si spuntano a mano. */
    public const SPUNTE_MANUALI = [
        'affiliazione_rinnovata' => ['Affiliazione rinnovata con la nuova denominazione', "Da confermare con l'ente di affiliazione."],
        'conto_bancario' => ['Conto bancario intestato alla nuova denominazione', "Intestatario e IBAN usati nell'email di accoglimento."],
        'sito_web' => ['Sito web aggiornato', 'Piè di pagina e pagine del sito con la nuova denominazione (a mano, su WordPress).'],
    ];

    protected static function booted(): void
    {
        // I periodi si leggono una volta per richiesta (ProfiloEnte): dopo
        // una modifica la copia in memoria non vale più.
        static::saved(fn () => \App\Services\ProfiloEnte::dimentica());
        static::deleted(fn () => \App\Services\ProfiloEnte::dimentica());
    }

    protected function casts(): array
    {
        return [
            'dal' => 'date',
            'rasd_data_iscrizione' => 'date',
            'spunte' => 'array',
        ];
    }

    public function etichettaForma(): string
    {
        return self::FORME[$this->forma_giuridica][0] ?? (string) $this->forma_giuridica;
    }

    /** "Base Associativa Sportiva", "Associazione Sportiva Dilettantistica": sotto il nome nella carta intestata. */
    public function descrizioneForma(): ?string
    {
        return $this->forma_descrizione ?: (self::FORME[$this->forma_giuridica][1] ?? null);
    }

    public function etichettaRegime(): string
    {
        return self::REGIMI[$this->regime_fiscale][0] ?? (string) $this->regime_fiscale;
    }

    /** Forma sportiva dilettantistica (A.S.D. o S.S.D.): RASD e relativi moduli. */
    public function sportivaDilettantistica(): bool
    {
        return in_array($this->forma_giuridica, ['asd', 'ssd'], true);
    }

    public function iscrittoRasd(): bool
    {
        return $this->sportivaDilettantistica() && filled($this->rasd_numero) && $this->rasd_data_iscrizione !== null;
    }
}
