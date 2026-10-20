<?php

namespace App\Http\Controllers;

use App\Models\OrganoDocumento;
use App\Models\Seduta;
use App\Services\DocumentiOrgani;
use App\Services\Riunioni;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Riunioni del Consiglio Direttivo (v0.44.0, tavola CD-5): bozza,
 * convocazione, rinvio, annullamento, presenze. Il verbale per punti si
 * scrive da VerbaliController (VB-2).
 *
 * Rotte nel gruppo 'permesso:verbali': leggono il CD, il Revisore e
 * l'Amministratore; scrivono il Presidente e il Segretario.
 */
class RiunioniController extends Controller
{
    public function __construct(private Riunioni $riunioni, private DocumentiOrgani $documenti)
    {
    }

    public function create(Request $request): View
    {
        $this->richiediModifica($request);

        return view('verbali.riunioni.form', ['s' => new Seduta(['tipo' => 'cd', 'ora_inizio' => '21:00', 'ordine_del_giorno' => ['']]), 'documenti' => collect()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->richiediModifica($request);
        $s = $this->riunioni->salvaBozza(null, $this->dati($request), $request->file('documenti'), $request->user());

        return redirect()->route('verbali.riunioni.show', $s)->with('success', 'Riunione salvata in bozza. Controlla e invia la convocazione.');
    }

    public function show(Seduta $seduta): View
    {
        abort_unless($seduta->isCd(), 404);
        $seduta->load(['presenze', 'verbale', 'presidenteSocio', 'segretarioSocio', 'creataDa']);

        return view('verbali.riunioni.show', [
            's' => $seduta,
            'documenti' => $seduta->documenti(),
            'membri' => Riunioni::membri(),
            'termine' => $seduta->stato === 'bozza' ? Riunioni::controllaTermine($seduta) : null,
            'righe' => $seduta->presenzeAperte() ? Riunioni::righePresenze($seduta) : collect(),
            'assenze' => $seduta->stato === 'svolta' ? Riunioni::assenzeConsecutive($seduta) : [],
        ]);
    }

    public function edit(Request $request, Seduta $seduta): View|RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($seduta->isCd(), 404);
        if (! $seduta->modificabile()) {
            return redirect()->route('verbali.riunioni.show', $seduta)->with('error', 'Dopo la convocazione la riunione non si modifica: rinviala e riconvocala.');
        }

        return view('verbali.riunioni.form', ['s' => $seduta, 'documenti' => $seduta->documenti()]);
    }

    public function update(Request $request, Seduta $seduta): RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($seduta->isCd(), 404);
        $this->riunioni->salvaBozza($seduta, $this->dati($request), $request->file('documenti'), $request->user());

        return redirect()->route('verbali.riunioni.show', $seduta)->with('success', 'Riunione aggiornata.');
    }

    public function destroy(Request $request, Seduta $seduta): RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($seduta->isCd() && $seduta->stato === 'bozza', 403, 'Si elimina solo una riunione in bozza: una convocata si annulla.');
        foreach ($seduta->documenti() as $d) {
            $this->documenti->elimina($d);
        }
        $seduta->delete();

        return redirect()->route('verbali.index')->with('success', 'Bozza della riunione eliminata.');
    }

    // ----------------------------------------------------------------
    // Convocazione, rinvio, annullamento
    // ----------------------------------------------------------------

    public function convoca(Request $request, Seduta $seduta): RedirectResponse
    {
        $this->richiediModifica($request);
        $r = $this->riunioni->convoca($seduta, $request->user());
        $msg = $r['inviate'] === 1 ? 'Convocazione inviata a 1 membro.' : "Convocazione inviata a {$r['inviate']} membri.";
        if ($r['senza_email']) {
            return redirect()->route('verbali.riunioni.show', $seduta)->with('success', $msg)
                ->with('error', 'Senza email (avvisali tu): ' . implode(', ', $r['senza_email']) . '.');
        }

        return redirect()->route('verbali.riunioni.show', $seduta)->with('success', $msg);
    }

    public function prova(Request $request, Seduta $seduta): RedirectResponse
    {
        $this->richiediModifica($request);
        $r = $this->riunioni->convoca($seduta, $request->user(), prova: true);

        return back()->with($r['inviate'] ? 'success' : 'error', $r['inviate']
            ? 'Prova inviata a ' . $request->user()->email . '.'
            : 'Prova non inviata: controlla l\'email del tuo utente e il registro degli invii.');
    }

    public function rinvia(Request $request, Seduta $seduta): RedirectResponse
    {
        $this->richiediModifica($request);
        $d = $request->validateWithBag('rinvio', [
            'motivo_rinvio' => ['required', 'string', 'min:10', 'max:500'],
            'avvisa' => ['nullable', 'boolean'],
        ], ['motivo_rinvio.required' => 'Scrivi il motivo del rinvio.', 'motivo_rinvio.min' => 'Il motivo deve avere almeno :min caratteri.']);
        $n = $this->conBag('rinvio', fn () => $this->riunioni->rinvia($seduta, trim($d['motivo_rinvio']), ! empty($d['avvisa']), $request->user()));

        return redirect()->route('verbali.riunioni.show', $seduta)->with('success', 'Riunione rinviata' . (! empty($d['avvisa']) ? ": avviso inviato a {$n} membri." : '.') . ' Con «Riconvoca» si sceglie la nuova data.');
    }

    public function riconvoca(Request $request, Seduta $seduta): RedirectResponse
    {
        $this->richiediModifica($request);
        $this->riunioni->riconvoca($seduta, $request->user());

        return redirect()->route('verbali.riunioni.edit', $seduta)->with('success', 'La riunione è tornata in bozza: scegli la nuova data e invia di nuovo la convocazione.');
    }

    public function annulla(Request $request, Seduta $seduta): RedirectResponse
    {
        $this->richiediModifica($request);
        $d = $request->validateWithBag('annulla', [
            'motivo_annullamento' => ['required', 'string', 'min:10', 'max:500'],
        ], ['motivo_annullamento.required' => 'Scrivi il motivo dell\'annullamento.', 'motivo_annullamento.min' => 'Il motivo deve avere almeno :min caratteri.']);
        $this->conBag('annulla', fn () => $this->riunioni->annulla($seduta, trim($d['motivo_annullamento']), $request->user()));

        return redirect()->route('verbali.riunioni.show', $seduta)->with('success', 'Riunione annullata.');
    }

    // ----------------------------------------------------------------
    // Presenze
    // ----------------------------------------------------------------

    public function presenze(Request $request, Seduta $seduta): RedirectResponse
    {
        $this->richiediModifica($request);
        $d = $request->validate([
            'presenze' => ['required', 'array'],
            'presenze.*.presenza' => ['required', Rule::in(['sede', 'video', 'assente'])],
            'presenze.*.giustificato' => ['nullable', 'boolean'],
            'presidente_socio_id' => ['required', 'integer'],
            'segretario_socio_id' => ['required', 'integer'],
        ], [
            'presenze.*.presenza.required' => 'Indica la presenza di ogni membro.',
            'presidente_socio_id.required' => 'Scegli chi presiede la riunione.',
            'segretario_socio_id.required' => 'Scegli il segretario verbalizzante.',
        ]);
        $v = $this->riunioni->salvaPresenze($seduta, $d['presenze'], (int) $d['presidente_socio_id'], (int) $d['segretario_socio_id'], $request->user());

        return redirect()->route('verbali.scrivi', $v)->with('success', 'Presenze registrate. Ora scrivi il verbale, punto per punto.');
    }

    // ----------------------------------------------------------------
    // Documenti
    // ----------------------------------------------------------------

    public function documento(Seduta $seduta, OrganoDocumento $documento)
    {
        abort_unless($documento->allegabile_type === 'seduta' && $documento->allegabile_id === $seduta->id, 404);
        abort_unless(Storage::disk('local')->exists($documento->path), 404);

        return Storage::disk('local')->response($documento->path, $documento->nome, ['Content-Type' => 'application/pdf'], 'inline');
    }

    public function eliminaDocumento(Request $request, Seduta $seduta, OrganoDocumento $documento): RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($documento->allegabile_type === 'seduta' && $documento->allegabile_id === $seduta->id, 404);
        abort_unless($seduta->modificabile(), 403, 'Dopo la convocazione i documenti non si tolgono.');
        $this->documenti->elimina($documento);

        return back()->with('success', 'Documento tolto.');
    }

    // ----------------------------------------------------------------

    /** @return array<string, mixed> */
    private function dati(Request $request): array
    {
        $d = $request->validate([
            'data' => ['required', 'date', 'after_or_equal:today'],
            'ora_inizio' => ['required', 'date_format:H:i'],
            'luogo' => ['nullable', 'required_unless:videoconferenza,1', 'string', 'max:255'],
            'videoconferenza' => ['nullable', 'boolean'],
            'collegamento' => ['nullable', 'string', 'max:500'],
            'punti' => ['required', 'array', 'max:30'],
            'punti.*' => ['nullable', 'string', 'max:500'],
            'urgente' => ['nullable', 'boolean'],
            'motivo_urgenza' => ['nullable', 'required_if:urgente,1', 'string', 'max:500'],
            ...DocumentiOrgani::regole(),
        ], DocumentiOrgani::messaggi() + [
            'data.after_or_equal' => 'La data della riunione non può essere passata.',
            'ora_inizio.date_format' => 'Scrivi l\'ora come 21:00.',
            'luogo.required_unless' => 'Scrivi il luogo, o spunta la videoconferenza.',
            'motivo_urgenza.required_if' => 'Scrivi il motivo dell\'urgenza.',
            'punti.required' => 'Scrivi almeno un punto dell\'ordine del giorno.',
        ], ['ora_inizio' => 'ora']);

        return [
            'data' => $d['data'],
            'ora_inizio' => $d['ora_inizio'],
            'luogo' => isset($d['luogo']) ? trim($d['luogo']) : null,
            'videoconferenza' => ! empty($d['videoconferenza']),
            'collegamento' => isset($d['collegamento']) ? trim($d['collegamento']) : null,
            'punti' => $d['punti'],
            'urgente' => ! empty($d['urgente']),
            'motivo_urgenza' => isset($d['motivo_urgenza']) ? trim($d['motivo_urgenza']) : null,
        ];
    }

    private function conBag(string $bag, callable $fai)
    {
        try {
            return $fai();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $e->errorBag = $bag;
            throw $e;
        }
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('verbali'), 403, 'Il tuo ruolo può solo consultare le riunioni.');
    }
}
