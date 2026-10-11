<?php

namespace App\Http\Controllers;

use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\Scadenza;
use App\Services\RinnoviQuota;
use App\Services\Scadenzario;
use App\Support\Importo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Scadenze dell'associazione (scadenzario.md, tavola SC-1). Rotte sotto
 * 'permesso:scadenzario': Presidente, Segretario e Tesoriere modificano, il
 * Revisore consulta. L'uscita in Prima Nota con «Rinnovata» solo per chi
 * modifica anche la Prima Nota (Presidente e Tesoriere).
 */
class ScadenzarioController extends Controller
{
    use Concerns\Giustificativi;

    public function __construct(private Scadenzario $scadenzario)
    {
    }

    public function index(Request $request)
    {
        $tutte = Scadenza::with(['categoriaPrimaNota', 'ultimoRinnovo.movimento'])->orderBy('data_scadenza')->orderBy('titolo')->get();

        return view('scadenzario.index', [
            'ricorrenti' => $tutte->filter(fn ($s) => $s->ricorrente()),
            'unaTantum' => $tutte->filter(fn ($s) => ! $s->ricorrente() && (! $s->completato || $s->completato_il?->gte(today()->subYear()))),
            'avviso' => Scadenzario::avvisoAffiliazione(),
            'puo' => $request->user()->puoModificare('scadenzario'),
            'puoPrimaNota' => $request->user()->puoModificare('prima_nota'),
            'annoAvvio' => RinnoviQuota::annoAvvio(),
            'tenuti' => self::fileTenuti(),
        ]);
    }

    public function create(Request $request)
    {
        $this->richiediModifica($request);

        return view('scadenzario.form', $this->datiModulo(new Scadenza([
            'tipo' => 'ricorrente', 'categoria' => 'altro', 'giorni_preavviso' => 30,
        ])) + ['puo' => true]);
    }

    public function store(Request $request)
    {
        $this->richiediModifica($request);
        $s = Scadenza::create($this->valida($request, null) + ['creato_da' => $request->user()->id]);

        return redirect()->route('scadenzario.index')->with('success', "Scadenza «{$s->titolo}» aggiunta.");
    }

    public function edit(Request $request, Scadenza $scadenza)
    {
        $scadenza->load(['categoriaPrimaNota', 'rinnovi' => fn ($q) => $q->orderByDesc('fatto_il')->orderByDesc('id'), 'rinnovi.movimento', 'rinnovi.registratoDa']);

        return view('scadenzario.form', $this->datiModulo($scadenza) + ['puo' => $request->user()->puoModificare('scadenzario')]);
    }

    public function update(Request $request, Scadenza $scadenza)
    {
        $this->richiediModifica($request);
        $scadenza->update($this->valida($request, $scadenza));
        $this->scadenzario->copiaNellEnte($scadenza);

        return redirect()->route('scadenzario.index')->with('success', "Scadenza «{$scadenza->titolo}» salvata.");
    }

    public function destroy(Request $request, Scadenza $scadenza)
    {
        $this->richiediModifica($request);
        if ($scadenza->chiave) {
            return back()->with('error', "«{$scadenza->titolo}» la usa il gestionale (riquadri e avvisi): non si elimina. Si può cambiare la data.");
        }
        if ($scadenza->rinnovi()->exists()) {
            return back()->with('error', "«{$scadenza->titolo}» ha già dei rinnovi registrati: non si elimina, resta come storico.");
        }
        $titolo = $scadenza->titolo;
        $scadenza->delete();

        return redirect()->route('scadenzario.index')->with('success', "Scadenza «{$titolo}» eliminata.");
    }

    /** «Rinnovata» (pagamento) o «Fatta» (adempimento): finestra della tavola SC-1. */
    public function rinnova(Request $request, Scadenza $scadenza)
    {
        $this->richiediModifica($request);
        $movimento = $request->boolean('movimento') && $scadenza->conPagamento();
        if ($movimento && ! $request->user()->puoModificare('prima_nota')) {
            abort(403, 'Il tuo ruolo non registra uscite in Prima Nota.');
        }
        $erroriFile = $movimento ? $this->trattieniFile($request) : [];
        // L'importo si legge all'italiana; nel modulo, dopo un errore, torna
        // com'era scritto (withInput usa la richiesta, non questa copia)
        $dati = $request->all();
        $dati['importo'] = $request->filled('importo') ? Importo::leggi($request->input('importo')) ?? $request->input('importo') : null;

        $validatore = validator($dati, [
            'per_scadenza' => ['required', 'date_format:Y-m-d'],
            'fatto_il' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'importo' => [Rule::requiredIf($movimento), 'nullable', Importo::regola()],
            'conto' => [Rule::requiredIf($movimento), 'nullable', Rule::in(array_keys(PrimaNota::CONTI))],
            'prossima_scadenza' => [Rule::requiredIf($scadenza->ricorrente()), 'nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'fatto_il.before_or_equal' => 'La data non può essere nel futuro.',
            'importo.required' => 'Scrivi l\'importo pagato, per l\'uscita in Prima Nota.',
            'prossima_scadenza.required' => 'Scrivi la prossima scadenza.',
        ], ['fatto_il' => $scadenza->conPagamento() ? 'data del pagamento' : 'data', 'prossima_scadenza' => 'prossima scadenza']);

        if ($validatore->fails() || $erroriFile) {
            $errori = $validatore->errors();
            foreach ($erroriFile as $e) {
                $errori->add('documenti', $e);
            }

            return back()->withInput()->withErrors($errori, 'rinnovo')->with('rinnovo_aperto', $scadenza->id);
        }
        $d = $validatore->validated();

        try {
            $r = $this->archiviaInSicurezza(fn () => $this->scadenzario->rinnova($scadenza, $d + ['movimento' => $movimento],
                $request->user(), fn (PrimaNota $m) => $this->salvaFile($m, (array) $request->input('file_temp', []), $request)));
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['rinnovo' => $e->getMessage()], 'rinnovo')->with('rinnovo_aperto', $scadenza->id);
        }
        if ($movimento) {
            $this->liberaFile($request, (array) $request->input('file_temp', []));
        }

        $s = $scadenza->fresh();
        $messaggio = $s->ricorrente()
            ? "«{$s->titolo}» " . ($s->conPagamento() ? 'rinnovata' : 'fatta') . ": prossima scadenza il {$s->data_scadenza->format('d/m/Y')}"
            : "«{$s->titolo}» segnata come fatta";
        $messaggio .= $r->prima_nota_id ? ", uscita n. {$r->prima_nota_id} in Prima Nota." : ($s->conPagamento() ? ', senza uscita in Prima Nota.' : '.');

        return redirect()->route('scadenzario.index')->with('success', $messaggio);
    }

    public function annulla(Request $request, Scadenza $scadenza)
    {
        $this->richiediModifica($request);
        $motivo = $request->validate([
            'motivo' => ['required', 'string', 'min:15', 'max:1000'],
        ], ['motivo.required' => 'Scrivi perché annulli il rinnovo.', 'motivo.min' => 'La motivazione deve avere almeno 15 caratteri.'])['motivo'];

        $r = $scadenza->rinnovi()->orderByDesc('id')->first();
        if ($r?->prima_nota_id && ! $request->user()->puoModificare('prima_nota')) {
            return back()->with('error', 'Il rinnovo ha un\'uscita in Prima Nota: lo annulla il Presidente o il Tesoriere.');
        }
        try {
            $this->scadenzario->annullaUltimo($scadenza, $motivo);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('scadenzario.edit', $scadenza)->with('success', "Ultimo rinnovo annullato: «{$scadenza->titolo}» torna a scadere il {$scadenza->fresh()->data_scadenza->format('d/m/Y')}.");
    }

    // ----------------------------------------------------------------

    private function datiModulo(Scadenza $s): array
    {
        return [
            's' => $s,
            'categoriePn' => PrimaNotaCategoria::where('tipo', 'uscita')->where(fn ($q) => $q->where('attiva', true)->orWhere('id', $s->prima_nota_categoria_id))
                ->where(fn ($q) => $q->whereNull('uso')->orWhere('uso', 'movimenti'))
                ->orderBy('ordine')->pluck('nome', 'id')->all(),
        ];
    }

    private function valida(Request $request, ?Scadenza $s): array
    {
        $categorie = array_keys(Scadenza::CATEGORIE);
        if ($s?->categoria !== 'runts') {
            $categorie = array_values(array_diff($categorie, ['runts']));
        }
        $d = $request->validate([
            'titolo' => ['required', 'string', 'max:255'],
            'descrizione' => ['nullable', 'string', 'max:1000'],
            'tipo' => ['required', Rule::in(['ricorrente', 'una_tantum'])],
            'categoria' => ['required', Rule::in($categorie)],
            // Solo uscite usabili nei movimenti (non «solo bilancio» né di sistema)
            'prima_nota_categoria_id' => ['nullable', 'integer', Rule::exists('gl_prima_nota_categorie', 'id')->where('tipo', 'uscita')
                ->where(fn ($q) => $q->whereNull('uso')->orWhere('uso', 'movimenti'))],
            'data_scadenza' => ['required', 'date_format:Y-m-d'],
            'giorni_preavviso' => ['required', 'integer', 'between:0,365'],
        ], [], ['data_scadenza' => 'data', 'giorni_preavviso' => 'preavviso', 'prima_nota_categoria_id' => 'categoria dell\'uscita']);

        // Una scadenza con dei rinnovi resta del suo tipo: lo storico ne dipende
        if ($s && $s->tipo !== $d['tipo'] && $s->rinnovi()->exists()) {
            throw ValidationException::withMessages(['tipo' => 'La scadenza ha già dei rinnovi: il tipo non cambia.']);
        }
        $data = Carbon::parse($d['data_scadenza']);

        return $d + [
            'giorno_ricorrenza' => $d['tipo'] === 'ricorrente' ? $data->day : null,
            'mese_ricorrenza' => $d['tipo'] === 'ricorrente' ? $data->month : null,
        ];
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('scadenzario'), 403, 'Il tuo ruolo consulta lo Scadenzario ma non lo modifica.');
    }
}
