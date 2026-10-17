<?php

namespace App\Http\Controllers;

use App\Models\GovernanceCd;
use App\Models\Socio;
use App\Models\User;
use App\Services\ConsiglioDirettivo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Pagina «Consiglio Direttivo» (v0.42.0, tavole CD-1…CD-4,
 * analisi-collaudo-O.md §15). Le regole sono nel servizio.
 *
 * Gli errori tornano alla pagina con la finestra da riaprire
 * (session «modale»), come nelle altre pagine con le finestre.
 */
class ConsiglioDirettivoController extends Controller
{
    public function __construct(private ConsiglioDirettivo $cd) {}

    public function index(Request $request)
    {
        $membri = ConsiglioDirettivo::membri();
        $utenti = User::whereIn('socio_id', $membri->pluck('socio_id'))->get()->keyBy('socio_id');
        $chi = $request->user();

        // Soci attivi non nel CD, in ordine di cognome (tavola CD-2)
        $soci = Socio::where('stato', 'attivo')
            ->whereNotIn('id', $membri->pluck('socio_id'))
            ->orderBy('cognome')->orderBy('nome')->get(['id', 'cognome', 'nome', 'data_nascita', 'sesso', 'email']);

        $occupati = ConsiglioDirettivo::occupati();

        return view('governance.index', [
            'membri' => $membri,
            'utenti' => $utenti,
            'usciti' => GovernanceCd::with('socio')->where('in_carica', false)->orderByDesc('data_fine_mandato')->limit(20)->get(),
            'soci' => $soci,
            'occupati' => array_map(fn ($m) => ['id' => $m->id, 'nome' => trim("{$m->socio?->cognome} {$m->socio?->nome}")], $occupati),
            'puoModificare' => $chi->puoModificare('governance_cd'),
            'puoPresidente' => $chi->haRuolo('presidente', 'amministratore'),
            'io' => $chi->socio_id,
        ]);
    }

    public function store(Request $request)
    {
        return $this->conModale($request, 'aggiungi', function () use ($request) {
            $dati = $request->validate([
                'socio_id' => ['required', 'integer', Rule::exists('gl_soci', 'id')->whereNull('deleted_at')],
                'carica' => ['required', Rule::in(array_keys(ConsiglioDirettivo::CARICHE))],
                'dal' => ['required', 'date'],
                'nominato_da' => ['nullable', 'string', 'max:255'],
            ], ['socio_id.required' => 'Scegli il socio.', 'carica.required' => 'Scegli la carica.'], ['dal' => 'data']);
            $esito = $this->cd->aggiungi(Socio::findOrFail($dati['socio_id']), $dati['carica'], Carbon::parse($dati['dal']),
                $dati['nominato_da'] ?? null, $request->boolean('link'), $request->user());
            $s = $esito['membro']->socio;
            $utente = $esito['nuovo'] ? 'creato l\'utente' : 'collegato il suo utente';

            return "{$s->cognome} {$s->nome} è nel Consiglio Direttivo come ".ConsiglioDirettivo::etichetta($dati['carica'])
                .": {$utente}".($esito['link'] ? ' e inviato il link per la password.' : '.');
        });
    }

    public function carica(Request $request, GovernanceCd $membro)
    {
        return $this->conModale($request, "carica-{$membro->id}", function () use ($request, $membro) {
            $dati = $request->validate([
                'nuova_carica' => ['required', Rule::in(array_keys(ConsiglioDirettivo::CARICHE))],
                'dal_carica' => ['required', 'date'],
                'motivazione' => ['required', 'string', 'min:10', 'max:1000'],
                'altri' => ['nullable', Rule::in(['consigliere', 'esce'])],
            ], ['motivazione.required' => 'Scrivi perché cambia la carica.', 'motivazione.min' => 'La motivazione deve avere almeno 10 caratteri.'],
                ['nuova_carica' => 'carica', 'dal_carica' => 'data']);
            $this->cd->cambiaCarica($membro, $dati['nuova_carica'], Carbon::parse($dati['dal_carica']), $dati['motivazione'],
                $dati['altri'] ?? 'consigliere', $request->user());
            $s = $membro->socio;

            return "{$s->cognome} {$s->nome} ora è ".ConsiglioDirettivo::etichetta($dati['nuova_carica']).'.';
        });
    }

    public function togli(Request $request, GovernanceCd $membro)
    {
        return $this->conModale($request, "togli-{$membro->id}", function () use ($request, $membro) {
            $dati = $request->validate([
                'al' => ['required', 'date'],
                'motivo' => ['required', Rule::in(array_keys(ConsiglioDirettivo::MOTIVI_FINE))],
                'nota' => ['nullable', 'string', 'max:1000'],
            ], ['motivo.required' => 'Scegli il motivo.'], ['al' => 'data']);
            $this->cd->togli($membro, Carbon::parse($dati['al']), $dati['motivo'], $dati['nota'] ?? null, $request->user());
            $s = $membro->socio;

            return "{$s->cognome} {$s->nome} non è più nel Consiglio Direttivo.";
        });
    }

    /** Esegue; con un errore torna alla pagina con la finestra aperta. */
    private function conModale(Request $request, string $modale, callable $azione)
    {
        try {
            $messaggio = $azione();
        } catch (ValidationException $e) {
            return redirect()->route('governance.index')->withErrors($e->errors())->withInput()->with('modale', $modale);
        }

        // Chi ha appena ceduto la sua carica (o è uscito con essa) può aver
        // perso l'accesso: niente pagina 403 (revisione R1.1)
        $chi = $request->user()->fresh();
        if (! $chi->attivo) {
            \Illuminate\Support\Facades\Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('success', $messaggio . ' Il tuo utente non ha più ruoli ed è stato disattivato.');
        }
        if (! $chi->puoAccedere('governance_cd')) {
            return redirect()->route('dashboard')->with('success', $messaggio);
        }

        return redirect()->route('governance.index')->with('success', $messaggio);
    }
}
