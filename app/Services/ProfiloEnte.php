<?php

namespace App\Services;

use App\Models\AssociazionePeriodo;
use App\Models\Impostazione;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Profilo dell'ente per periodi (profilo-ente.md): l'unico punto da cui
 * documenti, email, pagine e menu leggono denominazione, forma, sede,
 * regime fiscale e versioni dei testi in vigore a una data.
 *
 * Regole:
 * - vale il periodo con `dal` più recente non successivo alla data;
 * - prima del primo periodo si usa il primo (es. oggi, prima del
 *   01/01/2027, valgono i dati della B.A.S.);
 * - senza periodi si usano i valori di prima: config/sito.php per la
 *   denominazione e le impostazioni per le versioni dei testi.
 * I periodi si leggono una volta per richiesta (cache nel container).
 */
class ProfiloEnte
{
    private const CACHE = 'profilo-ente.periodi';

    /** @return Collection<int, AssociazionePeriodo> in ordine di data */
    public static function periodi(): Collection
    {
        if (! app()->bound(self::CACHE)) {
            app()->instance(self::CACHE, AssociazionePeriodo::orderBy('dal')->get());
        }

        return app(self::CACHE);
    }

    /** Da chiamare dopo ogni modifica dei periodi. */
    public static function dimentica(): void
    {
        app()->forgetInstance(self::CACHE);
    }

    /** Periodo in vigore alla data (oggi se manca); il primo se la data lo precede; un periodo provvisorio se non ce ne sono. */
    public static function al(\DateTimeInterface|string|null $data = null): AssociazionePeriodo
    {
        $periodi = self::periodi();
        if ($periodi->isEmpty()) {
            return self::predefinito();
        }

        $giorno = Carbon::parse($data ?? now())->startOfDay();

        return $periodi->filter(fn (AssociazionePeriodo $p) => $p->dal->lte($giorno))->last() ?? $periodi->first();
    }

    /** Periodo davvero in vigore oggi, o null se il primo comincia dopo (avviso nella pagina Impostazioni). */
    public static function inVigoreOggi(): ?AssociazionePeriodo
    {
        $oggi = today();

        return self::periodi()->filter(fn (AssociazionePeriodo $p) => $p->dal->lte($oggi))->last();
    }

    /** Ultimo giorno di un periodo: il giorno prima del successivo, o null. */
    public static function fine(AssociazionePeriodo $periodo): ?Carbon
    {
        $dopo = self::periodi()->first(fn (AssociazionePeriodo $p) => $p->dal->gt($periodo->dal));

        return $dopo?->dal->copy()->subDay();
    }

    public static function denominazione(\DateTimeInterface|string|null $data = null): string
    {
        return self::al($data)->denominazione;
    }

    /** Riga di piè di pagina: «Denominazione · Affiliata CSEN» (senza affiliazione solo il nome). */
    public static function firma(\DateTimeInterface|string|null $data = null): string
    {
        $periodo = self::al($data);

        return $periodo->denominazione . (filled($periodo->ente_affiliazione) ? ' · Affiliata ' . $periodo->ente_affiliazione : '');
    }

    public static function descrizioneForma(\DateTimeInterface|string|null $data = null): ?string
    {
        return self::al($data)->descrizioneForma();
    }

    public static function sede(\DateTimeInterface|string|null $data = null): string
    {
        return self::al($data)->sede_legale;
    }

    /** Versione di statuto o informativa in vigore alla data. */
    public static function versione(string $tipo, \DateTimeInterface|string|null $data = null): string
    {
        $periodo = self::al($data);

        return (string) ($tipo === 'statuto' ? $periodo->statuto_versione : $periodo->informativa_versione);
    }

    /**
     * Moduli che dipendono dalla forma o dal regime (voci del menu, controlli):
     * 'rasd' (A.S.D./S.S.D.), 'l398' (opzione L. 398/1991), 'runts' (A.P.S.).
     */
    public static function haModulo(string $codice, \DateTimeInterface|string|null $data = null): bool
    {
        $periodo = self::al($data);

        return match ($codice) {
            'rasd' => $periodo->sportivaDilettantistica(),
            'l398' => $periodo->regime_fiscale === 'l398',
            'runts' => $periodo->forma_giuridica === 'aps',
            default => true,
        };
    }

    /**
     * Lista di controllo per un periodo che non è ancora in vigore. Ogni voce:
     * codice, titolo, descrizione, norma, stato ('ok' | 'ko' | 'manuale'),
     * fatta (bool), link (rotta suggerita o null). Le voci che non riguardano
     * la forma del periodo non compaiono.
     *
     * @return list<array<string, mixed>>
     */
    public static function listaPassaggio(AssociazionePeriodo $p): array
    {
        $voci = [];

        $statuto = Documenti::esiste('statuto', $p->statuto_versione);
        $voci[] = [
            'codice' => 'statuto', 'titolo' => 'Statuto della nuova forma pubblicato',
            'descrizione' => $statuto
                ? "La versione {$p->statuto_versione} è pubblicata: è quella che leggono le domande online del periodo."
                : "La versione {$p->statuto_versione} indicata nel periodo non è pubblicata in resources/content/statuto: le domande online la devono poter leggere.",
            'norma' => null, 'stato' => $statuto ? 'ok' : 'ko', 'fatta' => $statuto, 'link' => 'modifica',
        ];

        $informativa = self::informativaNomina($p);
        $voci[] = [
            'codice' => 'informativa', 'titolo' => 'Informativa con il titolare del periodo',
            'descrizione' => $informativa
                ? "L'informativa {$p->informativa_versione} nomina «{$p->denominazione}»."
                : "L'informativa {$p->informativa_versione} non è pubblicata o non nomina «{$p->denominazione}»: serve una versione con il nuovo titolare.",
            'norma' => 'Reg. (UE) 2016/679, art. 13', 'stato' => $informativa ? 'ok' : 'ko', 'fatta' => $informativa, 'link' => 'modifica',
        ];

        if ($p->sportivaDilettantistica()) {
            $rasd = $p->iscrittoRasd();
            $voci[] = [
                'codice' => 'rasd', 'titolo' => 'Iscrizione al RASD',
                'descrizione' => $rasd
                    ? "N. {$p->rasd_numero} del {$p->rasd_data_iscrizione->format('d/m/Y')}."
                    : 'Numero e data mancanti nel periodo. Senza iscrizione la dicitura «sportiva dilettantistica» non si usa.',
                'norma' => 'D.Lgs. 39/2021', 'stato' => $rasd ? 'ok' : 'ko', 'fatta' => $rasd, 'link' => 'modifica',
            ];
        }

        if ($p->regime_fiscale === 'l398') {
            $soglie = Impostazione::get('regime_398_plafond_soglia') !== null;
            $voci[] = [
                'codice' => 'regime', 'titolo' => 'Regime fiscale',
                'descrizione' => $soglie
                    ? 'Opzione L. 398/1991: soglie del plafond e della tracciabilità presenti nelle impostazioni.'
                    : 'Opzione L. 398/1991: mancano le soglie del plafond nelle impostazioni.',
                'norma' => 'L. 398/1991', 'stato' => $soglie ? 'ok' : 'ko', 'fatta' => $soglie, 'link' => null,
            ];
        }

        $affiliazione = filled($p->ente_affiliazione);
        $voci[] = [
            'codice' => 'affiliazione', 'titolo' => 'Ente di affiliazione',
            'descrizione' => $affiliazione ? "{$p->ente_affiliazione} indicato nel periodo." : "Nessun ente di affiliazione indicato nel periodo.",
            'norma' => null, 'stato' => $affiliazione ? 'ok' : 'ko', 'fatta' => $affiliazione, 'link' => 'modifica',
        ];

        foreach (AssociazionePeriodo::SPUNTE_MANUALI as $codice => [$titolo, $descrizione]) {
            $fatta = isset(($p->spunte ?? [])[$codice]);
            $voci[] = [
                'codice' => $codice, 'titolo' => $titolo, 'descrizione' => $descrizione,
                'norma' => null, 'stato' => 'manuale', 'fatta' => $fatta, 'link' => null,
            ];
        }

        return $voci;
    }

    /** L'informativa del periodo è pubblicata e nomina la denominazione del periodo. */
    private static function informativaNomina(AssociazionePeriodo $p): bool
    {
        if (! Documenti::esiste('informativa', $p->informativa_versione)) {
            return false;
        }

        return str_contains((string) file_get_contents(Documenti::percorso('informativa', $p->informativa_versione)), $p->denominazione);
    }

    /** Senza periodi: i valori di prima (config/sito.php, impostazioni). Non salvato. */
    private static function predefinito(): AssociazionePeriodo
    {
        $p = new AssociazionePeriodo();
        $p->forceFill([
            'dal' => '2000-01-01',
            'denominazione' => (string) config('sito.piede.intestazione'),
            'forma_giuridica' => 'associazione',
            'sede_legale' => (string) config('sito.indirizzo.ufficiale'),
            'regime_fiscale' => 'non_commerciale',
            'statuto_versione' => (string) Impostazione::get('statuto_versione_corrente', '1.0'),
            'informativa_versione' => (string) Impostazione::get('privacy_versione_corrente', '1.0'),
        ]);

        return $p;
    }
}
