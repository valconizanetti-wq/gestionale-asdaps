<?php
/*
 * Prova di OCR.space sugli scontrini (Gestionale ASD, analisi-scontrini.md §11).
 *
 * Come si usa (Cmder, Laragon):
 *   1. cartella C:\laragon\www\prova-ocr con dentro questo file e una
 *      sottocartella "foto" con le foto originali degli scontrini
 *      (JPG, PNG o PDF; il nome del file può dire fornitore e totale);
 *   2. un file "chiave.txt" nella stessa cartella con la sola chiave di
 *      OCR.space (non mandarla in chat);
 *   2b. se esce "SSL certificate problem": scaricare https://curl.se/ca/cacert.pem
 *      e metterlo nella stessa cartella (lo script lo usa da solo);
 *   3. cd C:\laragon\www\prova-ocr
 *      php prova-ocr.php
 *   4. manda a Claude il file "risultati-ocr.json" che viene creato
 *      (contiene solo il testo letto, non la chiave né le foto).
 *
 * Per ogni foto fa quello che farà il gestionale: copia ridotta (lato lungo
 * 1600 px, JPEG qualità 75, raddrizzata con i dati EXIF) sotto 1 MB, poi due
 * letture con il motore 2 in italiano: righe come tabella (isTable) e testo
 * semplice. Circa 2 richieste per foto (la chiave gratuita ne ha 25.000 al
 * mese e 500 al giorno).
 */

$dir = __DIR__;
$chiave = trim((string) @file_get_contents("$dir/chiave.txt"));
if ($chiave === '') {
    exit("Manca chiave.txt con la chiave di OCR.space.\n");
}
if (! function_exists('curl_init') || ! function_exists('imagecreatefromjpeg')) {
    exit("Servono le estensioni curl e gd di PHP (Laragon: Menu > PHP > Extensions).\n");
}
$foto = glob("$dir/foto/*.{jpg,jpeg,JPG,JPEG,png,PNG,pdf,PDF}", GLOB_BRACE);
if (! $foto) {
    exit("Nessuna foto in $dir\\foto\n");
}

/** Copia ridotta per l'OCR: lato lungo 1600 px, JPEG 75, orientamento EXIF. */
function ridotta(string $file): array
{
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        return [file_get_contents($file), 'application/pdf'];
    }
    $img = $ext === 'png' ? imagecreatefrompng($file) : imagecreatefromjpeg($file);
    if ($ext !== 'png' && function_exists('exif_read_data')) {
        $o = @exif_read_data($file)['Orientation'] ?? 1;
        $img = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
    }
    [$w, $h] = [imagesx($img), imagesy($img)];
    $f = min(1, 1600 / max($w, $h));
    if ($f < 1) {
        $img = imagescale($img, (int) round($w * $f), (int) round($h * $f), IMG_BICUBIC);
    }
    ob_start();
    imagejpeg($img, null, 75);

    return [ob_get_clean(), 'image/jpeg'];
}

/*
 * Certificati delle autorità (PHP su Windows spesso non li trova: errore
 * "SSL certificate problem: unable to get local issuer certificate").
 * Si usa il primo che esiste: cacert.pem in questa cartella (scaricato da
 * https://curl.se/ca/cacert.pem), quello di Laragon, quello di php.ini.
 * La verifica del certificato resta sempre accesa.
 */
function certificati(): ?string
{
    foreach ([__DIR__ . '/cacert.pem', 'C:/laragon/etc/ssl/cacert.pem', ini_get('curl.cainfo'), ini_get('openssl.cafile')] as $f) {
        if ($f && is_file($f)) {
            return $f;
        }
    }

    return null;
}

function leggi(string $dati, string $tipo, string $chiave, bool $tabella): array
{
    $t0 = microtime(true);
    $ch = curl_init('https://api.ocr.space/parse/image');
    if ($ca = certificati()) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['apikey: ' . $chiave],
        CURLOPT_POSTFIELDS => [
            'base64Image' => "data:$tipo;base64," . base64_encode($dati),
            'language' => 'ita',
            'OCREngine' => '2',
            'isTable' => $tabella ? 'true' : 'false',
            'detectOrientation' => 'true',
            'scale' => 'true',
        ],
    ]);
    $r = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    $j = $r ? json_decode($r, true) : null;

    return [
        'ms' => (int) ((microtime(true) - $t0) * 1000),
        'errore' => $err ?: ($j['IsErroredOnProcessing'] ?? false ? implode(' ', (array) ($j['ErrorMessage'] ?? [])) : null),
        'exit' => $j['OCRExitCode'] ?? null,
        'testo' => implode("\n", array_map(fn ($p) => $p['ParsedText'] ?? '', $j['ParsedResults'] ?? [])),
    ];
}

echo 'Certificati: ' . (certificati() ?? 'nessuno trovato (metti cacert.pem in questa cartella)') . "\n\n";

$out = [];
foreach ($foto as $f) {
    $nome = basename($f);
    [$dati, $tipo] = ridotta($f);
    echo str_pad($nome, 50) . ' ' . round(filesize($f) / 1024) . ' KB -> ' . round(strlen($dati) / 1024) . ' KB';
    $out[$nome] = [
        'originale_kb' => (int) round(filesize($f) / 1024),
        'ridotta_kb' => (int) round(strlen($dati) / 1024),
        'tabella' => leggi($dati, $tipo, $chiave, true),
        'semplice' => leggi($dati, $tipo, $chiave, false),
    ];
    $e = $out[$nome]['tabella']['errore'] ?? null;
    echo $e ? "  ERRORE: $e\n" : '  ok (' . $out[$nome]['tabella']['ms'] . " ms)\n";
    usleep(500000);
}
file_put_contents("$dir/risultati-ocr.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
echo "\nFatto: risultati-ocr.json (" . count($out) . " foto). Mandalo a Claude.\n";
