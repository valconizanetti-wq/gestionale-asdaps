<?php

namespace Tests\Unit;

use App\Scontrini\LettoreScontrino;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Lettura dei 14 scontrini veri mandati da Paolo il 12/10/2026
 * (analisi-scontrini.md §11): testo trascritto dalle foto in
 * tests/Fixtures/scontrini, come lo restituirebbe un OCR pulito.
 */
class LettoreScontrinoTest extends TestCase
{
    public static function scontrini(): array
    {
        return [
            'bar' => ['01', '9.00', '2026-09-08', '01622870556', 'MB BAR TIRACORRENDO', 'cassa', 2],
            'eurospin senza giorno' => ['02', '26.48', null, '04766841003', 'EURO Eurospin', 'banca', 14],
            'eurospin con sconto' => ['03', '27.43', '2026-10-05', '04766841003', 'Eurospin Lazio S.p.A', 'banca', 11],
            'ferramenta' => ['04', '14.50', '2026-10-03', '01452300567', 'FERRAMENTA BANELLA FRANCO', 'banca', 2],
            'vivaio a peso' => ['05', '9.00', '2026-09-28', '01440890554', 'GEAFLOR S.N.C', 'cassa', 2],
            'foto di uno schermo, 2020' => ['06', '22.20', '2020-06-06', '12999500155', 'IL FORNAIO', 'cassa', 1],
            'copisteria non soggetta' => ['07', '10.00', '2026-09-03', '01539950558', 'IN BELLA COPIA', 'cassa', 1],
            'lidl con resto' => ['08', '4.41', '2026-09-18', '02275030233', 'Lidl Italia S.r.l', 'cassa', 2],
            'telefonia' => ['09', '10.00', '2026-09-04', '13122851002', 'RASEL TELEFONIA ELETTRONICA', 'cassa', 1],
            'articoli casa' => ['10', '14.10', '2026-09-12', '01625930555', 'SOTTOSOPRA S.R.L. S', 'cassa', 4],
            'tabaccheria' => ['11', '3.00', '2026-09-07', '00460930555', 'TABACCHERIA CHIASSO ROBERTO', 'cassa', 1],
            'arceria' => ['12', '9.00', '2026-09-25', '01930680549', 'TOXON SPORT', 'cassa', 1],
            'arceria a righe' => ['13', '9.20', '2026-09-17', '01930680549', 'TOXON SPORT', 'cassa', 3],
            'legno' => ['14', '31.22', '2026-09-01', '01689730552', 'TUTTO LEGNO PACIFICI SRLS', 'banca', 3],
        ];
    }

    #[DataProvider('scontrini')]
    public function test_legge_lo_scontrino(string $file, string $totale, ?string $data, string $piva, string $fornitore, string $conto, int $righe): void
    {
        $r = (new LettoreScontrino())->leggi(file_get_contents(__DIR__ . "/../Fixtures/scontrini/s{$file}.txt"), new \DateTimeImmutable('2026-10-12'));

        $this->assertSame($totale, $r['totale']);
        $this->assertTrue($r['totale_sicuro']);
        $this->assertSame($data, $r['data']);
        $this->assertSame($piva, $r['partita_iva']);
        $this->assertTrue($r['partita_iva_valida']);
        $this->assertStringStartsWith($fornitore, (string) $r['fornitore']);
        $this->assertSame($conto, $r['conto']);
        $this->assertCount($righe, $r['righe']);
        $this->assertSame($totale, $r['righe_somma']);
    }

    public function test_casi_particolari(): void
    {
        $l = new LettoreScontrino();
        $oggi = new \DateTimeImmutable('2026-10-12');
        // Lidl: il contante dato (5,41) non è il totale, c'è il resto
        $this->assertSame('4.41', $l->leggi(file_get_contents(__DIR__ . '/../Fixtures/scontrini/s08.txt'), $oggi)['totale']);
        // Scontrino del 2020: avviso sulla data
        $this->assertStringContainsString('più di un anno', (string) $l->leggi(file_get_contents(__DIR__ . '/../Fixtures/scontrini/s06.txt'), $oggi)['data_avviso']);
        // Giorno con la prima cifra tagliata: avviso
        $this->assertNotNull($l->leggi(file_get_contents(__DIR__ . '/../Fixtures/scontrini/s03.txt'), $oggi)['data_avviso']);
        // Riga di sconto negativa e quantità attaccata alla riga
        $eurospin = $l->leggi(file_get_contents(__DIR__ . '/../Fixtures/scontrini/s02.txt'), $oggi);
        $this->assertSame('-0.99', end($eurospin['righe'])['importo']);
        $toxon = $l->leggi(file_get_contents(__DIR__ . '/../Fixtures/scontrini/s13.txt'), $oggi);
        $this->assertSame('2 X 2,20', $toxon['righe'][1]['dettaglio']);
        // Importi
        $this->assertSame('9.00', LettoreScontrino::importoFinale('TOTALE COMPLESSIVO 9.0'));
        $this->assertSame('1234.50', LettoreScontrino::importoFinale('TOTALE 1.234,50'));
        // Partita IVA: cifra di controllo; letta male → avviso
        $this->assertTrue(LettoreScontrino::partitaIvaValida('01689730552'));
        $this->assertFalse(LettoreScontrino::partitaIvaValida('01689730553'));
        $male = $l->leggi("NEGOZIO SRL\nP.IVA 01689730553\nTOTALE COMPLESSIVO 3,00\nIMPORTO PAGATO 3,00", $oggi);
        $this->assertFalse($male['partita_iva_valida']);
        $this->assertNotEmpty($male['avvisi']);
        // Niente totale riconoscibile: nessuna proposta
        $this->assertNull($l->leggi("foto sfocata\n???", $oggi)['totale']);
    }
}
