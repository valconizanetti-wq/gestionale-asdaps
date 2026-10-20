<?php

namespace App\Services;

use App\Models\OrganoDocumento;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Documenti (PDF) di sedute, consultazioni e verbali (v0.44.0). Si salvano
 * in storage/app/private/verbali/documenti/<tipo>/<id>/ con un nome casuale;
 * il nome originale resta per lo scaricamento.
 */
class DocumentiOrgani
{
    public const MAX_KB = 8192;

    public const MAX_FILE = 10;

    /** Regole di validazione dei file. @return array<string, list<string>> */
    public static function regole(string $campo = 'documenti'): array
    {
        return [
            $campo => ['nullable', 'array', 'max:' . self::MAX_FILE],
            $campo . '.*' => ['file', 'mimes:pdf', 'max:' . self::MAX_KB],
        ];
    }

    /** @return array<string, string> */
    public static function messaggi(string $campo = 'documenti'): array
    {
        return [
            $campo . '.max' => 'Al massimo ' . self::MAX_FILE . ' documenti per volta.',
            $campo . '.*.mimes' => 'I documenti devono essere PDF.',
            $campo . '.*.max' => 'Ogni documento può pesare al massimo 8 MB.',
            $campo . '.*.uploaded' => 'Un documento non è arrivato per intero: riprova.',
        ];
    }

    /**
     * @param array<int, UploadedFile>|null $files
     * @return list<string> percorsi scritti (per toglierli se la transazione fallisce)
     */
    public function salva(string $tipo, int $id, ?array $files, ?User $utente): array
    {
        $scritti = [];
        $ordine = (int) OrganoDocumento::where('allegabile_type', $tipo)->where('allegabile_id', $id)->max('ordine');
        foreach ((array) $files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $path = $file->storeAs("verbali/documenti/{$tipo}/{$id}", bin2hex(random_bytes(12)) . '.pdf', 'local');
            $scritti[] = $path;
            OrganoDocumento::create([
                'allegabile_type' => $tipo,
                'allegabile_id' => $id,
                'path' => $path,
                'nome' => mb_substr(str_replace(['/', '\\'], '', $file->getClientOriginalName()) ?: 'documento.pdf', 0, 255),
                'dimensione' => (int) $file->getSize(),
                'ordine' => ++$ordine,
                'caricato_da' => $utente?->id,
            ]);
        }

        return $scritti;
    }

    /** Copia i riferimenti dei documenti (stessi file) da un elemento a un altro: da consultazione o seduta al verbale. */
    public function copia(string $daTipo, int $daId, string $aTipo, int $aId): void
    {
        foreach (OrganoDocumento::where('allegabile_type', $daTipo)->where('allegabile_id', $daId)->orderBy('ordine')->get() as $d) {
            OrganoDocumento::create([
                'allegabile_type' => $aTipo,
                'allegabile_id' => $aId,
                'path' => $d->path,
                'nome' => $d->nome,
                'dimensione' => $d->dimensione,
                'ordine' => $d->ordine,
                'caricato_da' => $d->caricato_da,
            ]);
        }
    }

    /** Toglie un documento; il file si cancella solo se nessun altro elemento lo usa. */
    public function elimina(OrganoDocumento $d): void
    {
        $path = $d->path;
        $d->delete();
        if (! OrganoDocumento::where('path', $path)->exists()) {
            Storage::disk('local')->delete($path);
        }
    }

    /** @param list<string> $paths */
    public static function cancellaFile(array $paths): void
    {
        foreach ($paths as $p) {
            Storage::disk('local')->delete($p);
        }
    }
}
