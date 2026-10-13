<?php

namespace App\Http\Controllers;

use App\Services\ImportazioneSoci;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Soci › Importa da Excel (v0.40.0, tavole IS-1…IS-3,
 * analisi-importazione-soci.md). Come «Carica tessere già emesse»: il file
 * resta in tmp/soci-import/ fino a conferma o annullamento (percorso in
 * sessione), poi si cancella; pulizia giornaliera con tessere:pulisci.
 * Presidente e Segretario (chi modifica i Soci, decisione D5).
 */
class SociImportaController extends Controller
{
    private const SESSIONE = 'importazione_soci';

    private const CARTELLA = 'tmp/soci-import';

    public function __construct(private ImportazioneSoci $servizio)
    {
    }

    public function index(Request $request)
    {
        $this->richiediModifica($request);
        $carico = $request->session()->get(self::SESSIONE);
        $anteprima = null;
        if ($carico && Storage::disk('local')->exists($carico['path'])) {
            $anteprima = $this->servizio->leggi(Storage::disk('local')->path($carico['path'])) + ['nome' => $carico['nome'], 'letto_il' => $carico['letto_il'] ?? null];
        }

        return view('soci.importa', ['anteprima' => $anteprima]);
    }

    public function modello(Request $request)
    {
        $this->richiediModifica($request);

        return response()->download(resource_path('modelli/importazione-soci-modello.xlsx'), 'importazione-soci-modello.xlsx');
    }

    public function leggi(Request $request)
    {
        $this->richiediModifica($request);
        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'extensions:xlsx'],   // l'estensione: il tipo MIME di un .xlsx a volte è «zip»; il contenuto lo controlla il lettore
        ], [
            'file.required' => 'Scegli il file Excel con i soci.',
            'file.extensions' => 'Serve il modello in formato Excel (.xlsx).',
            'file.max' => 'Il file è troppo grande: al massimo 2 MB.',
        ]);

        $this->scarta($request);
        $path = $request->file('file')->storeAs(self::CARTELLA, Str::random(40) . '.xlsx', 'local');
        $request->session()->put(self::SESSIONE, [
            'path' => $path,
            'nome' => mb_substr($request->file('file')->getClientOriginalName(), 0, 100),
            'letto_il' => now()->format('H:i'),
        ]);

        return redirect()->route('soci.importa');
    }

    public function conferma(Request $request)
    {
        $this->richiediModifica($request);
        $carico = $request->session()->get(self::SESSIONE);
        if (! $carico || ! Storage::disk('local')->exists($carico['path'])) {
            return redirect()->route('soci.importa')->with('error', 'Il file non c\'è più: caricalo di nuovo.');
        }

        try {
            $esito = $this->servizio->importa(Storage::disk('local')->path($carico['path']), $carico['nome'], $request->user());
        } catch (\RuntimeException $e) {
            // Il file resta per ricontrollare l'anteprima (che mostra il perché)
            return redirect()->route('soci.importa')->with('error', $e->getMessage());
        }
        $this->scarta($request);   // dati personali: il file non resta

        $request->session()->put('importazione_soci_esito', $esito + ['data' => now()->toDateString()]);

        return redirect()->route('soci.importa.fatto');
    }

    public function fatto(Request $request)
    {
        $this->richiediModifica($request);
        $esito = $request->session()->get('importazione_soci_esito');
        if (! $esito) {
            return redirect()->route('soci.index');
        }

        return view('soci.importa-fatto', ['esito' => $esito]);
    }

    public function annulla(Request $request)
    {
        $this->richiediModifica($request);
        $this->scarta($request);

        return redirect()->route('soci.importa');
    }

    /** Il file contiene dati personali: si cancella appena non serve più. */
    private function scarta(Request $request): void
    {
        $disco = Storage::disk('local');
        if ($vecchio = $request->session()->pull(self::SESSIONE)) {
            $disco->delete($vecchio['path']);
        }
        foreach ($disco->files(self::CARTELLA) as $f) {
            if ($disco->lastModified($f) < now()->subDay()->timestamp) {
                $disco->delete($f);
            }
        }
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('soci'), 403, 'Importano i soci Presidente e Segretario.');
    }
}
