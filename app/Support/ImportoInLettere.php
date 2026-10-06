<?php

namespace App\Support;

/**
 * Importo in lettere per le ricevute: "trenta/00", "milleduecentoquarantasette/50".
 * Scritto da zero: quello del Gestionale APS arrotondava all'intero
 * (12,50 → "tredici") e sbagliava le elisioni (31 → "trentauno").
 */
class ImportoInLettere
{
    private const UNITA = ['zero', 'uno', 'due', 'tre', 'quattro', 'cinque', 'sei', 'sette', 'otto', 'nove', 'dieci',
        'undici', 'dodici', 'tredici', 'quattordici', 'quindici', 'sedici', 'diciassette', 'diciotto', 'diciannove'];

    private const DECINE = [2 => 'venti', 3 => 'trenta', 4 => 'quaranta', 5 => 'cinquanta', 6 => 'sessanta',
        7 => 'settanta', 8 => 'ottanta', 9 => 'novanta'];

    public static function euro(float|string $importo): string
    {
        $centesimi = (int) round(((float) $importo) * 100);
        $interi = intdiv($centesimi, 100);

        return self::numero($interi) . '/' . str_pad((string) ($centesimi % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function numero(int $n): string
    {
        if ($n < 0) {
            return 'meno ' . self::numero(-$n);
        }
        if ($n < 20) {
            return self::UNITA[$n];
        }
        if ($n < 100) {
            $d = self::DECINE[intdiv($n, 10)];
            $u = $n % 10;
            if ($u === 0) {
                return $d;
            }
            // Elisione davanti a "uno" e "otto": ventuno, trentotto
            if ($u === 1 || $u === 8) {
                $d = substr($d, 0, -1);
            }

            return $d . ($u === 3 ? 'tré' : self::UNITA[$u]);
        }
        if ($n < 1000) {
            $c = intdiv($n, 100);
            $resto = $n % 100;
            $cento = ($c === 1 ? '' : self::UNITA[$c]) . 'cento';
            // "centottanta": elisione davanti a "ottanta"
            if ($resto >= 80 && $resto < 90) {
                $cento = substr($cento, 0, -1);
            }

            return $cento . ($resto ? self::numero($resto) : '');
        }
        if ($n < 1000000) {
            $m = intdiv($n, 1000);
            $resto = $n % 1000;

            return ($m === 1 ? 'mille' : self::numero($m) . 'mila') . ($resto ? self::numero($resto) : '');
        }
        $mi = intdiv($n, 1000000);
        $resto = $n % 1000000;

        return ($mi === 1 ? 'unmilione' : self::numero($mi) . 'milioni') . ($resto ? self::numero($resto) : '');
    }
}
