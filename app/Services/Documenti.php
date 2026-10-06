<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Testi versionati mostrati al pubblico e accettati nelle domande:
 * informativa privacy e statuto. Ogni versione è un file Markdown in
 * resources/content/<tipo>/<versione>.md, che non si modifica più dopo la
 * pubblicazione: una correzione è una versione nuova. Lo SHA-256 del file,
 * salvato con la domanda, prova quale testo esatto è stato accettato.
 */
class Documenti
{
    public const TIPI = ['informativa', 'statuto'];

    public static function percorso(string $tipo, string $versione): string
    {
        if (! in_array($tipo, self::TIPI, true) || ! preg_match('/^\d+\.\d+$/', $versione)) {
            throw new \InvalidArgumentException("Documento non previsto: {$tipo} {$versione}");
        }

        return resource_path("content/{$tipo}/{$versione}.md");
    }

    public static function esiste(string $tipo, string $versione): bool
    {
        return is_file(self::percorso($tipo, $versione));
    }

    public static function hash(string $tipo, string $versione): ?string
    {
        $file = self::percorso($tipo, $versione);

        return is_file($file) ? hash_file('sha256', $file) : null;
    }

    /**
     * HTML del documento. Il Markdown è scritto da noi e versionato nel
     * repository, ma l'HTML grezzo si disattiva comunque: niente <script>
     * o attributi da un file modificato per errore.
     */
    public static function html(string $tipo, string $versione): ?string
    {
        $file = self::percorso($tipo, $versione);
        if (! is_file($file)) {
            return null;
        }

        return Str::markdown(file_get_contents($file), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
