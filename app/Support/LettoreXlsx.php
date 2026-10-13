<?php

namespace App\Support;

/**
 * Lettore minimo dei file .xlsx (v0.40.0, importazione dei soci): un foglio
 * per nome, i valori delle celle, niente formule né stili.
 *
 * Perché non PhpSpreadsheet: dalla sessione di sviluppo Packagist non è
 * raggiungibile, e sul server servirebbe un «composer install» in più. Un
 * .xlsx è uno ZIP di file XML (Office Open XML): bastano ZipArchive e
 * SimpleXML. ZipArchive richiede l'estensione PHP «zip», che non sempre è
 * attiva (in Laragon non lo era): deploy.md §6.
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

    /** File salvato con il sistema di date del 1904 (vecchio Excel per Mac). */
    private bool $date1904 = false;

    private bool $aperto = false;

    /** @throws \RuntimeException con un messaggio per l'utente */
    public function __construct(string $percorso)
    {
        // L'estensione zip di PHP non è sempre attiva (Laragon, 13/10/2026):
        // messaggio chiaro invece dell'errore 500.
        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException("Manca l'estensione PHP «zip», che serve per leggere i file Excel: va attivata nella configurazione di PHP (deploy.md §6).");
        }
        $this->zip = new \ZipArchive();
        if ($this->zip->open($percorso, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('Il file non è un foglio Excel (.xlsx) leggibile.');
        }
        $this->aperto = true;

        $workbook = $this->xml('xl/workbook.xml');
        $rels = $this->xml('xl/_rels/workbook.xml.rels');
        if (! $workbook || ! $rels) {
            throw new \RuntimeException('Il file non è un foglio Excel (.xlsx) leggibile.');
        }

        $pr = $workbook->workbookPr ?? null;
        $this->date1904 = $pr !== null && in_array((string) ($pr['date1904'] ?? ''), ['1', 'true'], true);

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

    /**
     * Chiude il file. Va chiamato appena letto: su Windows un file aperto non
     * si può cancellare (collaudo in Laragon, 13/10/2026: il file caricato
     * restava in tmp/soci-import).
     */
    public function chiudi(): void
    {
        if ($this->aperto) {
            $this->aperto = false;
            try {
                $this->zip->close();
            } catch (\Throwable) {
                // già chiuso
            }
        }
    }

    public function __destruct()
    {
        $this->chiudi();
    }

    /** Il file usa il sistema di date del 1904. */
    public function date1904(): bool
    {
        return $this->date1904;
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
        if (! $this->aperto) {
            throw new \RuntimeException('Il file è già stato chiuso.');
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
                if ($valore !== null && (! is_string($valore) || trim($valore) !== '')) {
                    $celle[$col] = $valore;
                }
            }
            if ($celle) {
                $righe[$n ?: count($righe) + 1] = $celle;
            }
        }

        return $righe;
    }

    /** Data di una cella numerica di questo file (sistema 1900 o 1904). */
    public function data(float $serie): ?\Illuminate\Support\Carbon
    {
        return self::dataExcel($this->date1904 ? $serie + 1462 : $serie);
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
            'd' => substr((string) $c->v, 0, 10),   // data ISO 8601 (raro)
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
