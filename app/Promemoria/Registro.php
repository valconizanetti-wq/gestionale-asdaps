<?php

namespace App\Promemoria;

/** Funzioni con promemoria (promemoria.md §2). */
class Registro
{
    public const FUNZIONI = [
        'quote_non_versate' => Funzioni\QuoteNonVersate::class,
        // «N giorni prima», a Presidente e Tesoriere (v0.38.0, tavola PR-3)
        'scadenze_associazione' => Funzioni\ScadenzeAssociazione::class,
        'tessere_in_scadenza' => Funzioni\TessereInScadenza::class,
    ];

    public static function trova(?string $chiave): ?Funzione
    {
        $classe = self::FUNZIONI[$chiave] ?? null;

        return $classe ? app($classe) : null;
    }

    /** @return array<string, Funzione> */
    public static function tutte(): array
    {
        return array_map(fn ($c) => app($c), self::FUNZIONI);
    }
}
