<?php

namespace App\Scontrini;

/**
 * Copie ridotte della foto di uno scontrino (analisi-scontrini.md §2, §12):
 * - per la lettura: lato lungo 1600 px, JPEG qualità 75, raddrizzata con
 *   l'orientamento EXIF (sotto il limite di 1 MB del piano gratuito di
 *   OCR.space; è anche l'anteprima della scheda);
 * - miniatura per l'elenco: lato lungo 240 px.
 * L'originale si archivia com'è. Senza l'estensione gd di PHP non si
 * riduce nulla: si manda l'originale se è sotto 1 MB.
 */
class Foto
{
    public const LIMITE_OCR = 1024 * 1024;

    /** HEIC/HEIF dall'iPhone: si riconosce dai primi byte, anche col nome .jpg. */
    public static function heic(string $file): bool
    {
        $testa = (string) @file_get_contents($file, false, null, 0, 32);

        return (bool) preg_match('/ftyp(heic|heix|hevc|hevx|mif1|msf1|heif)/', $testa);
    }

    public static function disponibile(): bool
    {
        return function_exists('imagecreatefromjpeg') && function_exists('imagejpeg');
    }

    /**
     * Copia JPEG ridotta e raddrizzata; null se l'immagine non si apre o
     * manca gd (allora si usa l'originale).
     *
     * v0.39.1 (collaudo N2, 12/10/2026): sul server imagescale() restituiva
     * false e la pagina andava in errore 500. Ora la riduzione usa
     * imagecopyresampled (presente in ogni versione di GD), ogni passo è
     * controllato e la rotazione si fa sulla copia già ridotta: una foto da
     * 12 megapixel ruotata a piena grandezza occupava due volte la memoria.
     */
    public static function riduci(string $file, int $lato = 1600, int $qualita = 75): ?string
    {
        if (! self::disponibile()) {
            return null;
        }
        $info = @getimagesize($file);
        if (! $info || $info[0] < 1 || $info[1] < 1) {
            return null;
        }
        self::memoria($info[0] * $info[1]);
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            default => false,
        };
        if (! $img) {
            return null;
        }
        $orientamento = $info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')
            ? (int) (@exif_read_data($file)['Orientation'] ?? 1) : 1;

        // Riduzione e sfondo bianco (PNG trasparente) in un solo passaggio
        [$w, $h] = [imagesx($img), imagesy($img)];
        $f = min(1, $lato / max($w, $h, 1));
        [$nw, $nh] = [max(1, (int) round($w * $f)), max(1, (int) round($h * $f))];
        $fondo = @imagecreatetruecolor($nw, $nh);
        if (! $fondo) {
            imagedestroy($img);

            return null;
        }
        imagefill($fondo, 0, 0, imagecolorallocate($fondo, 255, 255, 255));
        $ok = @imagecopyresampled($fondo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        if (! $ok) {
            imagedestroy($fondo);

            return null;
        }

        // Raddrizzata con l'orientamento EXIF, sulla copia piccola
        $ruotata = match ($orientamento) {
            3 => @imagerotate($fondo, 180, 0),
            6 => @imagerotate($fondo, -90, 0),
            8 => @imagerotate($fondo, 90, 0),
            default => null,
        };
        if ($ruotata) {
            imagedestroy($fondo);
            $fondo = $ruotata;
        }

        ob_start();
        $scritta = @imagejpeg($fondo, null, $qualita);
        $jpeg = ob_get_clean();
        imagedestroy($fondo);

        return $scritta && $jpeg ? $jpeg : null;
    }

    /**
     * Memoria per aprire la foto: GD tiene circa 5 byte per pixel, una foto
     * da 12 megapixel ne vuole una sessantina di MB oltre al resto. Se il
     * limite di PHP è più basso, lo si alza per questa richiesta (fino a
     * 512 MB); altrimenti un errore fatale non lascerebbe usare l'originale.
     */
    private static function memoria(int $pixel): void
    {
        $serve = (int) ($pixel * 5 * 1.5) + memory_get_usage() + 32 * 1024 * 1024;
        $limite = self::byte((string) ini_get('memory_limit'));
        if ($limite > 0 && $limite < $serve) {
            @ini_set('memory_limit', (string) min(max($serve, $limite), 512 * 1024 * 1024));
        }
    }

    private static function byte(string $v): int
    {
        $v = trim($v);
        if ($v === '' || $v === '-1') {
            return -1;
        }
        $n = (int) $v;

        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
