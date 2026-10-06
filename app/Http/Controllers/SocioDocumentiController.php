<?php

namespace App\Http\Controllers;

use App\Models\Impostazione;
use App\Models\Socio;
use App\Models\SocioDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Documenti del socio e consenso privacy (soci.md §3, "Documenti e
 * privacy"). File su disco privato 'local', scaricabili solo da chi
 * accede alla sezione Soci.
 */
class SocioDocumentiController extends Controller
{
    // ----------------------------------------------------------------
    // Documenti
    // ----------------------------------------------------------------

    public function store(Request $request, Socio $socio)
    {
        $this->richiediModifica($request);

        $categorie = config('soci.categorie_documenti');
        $dati = $request->validate([
            'categoria'   => ['required', 'in:' . implode(',', array_keys($categorie))],
            'descrizione' => ['nullable', 'required_if:categoria,altro', 'string', 'max:255'],
            'file'        => ['required', 'file', 'mimes:' . config('soci.upload.estensioni'), 'max:' . config('soci.upload.max_kb')],
        ], [
            'descrizione.required_if' => 'Per la categoria «Altro» indica di che documento si tratta.',
            'file.mimes' => 'Sono ammessi solo file PDF, JPG o PNG.',
            'file.max'   => 'Il file supera i 5 MB.',
        ], ['file' => 'file']);

        SocioDocumento::create([
            'socio_id'       => $socio->id,
            'categoria'      => $dati['categoria'],
            'descrizione'    => $dati['descrizione'] ?? null,
            'file_path'      => $this->salva($request->file('file'), $socio, 'documenti'),
            'file_originale' => $request->file('file')->getClientOriginalName(),
            'caricato_da'    => $request->user()->id,
        ]);

        // La scheda di iscrizione caricata vale anche come "presente".
        if ($dati['categoria'] === 'scheda_iscrizione' && ! $socio->scheda_registrazione_presente) {
            $socio->update(['scheda_registrazione_presente' => true]);
        }

        return redirect()->route('soci.show', $socio)
            ->with('success', $categorie[$dati['categoria']] . ' caricato.');
    }

    public function download(Socio $socio, SocioDocumento $documento)
    {
        $this->richiediAppartenenza($socio, $documento);
        abort_unless(Storage::disk('local')->exists($documento->file_path), 404, 'File non trovato.');

        return Storage::disk('local')->download($documento->file_path, $documento->file_originale);
    }

    /**
     * Anteprima nel browser (nuova scheda): stesso file del download, ma
     * servito "inline". Sono ammessi solo PDF, JPG e PNG (validati al
     * caricamento sul contenuto reale), e nosniff impedisce al browser di
     * interpretarli come altro.
     */
    public function anteprima(Socio $socio, SocioDocumento $documento)
    {
        $this->richiediAppartenenza($socio, $documento);

        return $this->inline($documento->file_path, $documento->file_originale);
    }

    public function destroy(Request $request, Socio $socio, SocioDocumento $documento)
    {
        $this->richiediModifica($request);
        $this->richiediAppartenenza($socio, $documento);

        $path = $documento->file_path;
        $documento->delete();
        Storage::disk('local')->delete($path);

        return redirect()->route('soci.show', $socio)->with('success', 'Documento eliminato.');
    }

    // ----------------------------------------------------------------
    // Consenso privacy
    // ----------------------------------------------------------------

    /** Consenso raccolto di persona: si registra data, ora e versione dell'informativa. */
    public function privacyPresenza(Request $request, Socio $socio)
    {
        $this->richiediModifica($request);

        $socio->update([
            'privacy_accettata_il' => now(),
            'privacy_metodo'       => 'presenza',
            'privacy_versione'     => $this->versioneInformativa(),
            'privacy_ip'           => null, // l'IP sarebbe quello dell'operatore, non del socio
        ]);

        return redirect()->route('soci.show', $socio)->with('success', 'Consenso privacy registrato.');
    }

    /** Consenso su modulo cartaceo: si carica la scansione del modulo firmato. */
    public function privacyCartacea(Request $request, Socio $socio)
    {
        $this->richiediModifica($request);

        $dati = $request->validate([
            'data_firma' => ['required', 'date', 'before_or_equal:today'],
            'modulo'     => ['required', 'file', 'mimes:' . config('soci.upload.estensioni'), 'max:' . config('soci.upload.max_kb')],
        ], [
            'modulo.mimes' => 'Sono ammessi solo file PDF, JPG o PNG.',
            'modulo.max'   => 'Il file supera i 5 MB.',
        ], ['data_firma' => 'data della firma', 'modulo' => 'modulo firmato']);

        // Il modulo precedente non si cancella: resta come prova del
        // consenso dato in passato (il cambio è comunque nell'audit log).
        $socio->update([
            'privacy_accettata_il' => $dati['data_firma'],
            'privacy_metodo'       => 'cartaceo',
            'privacy_versione'     => $this->versioneInformativa(),
            'privacy_ip'           => null,
            'privacy_doc_path'     => $this->salva($request->file('modulo'), $socio, 'privacy'),
        ]);

        return redirect()->route('soci.show', $socio)->with('success', 'Modulo privacy firmato caricato.');
    }

    /** Link personale per l'informativa e foto e video (privacy-link.md §3): nuovo invio, il precedente è annullato. */
    public function privacyLink(Request $request, Socio $socio, \App\Services\LinkPrivacy $servizio)
    {
        $this->richiediModifica($request);

        $esito = $servizio->invia($socio, $request->user());

        return redirect()->route('soci.show', $socio)->with(
            $esito['inviato'] ? 'success' : 'error',
            $esito['inviato'] ? "Link inviato a {$esito['email']}." : 'Link non inviato: ' . $esito['motivo']
        );
    }

    /**
     * Revoca del consenso a foto e video arrivata alla segreteria (email, di
     * persona, lettera). Il consenso lo dà solo il socio, con la domanda o
     * con il link: qui si registra solo la revoca.
     */
    public function fotoVideoRevoca(Request $request, Socio $socio)
    {
        $this->richiediModifica($request);
        abort_if($socio->pseudonimizzato, 403);

        // La revoca non può precedere il consenso che revoca.
        $dal = $socio->foto_video_il?->toDateString();
        $dati = $request->validate([
            'revoca_il' => array_filter(['required', 'date', 'before_or_equal:today', $dal ? "after_or_equal:{$dal}" : null]),
            'revoca_modo' => ['required', 'in:' . implode(',', array_keys(Socio::MODI_REVOCA))],
        ], [
            'revoca_il.after_or_equal' => 'La revoca non può essere precedente al consenso (' . $socio->foto_video_il?->format('d/m/Y') . ').',
        ], ['revoca_il' => 'data della richiesta', 'revoca_modo' => 'come è arrivata']);

        if (! $socio->foto_video_consenso) {
            return redirect()->route('soci.show', $socio)->with('error', 'Il consenso a foto e video risulta già non dato.');
        }

        $socio->update([
            'foto_video_consenso' => false,
            'foto_video_il' => $dati['revoca_il'],
            'foto_video_origine' => $dati['revoca_modo'],
        ]);

        return redirect()->route('soci.show', $socio)->with('success', 'Revoca del consenso a foto e video registrata.');
    }

    public function privacyDownload(Socio $socio)
    {
        abort_unless($socio->privacy_doc_path && Storage::disk('local')->exists($socio->privacy_doc_path), 404, 'File non trovato.');

        $estensione = pathinfo($socio->privacy_doc_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $socio->privacy_doc_path,
            Str::slug("privacy {$socio->cognome} {$socio->nome}") . ".{$estensione}"
        );
    }

    public function privacyAnteprima(Socio $socio)
    {
        abort_unless($socio->privacy_doc_path, 404, 'File non trovato.');

        $estensione = pathinfo($socio->privacy_doc_path, PATHINFO_EXTENSION);

        return $this->inline(
            $socio->privacy_doc_path,
            Str::slug("privacy {$socio->cognome} {$socio->nome}") . ".{$estensione}"
        );
    }

    // ----------------------------------------------------------------
    // Supporto
    // ----------------------------------------------------------------

    private function inline(string $path, string $nome)
    {
        $disco = Storage::disk('local');
        abort_unless($disco->exists($path), 404, 'File non trovato.');

        return $disco->response($path, $nome, [
            'Content-Type' => $disco->mimeType($path),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    /** Salva il file con un nome casuale: il nome originale resta solo nel database. */
    private function salva(UploadedFile $file, Socio $socio, string $cartella): string
    {
        return $file->storeAs(
            "soci/{$socio->id}/{$cartella}",
            Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()),
            'local'
        );
    }

    private function versioneInformativa(): ?string
    {
        // Informativa in vigore oggi secondo il profilo dell'ente (v0.32.0).
        return \App\Services\ProfiloEnte::versione('informativa');
    }

    /**
     * Il documento deve essere di quel socio. Controllo esplicito invece di
     * scopeBindings(): Laravel cercherebbe la relazione "documentos"
     * (plurale inglese di {documento}), che non esiste.
     */
    private function richiediAppartenenza(Socio $socio, SocioDocumento $documento): void
    {
        abort_unless($documento->socio_id === $socio->id, 404);
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('soci'), 403, 'Il tuo ruolo può solo consultare questa sezione.');
    }
}
