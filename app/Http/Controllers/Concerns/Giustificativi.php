<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PrimaNota;
use App\Models\PrimaNotaDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Giustificativi di un movimento di Prima Nota caricati da un modulo
 * (prima-nota.md, collaudo J4): messi da parte prima della validazione,
 * ripresentati dopo un errore, archiviati con il nome codificato
 * PN<anno>-<n. movimento>-<progressivo>. Usato dalla Prima Nota e dai
 * pacchetti di tessere (v0.37.0), che registrano anche l'uscita.
 */
trait Giustificativi
{
    private const MAX_KB = 8192;

    private const MAX_FILE = 10;

    private const ESTENSIONI = ['jpg', 'jpeg', 'png', 'pdf'];

    /** Giustificativi caricati e messi da parte finché il movimento non si salva. */
    private const SESSIONE_FILE = 'prima_nota_file_temp';

    /**
     * Giustificativi scelti nel modulo: si controllano e si mettono da parte
     * PRIMA della validazione del resto, così dopo un errore restano nel
     * modulo (collaudo J4). Ogni file diventa un gettone in sessione; il
     * modulo li rimanda come file_temp[]. Restituisce i messaggi dei file
     * rifiutati.
     *
     * @return string[]
     */
    private function trattieniFile(Request $request): array
    {
        $sessione = $this->pulisciFileTemp($request);
        $tenuti = array_values(array_unique(array_intersect(
            array_map('strval', (array) $request->input('file_temp', [])), array_keys($sessione))));
        $errori = [];

        $files = $request->file('documenti', []);
        foreach (is_array($files) ? $files : [$files] as $file) {
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }
            $nome = mb_substr($file->getClientOriginalName(), 0, 255) ?: 'file';
            $ext = strtolower((string) $file->guessExtension());
            if (! $file->isValid()) {
                $errori[] = "«{$nome}» non è arrivato per intero: riprova.";
            } elseif (! in_array($ext, self::ESTENSIONI, true)) {
                $errori[] = "«{$nome}»: i giustificativi devono essere JPG, PNG o PDF.";
            } elseif ($file->getSize() > self::MAX_KB * 1024) {
                $errori[] = "«{$nome}» pesa più di 8 MB.";
            } elseif (count($tenuti) >= self::MAX_FILE) {
                $errori[] = 'Al massimo ' . self::MAX_FILE . ' giustificativi per volta.';
                break;
            } else {
                $gettone = Str::random(40);
                $ext = $ext === 'jpeg' ? 'jpg' : $ext;
                $sessione[$gettone] = [
                    'path' => $file->storeAs('tmp/prima-nota/' . $request->user()->id, $gettone . '.' . $ext, 'local'),
                    'nome' => $nome,
                    'peso' => $file->getSize(),
                    'ext' => $ext,
                    'ora' => now()->timestamp,
                ];
                $tenuti[] = $gettone;
            }
        }

        $request->session()->put(self::SESSIONE_FILE, $sessione);
        $request->merge(['file_temp' => $tenuti]);

        return $errori;
    }

    /** File messi da parte da più di un giorno: si cancellano (moduli abbandonati). */
    private function pulisciFileTemp(Request $request): array
    {
        $sessione = (array) $request->session()->get(self::SESSIONE_FILE, []);
        $limite = now()->subDay()->timestamp;
        foreach ($sessione as $gettone => $f) {
            if (($f['ora'] ?? 0) < $limite || ! Storage::disk('local')->exists($f['path'] ?? '')) {
                Storage::disk('local')->delete($f['path'] ?? '');
                unset($sessione[$gettone]);
            }
        }
        $cartella = 'tmp/prima-nota/' . $request->user()->id;
        $inUso = array_column($sessione, 'path');
        foreach (Storage::disk('local')->files($cartella) as $path) {
            if (! in_array($path, $inUso, true) && Storage::disk('local')->lastModified($path) < $limite) {
                Storage::disk('local')->delete($path);
            }
        }

        return $sessione;
    }

    /** Dopo il salvataggio i gettoni usati escono dalla sessione e i file temporanei si cancellano. */
    private function liberaFile(Request $request, array $gettoni): void
    {
        $sessione = (array) $request->session()->get(self::SESSIONE_FILE, []);
        foreach ($gettoni as $g) {
            if (isset($sessione[$g])) {
                Storage::disk('local')->delete($sessione[$g]['path']);
            }
        }
        $request->session()->put(self::SESSIONE_FILE, array_diff_key($sessione, array_flip($gettoni)));
        $this->archiviati = [];
    }

    /** @var string[] file copiati nell'archivio durante la transazione in corso */
    private array $archiviati = [];

    /**
     * Esegue la transazione; se fallisce, toglie dall'archivio le copie già
     * fatte (i file messi da parte restano per un nuovo invio).
     */
    private function archiviaInSicurezza(\Closure $operazione): mixed
    {
        try {
            return DB::transaction($operazione);
        } catch (\Throwable $e) {
            foreach ($this->archiviati as $path) {
                Storage::disk('local')->delete($path);
            }
            $this->archiviati = [];
            throw $e;
        }
    }

    /**
     * Archivia i file messi da parte con un nome codificato, non quello
     * originale (collaudo J4): PN<anno>-<n. movimento>-<progressivo>.<est>,
     * es. PN2026-00005-01.pdf. Lo stesso nome si vede nella scheda e nel
     * file scaricato.
     *
     * @param string[] $gettoni
     */
    private function salvaFile(PrimaNota $m, array $gettoni, Request $request): void
    {
        $sessione = (array) $request->session()->get(self::SESSIONE_FILE, []);
        $anno = $m->annoMovimento();
        $progressivo = PrimaNotaDocumento::where('prima_nota_id', $m->id)->count();

        foreach ($gettoni as $gettone) {
            $f = $sessione[$gettone] ?? null;
            if (! $f || ! Storage::disk('local')->exists($f['path'])) {
                continue;
            }
            do {
                $progressivo++;
                $nome = sprintf('PN%d-%05d-%02d.%s', $anno, $m->id, $progressivo, $f['ext']);
                $path = "prima-nota/{$anno}/{$nome}";
            } while (Storage::disk('local')->exists($path));

            // Copia, non spostamento: se la transazione fallisce il file messo
            // da parte resta e il modulo lo ripresenta; la copia si toglie
            // (archiviaInSicurezza). Il file temporaneo si cancella dopo il
            // salvataggio riuscito (liberaFile).
            Storage::disk('local')->copy($f['path'], $path);
            $this->archiviati[] = $path;
            PrimaNotaDocumento::create([
                'prima_nota_id' => $m->id,
                'path' => $path,
                'nome' => $nome,
                'caricato_da' => $request->user()->id,
            ]);
        }
    }

    /** Giustificativi già messi da parte, per ripresentarli nel modulo dopo un errore. */
    public static function fileTenuti(): array
    {
        $sessione = (array) session(self::SESSIONE_FILE, []);

        return collect((array) old('file_temp', []))
            ->filter(fn ($g) => is_string($g) && isset($sessione[$g]))
            ->map(fn ($g) => ['gettone' => $g, 'nome' => $sessione[$g]['nome'], 'peso' => $sessione[$g]['peso']])
            ->values()->all();
    }

}
