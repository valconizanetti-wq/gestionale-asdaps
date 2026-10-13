<?php

namespace App\Support;

/**
 * Lettore minimo dei file .xlsx (v0.40.0, importazione dei soci): un foglio
 * per nome, i valori delle celle, niente formule né stili.
 *
 * Perché non PhpSpreadsheet: dalla sessione di sviluppo Packagist non è
 * raggiungibile, e sul server servirebbe un «composer install» in più. Un
 * .xlsx è uno ZIP di file XML (Office Open XML): bastano ZipArchive e
 * SimpleXML, già usati da Laravel e DomPDF. Analisi-importazione-soci.md §2.
 *
 * Sicurezza:
 * - dimensione massima di ogni parte decompressa (difesa dagli «ZIP bomb»);
 * - XML letto senza rete (LIBXML_NONET) e senza entità esterne (PHP 8 non le
 *   carica di suo);
 * - nessun percorso del file ZIP usato sul disco.
 *
 * Valori: testo (stringhe condivise, testo in linea, formula testo) come
 * stringa; numeri come float (anche le date, che in Excel sono numeri di
 * serie: le converte chi legge, con dataExcel()); vero/falso come bool;
 * celle vuote assenti.
 */
class LettoreXlsx
{
    /** Massimo per ogni parte XML decompressa. */
    public const MAX_PARTE = 20 * 1024 * 1024;

    private \ZipArchive $zip;

    /** @var list<string> */
    private array $condivise = [];

    /** @var array<string, string> nome del foglio => percorso nello ZIP */
    private array $fogli = [];

    /** @throws \RuntimeException con un messaggio per l'utente */
    public function __construct(string $percorso)
    {
        $this->zip = new \ZipArchive();
        if ($this->zip->open($percorso, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Il file non è un foglio Excel (.xlsx) leggibile.');
        }

        $workbook = $this->xml('xl/workbook.xml');
        $rels = $this->xml('xl/_rels/workbook.xml.rels');
        if (! $workbook || ! $rels) {
            throw new \RuntimeException('Il file non è un foglio Excel (.xlsx) leggibile.');
        }

        $target = [];
        foreach ($rels->children() as $rel) {
            $t = (string) $rel['Target'];
            $t = str_starts_with($t, '/') ? ltrim($t, '/') : 'xl/' . $t;
            $target[(string) $rel['Id']] = $t;
        }
        $ns = $workbook->getNamespaces(true);
        foreach ($workbook->sheets->sheet ?? [] as $sheet) {
            $rid = (string) $sheet->attributes($ns['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($target[$rid])) {
                $this->fogli[(string) $sheet['name']] = $target[$rid];
            }
        }

        if ($ss = $this->xml('xl/sharedStrings.xml')) {
            foreach ($ss->si as $si) {
                $this->condivise[] = $this->testo($si);
            }
        }
    }

    public function __destruct()
    {
        try {
            $this->zip->close();
        } catch (\Throwable) {
            // già chiuso
        }
    }

    /** @return list<string> */
    public function nomiFogli(): array
    {
        return array_keys($this->fogli);
    }

    /**
     * Righe del foglio: numero di riga di Excel => [indice colonna da 0 => valore].
     * Le righe senza celle piene non ci sono.
     *
     * @return array<int, array<int, string|float|bool>>
     * @throws \RuntimeException
     */
    public function righe(string $foglio): array
    {
        if (! isset($this->fogli[$foglio])) {
            throw new \RuntimeException("Manca il foglio «{$foglio}».");
        }
        $xml = $this->xml($this->fogli[$foglio]);
        if (! $xml) {
            throw new \RuntimeException("Il foglio «{$foglio}» non si legge.");
        }

        $righe = [];
        foreach ($xml->sheetData->row ?? [] as $row) {
            $n = (int) $row['r'];
            $celle = [];
            $pos = 0;
            foreach ($row->c as $c) {
                $col = isset($c['r']) ? self::colonna((string) $c['r']) : $pos;
                $pos = $col + 1;
                $valore = $this->valore($c);
                if ($valore !== null && $valore !== '') {
                    $celle[$col] = $valore;
                }
            }
            if ($celle) {
                $righe[$n ?: count($righe) + 1] = $celle;
            }
        }

        return $righe;
    }

    /** Numero di serie di Excel (sistema 1900) → data, o null se fuori misura. */
    public static function dataExcel(float $serie): ?\Illuminate\Support\Carbon
    {
        if ($serie < 1 || $serie > 2958465) {
            return null;
        }
        // 25569 = 01/01/1970; Excel conta per errore il 29/02/1900, già
        // compreso in questo scarto per tutte le date dal 01/03/1900.
        return \Illuminate\Support\Carbon::createFromTimestampUTC((int) round(((int) floor($serie) - 25569) * 86400))->startOfDay();
    }

    /** «C12» → 2 (A = 0). */
    public static function colonna(string $riferimento): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($riferimento), $m);
        $n = 0;
        foreach (str_split($m[1] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private function valore(\SimpleXMLElement $c): string|float|bool|null
    {
        $tipo = (string) $c['t'];

        return match ($tipo) {
            's' => $this->condivise[(int) $c->v] ?? null,
            'inlineStr' => $this->testo($c->is),
            'str' => (string) $c->v,
            'b' => (string) $c->v === '1',
            'e' => null,
            default => isset($c->v) && is_numeric((string) $c->v) ? (float) (string) $c->v : (isset($c->v) ? (string) $c->v : null),
        };
    }

    /** Testo di un elemento con <t> diretto o «rich text» (<r><t>…). */
    private function testo(?\SimpleXMLElement $el): string
    {
        if (! $el) {
            return '';
        }
        if (isset($el->t)) {
            return (string) $el->t;
        }
        $s = '';
        foreach ($el->r ?? [] as $r) {
            $s .= (string) $r->t;
        }

        return $s;
    }

    private function xml(string $nome): ?\SimpleXMLElement
    {
        $stat = $this->zip->statName($nome);
        if ($stat === false) {
            return null;
        }
        if ($stat['size'] > self::MAX_PARTE) {
            throw new \RuntimeException('Il file è troppo grande per un elenco di soci.');
        }
        $dati = $this->zip->getFromName($nome);
        if ($dati === false) {
            return null;
        }
        $xml = simplexml_load_string($dati, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

        return $xml === false ? null : $xml;
    }
}
