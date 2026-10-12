<?php

namespace App\Scontrini;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Chiamata a OCR.space (analisi-scontrini.md §2, §12): motore 2, italiano,
 * isTable=true (una sola richiesta per foto: il testo semplice separa le
 * etichette dagli importi), raddrizzamento e ingrandimento automatici.
 *
 * Non lancia mai eccezioni: lo scontrino si registra lo stesso, con i campi
 * da scrivere a mano. Esiti: letto, non_letto (risposta senza testo),
 * non_disponibile (nessuna chiave o file troppo grande), errore (rete,
 * certificato, chiave rifiutata). La chiave non finisce mai nei log.
 */
class ServizioOcr
{
    /** @return array{esito: string, testo: ?string, ms: ?int, messaggio: ?string} */
    public function leggi(string $contenuto, string $mime): array
    {
        $chiave = (string) config('services.ocr_space.key');
        if ($chiave === '') {
            return ['esito' => 'non_disponibile', 'testo' => null, 'ms' => null, 'messaggio' => 'La lettura automatica non è configurata.'];
        }
        if (strlen($contenuto) > Foto::LIMITE_OCR) {
            return ['esito' => 'non_disponibile', 'testo' => null, 'ms' => null, 'messaggio' => 'Il file è troppo grande per la lettura automatica.'];
        }

        $inizio = microtime(true);
        try {
            $richiesta = Http::asForm()->timeout((int) config('services.ocr_space.timeout', 25))
                ->withHeaders(['apikey' => $chiave]);
            if ($ca = config('services.ocr_space.ca')) {
                $richiesta = $richiesta->withOptions(['verify' => $ca]);
            }
            $risposta = $richiesta->post((string) config('services.ocr_space.url'), [
                'base64Image' => "data:{$mime};base64," . base64_encode($contenuto),
                'language' => 'ita',
                'OCREngine' => '2',
                'isTable' => 'true',
                'detectOrientation' => 'true',
                'scale' => 'true',
            ]);
            $ms = (int) ((microtime(true) - $inizio) * 1000);
            $j = $risposta->json();
            if (! $risposta->successful() || ! is_array($j)) {
                Log::warning('OCR.space: risposta HTTP ' . $risposta->status());

                return ['esito' => 'errore', 'testo' => null, 'ms' => $ms, 'messaggio' => 'Il servizio di lettura non ha risposto.'];
            }
            if (! empty($j['IsErroredOnProcessing'])) {
                Log::warning('OCR.space: ' . implode(' ', (array) ($j['ErrorMessage'] ?? [])));

                return ['esito' => 'errore', 'testo' => null, 'ms' => $ms, 'messaggio' => 'Il servizio di lettura ha dato un errore.'];
            }
            $testo = trim(implode("\n", array_map(fn ($p) => (string) ($p['ParsedText'] ?? ''), (array) ($j['ParsedResults'] ?? []))));

            return $testo === ''
                ? ['esito' => 'non_letto', 'testo' => null, 'ms' => $ms, 'messaggio' => 'Nella foto non si legge testo.']
                : ['esito' => 'letto', 'testo' => mb_substr($testo, 0, 20000), 'ms' => $ms, 'messaggio' => null];
        } catch (\Throwable $e) {
            Log::warning('OCR.space non raggiungibile: ' . get_class($e) . ' ' . str_replace($chiave, '***', $e->getMessage()));

            return ['esito' => 'errore', 'testo' => null, 'ms' => (int) ((microtime(true) - $inizio) * 1000), 'messaggio' => 'Il servizio di lettura non ha risposto.'];
        }
    }
}
