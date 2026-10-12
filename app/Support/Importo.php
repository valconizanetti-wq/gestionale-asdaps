<?php

namespace App\Support;

/**
 * Importi in euro scritti all'italiana (prima-nota.md §4).
 *
 * Il campo importo è un campo di testo (inputmode="decimal"), non
 * type="number": in gestionale-acv con type="number" un "23" digitato
 * senza selezionare il valore precedente si è salvato come 22,96.
 * La lettura segue la stessa euristica di gestionale-acv
 * (gestionale_valida_importo, bug reale 1.7.1: "1.450" letto come 1,45):
 * - punto e virgola insieme: punto delle migliaia, virgola dei decimali;
 * - solo virgola: virgola dei decimali;
 * - solo punto con esattamente 3 cifre dopo: migliaia ("1.450" = 1450);
 * - altrimenti: formato standard ("12.50", "1250").
 */
class Importo
{
    /** Stringa normalizzata con due decimali ("1234.50"), o null se non valida. */
    public static function leggi(mixed $grezzo): ?string
    {
        $s = trim(str_replace(["\u{00A0}", ' ', '€'], '', (string) $grezzo));
        if ($s === '') {
            return null;
        }

        if (str_contains($s, ',') && str_contains($s, '.')) {
            if (! preg_match('/^\d{1,3}(\.\d{3})+,\d{1,2}$/', $s)) {
                return null;
            }
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        if (! preg_match('/^\d{1,8}(\.\d{1,2})?$/', $s)) {
            return null;
        }

        return number_format((float) $s, 2, '.', '');
    }

    /**
     * Centesimi interi da un importo normalizzato ("12.50", "-4.50"): per
     * somme e confronti esatti senza bcmath (non c'è su ogni server).
     */
    public static function centesimi(mixed $importo): int
    {
        $t = trim((string) $importo);
        $negativo = str_starts_with($t, '-');
        [$interi, $decimali] = array_pad(explode('.', ltrim($t, '-+')), 2, '0');
        $c = (int) $interi * 100 + (int) str_pad(substr($decimali, 0, 2), 2, '0');

        return $negativo ? -$c : $c;
    }

    /** Da centesimi a importo normalizzato ("-4.50"). */
    public static function daCentesimi(int $c): string
    {
        return ($c < 0 ? '-' : '') . intdiv(abs($c), 100) . '.' . str_pad((string) (abs($c) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** "1.234,50" per le viste e i PDF. */
    public static function it(mixed $valore, bool $euro = false): string
    {
        $t = number_format((float) $valore, 2, ',', '.');

        return $euro ? $t . ' €' : $t;
    }

    /** Regola di validazione: importo leggibile e almeno 0,01. */
    public static function regola(): \Closure
    {
        return function (string $attributo, mixed $valore, \Closure $fail) {
            $letto = self::leggi($valore);
            if ($letto === null || (float) $letto < 0.01) {
                $fail("L'importo non è valido: scrivilo come 12,50 o 1.450,00.");
            }
        };
    }
}
