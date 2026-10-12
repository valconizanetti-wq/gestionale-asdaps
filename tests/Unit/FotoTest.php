<?php

namespace Tests\Unit;

use App\Scontrini\Foto;
use PHPUnit\Framework\TestCase;

/**
 * Copia ridotta della foto di uno scontrino (v0.39.1, collaudo N2: sul
 * server imagescale() restituiva false e «Leggi lo scontrino» dava errore
 * 500). Foto grande del telefono con orientamento EXIF, PNG trasparente.
 */
class FotoTest extends TestCase
{
    private function dimensioni(string $jpeg): array
    {
        $info = getimagesizefromstring($jpeg);

        return [$info[0], $info[1], $info[2]];
    }

    public function test_foto_grande_ridotta_e_raddrizzata(): void
    {
        // 4000 × 3000 con orientamento 6 (telefono in verticale): esce 1200 × 1600
        $jpeg = Foto::riduci(__DIR__ . '/../Fixtures/scontrini/foto-grande-ruotata.jpg');
        $this->assertNotNull($jpeg);
        $this->assertSame([1200, 1600, IMAGETYPE_JPEG], $this->dimensioni($jpeg));
        $this->assertLessThan(Foto::LIMITE_OCR, strlen($jpeg));
        // La striscia blu in alto a sinistra, dopo la rotazione di 90° in senso orario, è in alto a destra
        $img = imagecreatefromstring($jpeg);
        $c = imagecolorsforindex($img, imagecolorat($img, 1190, 50));
        $this->assertGreaterThan(150, $c['blue']);
        $this->assertLessThan(100, $c['red']);
    }

    public function test_png_trasparente_su_fondo_bianco_e_miniatura(): void
    {
        $jpeg = Foto::riduci(__DIR__ . '/../Fixtures/scontrini/trasparente.png', 240, 70);
        $this->assertSame([240, 80, IMAGETYPE_JPEG], $this->dimensioni($jpeg));
        $c = imagecolorsforindex($img = imagecreatefromstring($jpeg), imagecolorat($img, 10, 10));
        $this->assertGreaterThan(245, $c['red'] + 0);
        $this->assertGreaterThan(245, $c['green'] + 0);
    }

    public function test_file_non_immagine(): void
    {
        $this->assertNull(Foto::riduci(__FILE__));
    }
}
