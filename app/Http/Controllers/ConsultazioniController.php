<?php

namespace App\Http\Controllers;

use App\Models\Consultazione;
use App\Models\OrganoDocumento;
use App\Services\Consultazioni;
use App\Services\DocumentiOrgani;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Consultazioni telematiche del CD nell'area riservata (v0.44.0, tavole
 * CT-1 bozza e CT-3 stato dei voti). La bozza la prepara chi scrive i
 * verbali; l'avvio è del Presidente o del Vicepresidente in sostituzione
 * (controllo nel servizio, V16).
 */
class ConsultazioniController extends Controller
{
    public function __construct(private Consultazioni $consultazioni, private DocumentiOrgani $documenti)
    {
    }

    public function create(Request $request): View
    {
        $this->richiediModifica($request);

        return view('verbali.consultazioni.form', [
            'c' => new Consultazione(['termine' => Consultazioni::termineMinimo()->addDay()->setTime(21, 0)]),
            'documenti' => collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->richiediModifica($request);
        $d = $this->dati($request);
        $c = $this->consultazioni->salvaBozza(null, $d['oggetto'], $d['testo_html'], $d['termine'], $request->file('documenti'), $request->user());

        return redirect()->route('verbali.consultazioni.show', $c)->with('success', 'Consultazione salvata in bozza.' . ($request->user()->haRuolo('presidente') ? '' : ' La avvia il Presidente.'));
    }

    public function show(Request $request, Consultazione $consultazione): View
    {
        $consultazione->load(['voti', 'verbale', 'preparataDa', 'avviataDa']);
        $u = $request->user();

        return view('verbali.consultazioni.show', [
            'c' => $consultazione,
            'documenti' => $consultazione->documenti(),
            'calcolo' => $consultazione->isBozza() ? null : Consultazioni::calcola($consultazione),
            'puoAvviare' => Consultazioni::motivoNonAvvia($u, true) === null,
            'vice' => ! $u->haRuolo('presidente') && $u->haRuolo('vicepresidente'),
            'termineMinimo' => Consultazioni::termineMinimo(),
        ]);
    }

    public function edit(Request $request, Consultazione $consultazione): View|RedirectResponse
    {
        $this->richiediModifica($request);
        if (! $consultazione->isBozza()) {
            return redirect()->route('verbali.consultazioni.show', $consultazione);
        }

        return view('verbali.consultazioni.form', ['c' => $consultazione, 'documenti' => $consultazione->documenti()]);
    }

    public function update(Request $request, Consultazione $consultazione): RedirectResponse
    {
        $this->richiediModifica($request);
        $d = $this->dati($request);
        $this->consultazioni->salvaBozza($consultazione, $d['oggetto'], $d['testo_html'], $d['termine'], $request->file('documenti'), $request->user());

        return redirect()->route('verbali.consultazioni.show', $consultazione)->with('success', 'Consultazione aggiornata.');
    }

    public function destroy(Request $request, Consultazione $consultazione): RedirectResponse
    {
        $this->richiediModifica($request);
        $this->consultazioni->eliminaBozza($consultazione);

        return redirect()->route('verbali.index')->with('success', 'Bozza della consultazione eliminata.');
    }

    public function avvia(Request $request, Consultazione $consultazione): RedirectResponse
    {
        $r = $this->consultazioni->avvia($consultazione, $request->user(), $request->boolean('in_sostituzione'));

        return redirect()->route('verbali.consultazioni.show', $consultazione)
            ->with('success', "Consultazione avviata: link personale inviato a {$r['inviate']} " . ($r['inviate'] === 1 ? 'componente.' : 'componenti.'));
    }

    public function sollecita(Request $request, Consultazione $consultazione): RedirectResponse
    {
        $this->richiediModifica($request);
        $n = $this->consultazioni->sollecita($consultazione, $request->user());

        return back()->with('success', $n === 1 ? 'Sollecito inviato a 1 componente.' : "Sollecito inviato a {$n} componenti.");
    }

    public function ritira(Request $request, Consultazione $consultazione): RedirectResponse
    {
        $d = $request->validateWithBag('ritiro', [
            'motivo_ritiro' => ['required', 'string', 'min:10', 'max:500'],
        ], ['motivo_ritiro.required' => 'Scrivi il motivo del ritiro.', 'motivo_ritiro.min' => 'Il motivo deve avere almeno :min caratteri.']);
        try {
            $this->consultazioni->ritira($consultazione, trim($d['motivo_ritiro']), $request->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $e->errorBag = 'ritiro';
            throw $e;
        }

        return redirect()->route('verbali.consultazioni.show', $consultazione)->with('success', 'Consultazione ritirata: i componenti sono avvisati per email.');
    }

    public function documento(Consultazione $consultazione, OrganoDocumento $documento)
    {
        abort_unless($documento->allegabile_type === 'consultazione' && $documento->allegabile_id === $consultazione->id, 404);
        abort_unless(Storage::disk('local')->exists($documento->path), 404);

        return Storage::disk('local')->response($documento->path, $documento->nome, ['Content-Type' => 'application/pdf'], 'inline');
    }

    public function eliminaDocumento(Request $request, Consultazione $consultazione, OrganoDocumento $documento): RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($documento->allegabile_type === 'consultazione' && $documento->allegabile_id === $consultazione->id, 404);
        abort_unless($consultazione->isBozza(), 403, 'Dopo l\'avvio i documenti non si tolgono.');
        $this->documenti->elimina($documento);

        return back()->with('success', 'Documento tolto.');
    }

    /** @return array{oggetto: string, testo_html: string, termine: string} */
    private function dati(Request $request): array
    {
        return $request->validate([
            'oggetto' => ['required', 'string', 'max:255'],
            'testo_html' => ['required', 'string', 'max:60000'],
            'termine' => ['required', 'date', 'after:now'],
            ...DocumentiOrgani::regole(),
        ], DocumentiOrgani::messaggi() + [
            'oggetto.required' => 'Scrivi l\'oggetto della proposta.',
            'testo_html.required' => 'Scrivi il testo della proposta di delibera.',
            'termine.after' => 'Il termine deve essere nel futuro.',
        ]);
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('verbali'), 403, 'Il tuo ruolo può solo consultare le consultazioni.');
    }
}
