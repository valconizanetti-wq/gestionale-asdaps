<?php

namespace App\Services;

use App\Models\Domanda;
use App\Models\Impostazione;
use Illuminate\Support\Carbon;

/**
 * Quota associativa dei nuovi soci (statuto v1.0 artt. 5.4 e 6.6,
 * domande-iscrizione.md §3). Importi per categoria dalle impostazioni
 * (decisi dal CD); promozione di ottobre–dicembre (decisione di Paolo del
 * 29/09/2026): chi è accolto o versa da ottobre a dicembre diventa socio
 * subito e la quota vale fino al 31 dicembre dell'anno dopo.
 */
class Quota
{
    /** IBAN mostrato finché la scheda Associazione non ha quello vero. */
    public const IBAN_SEGNAPOSTO = 'IT XX X XXXXX XXXXX XXXXXXXXXXXX';

    /** Mese da cui vale la promozione (ottobre). */
    public const MESE_PROMOZIONE = 10;

    public static function importo(string $tipoSocio): float
    {
        return (float) ($tipoSocio === 'minorenne'
            ? Impostazione::get('quota_giovane', 30)
            : Impostazione::get('quota_ordinario', 70));
    }

    public static function promozioneAttiva(): bool
    {
        return (bool) Impostazione::get('quota_promo_ultimo_trimestre', true);
    }

    /** Siamo nel periodo della promozione (ottobre–dicembre)? */
    public static function periodoPromozione(Carbon $data): bool
    {
        return $data->month >= self::MESE_PROMOZIONE;
    }

    /** Anno sociale coperto da una quota versata in questa data. */
    public static function anno(Carbon $data, bool $promo): int
    {
        return $promo && self::periodoPromozione($data) ? $data->year + 1 : $data->year;
    }

    /** "Quota associativa 2026 – Rossi Elena" (cognome e nome di chi diventa socio). */
    public static function causale(Domanda $domanda): string
    {
        $anno = $domanda->quota_anno ?? now()->year;

        return "Quota associativa {$anno} – " . trim($domanda->cognome . ' ' . $domanda->nome);
    }
}
