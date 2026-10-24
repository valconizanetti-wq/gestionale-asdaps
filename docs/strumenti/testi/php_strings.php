<?php
// Estrae le stringhe dai file PHP: [file, riga, raw, contesto]
$out = [];
foreach (array_slice($argv, 1) as $file) {
    $tok = token_get_all(file_get_contents($file));
    $n = count($tok);
    $ctx = function ($i) use ($tok) {
        // ultimi token significativi prima di $i
        $parts = [];
        for ($j = $i - 1; $j >= 0 && count($parts) < 8; $j--) {
            $t = $tok[$j];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT])) continue;
            $parts[] = is_array($t) ? $t[1] : $t;
        }
        return implode(' ', array_reverse($parts));
    };
    for ($i = 0; $i < $n; $i++) {
        $t = $tok[$i];
        if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING) {
            $out[] = [$file, $t[2], $t[1], $ctx($i)];
        } elseif ($t === '"') {
            // stringa con variabili: fino al prossimo "
            $raw = '"'; $line = null; $j = $i + 1;
            for ($k = $i - 1; $k >= 0; $k--) { if (is_array($tok[$k])) { $line = $tok[$k][2] + substr_count($tok[$k][1], "\n"); break; } }
            while ($j < $n && $tok[$j] !== '"') { $raw .= is_array($tok[$j]) ? $tok[$j][1] : $tok[$j]; if ($line === null && is_array($tok[$j])) $line = $tok[$j][2]; $j++; }
            $raw .= '"';
            // riga: quella del primo token interno
            for ($k = $i + 1; $k < $j; $k++) { if (is_array($tok[$k])) { $line = $tok[$k][2]; break; } }
            $out[] = [$file, $line, $raw, $ctx($i)];
            $i = $j;
        }
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
