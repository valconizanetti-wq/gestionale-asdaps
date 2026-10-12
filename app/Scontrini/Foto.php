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
     * manca gd.
     */
    public static function riduci(string $file, int $lato = 1600, int $qualita = 75): ?string
    {
        if (! self::disponibile()) {
            return null;
        }
        $info = @getimagesize($file);
        if (! $info) {
            return null;
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file),
            IMAGETYPE_PNG => @imagecreatefrompng($file),
            default => false,
        };
        if (! $img) {
            return null;
        }
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientamento = (int) (@exif_read_data($file)['Orientation'] ?? 1);
            $ruotata = match ($orientamento) {
                3 => imagerotate($img, 180, 0),
                6 => imagerotate($img, -90, 0),
                8 => imagerotate($img, 90, 0),
                default => null,
            };
            if ($ruotata) {
                imagedestroy($img);
                $img = $ruotata;
            }
        }
        [$w, $h] = [imagesx($img), imagesy($img)];
        $f = min(1, $lato / max($w, $h));
        if ($f < 1) {
            $nuova = imagescale($img, max(1, (int) round($w * $f)), max(1, (int) round($h * $f)), IMG_BICUBIC);
            imagedestroy($img);
            $img = $nuova;
        }
        // PNG trasparente: sfondo bianco nel JPEG
        $fondo = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($fondo, 0, 0, imagecolorallocate($fondo, 255, 255, 255));
        imagecopy($fondo, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imagedestroy($img);
        ob_start();
        imagejpeg($fondo, null, $qualita);
        imagedestroy($fondo);

        return ob_get_clean() ?: null;
    }
}
