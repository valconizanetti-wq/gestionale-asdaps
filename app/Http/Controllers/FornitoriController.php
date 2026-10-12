<?php

namespace App\Http\Controllers;

use App\Models\ErogazioneLiberale;
use App\Models\PrimaNota;
use App\Models\PrimaNotaScontrino;
use App\Models\Soggetto;
use App\Scontrini\Fornitori;
use App\Scontrini\LettoreScontrino;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Fornitori (tavola SN-7, scontrini.md §5): l'elenco di gl_soggetti usato
 * dagli scontrini e dal campo «Fornitore» della Prima Nota. Correggere,
 * unire due doppioni, togliere dalla lista. Presidente e Tesoriere (chi
 * modifica la Prima Nota); gli altri ruoli con la sezione la consultano.
 *
 * I movimenti copiano il nome del fornitore: cambiare il nome o unire due
 * fornitori aggiorna anche i movimenti (in tutti gli anni, perché è una
 * descrizione, non un importo) e lascia una riga nell'Audit Log.
 */
class FornitoriController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $usi = PrimaNota::whereNotNull('soggetto')->selectRaw('soggetto, COUNT(*) n, MAX(data) ultimo')->groupBy('soggetto')->get()->keyBy('soggetto');
        $tutti = Soggetto::orderBy('nome')->get();

        $fornitori = $tutti
            ->when($q !== '', fn ($c) => $c->filter(fn ($s) => str_contains(mb_strtolower($s->nome), mb_strtolower($q)) || str_contains((string) $s->partita_iva, $q)))
            ->map(function ($s) use ($usi) {
                $s->usi = (int) ($usi[$s->nome]->n ?? 0);
                $s->ultimo = isset($usi[$s->nome]) ? \Illuminate\Support\Carbon::parse($usi[$s->nome]->ultimo) : null;

                return $s;
            })
            ->sortBy([['attivo', 'desc'], fn ($a, $b) => strcasecmp($a->nome, $b->nome)])
            ->values();

        // Possibili doppioni: stesso nome senza forme giuridiche e punteggiatura
        $doppioni = $tutti->groupBy(fn ($s) => Fornitori::normalizza($s->nome))
            ->filter(fn ($g, $k) => $k !== '' && $g->count() > 1)
            ->map(fn ($g) => $g->sortByDesc(fn ($s) => (int) ($usi[$s->nome]->n ?? 0))->values())
            ->values();

        return view('scontrini.fornitori', [
            'fornitori' => $fornitori,
            'doppioni' => $doppioni,
            'q' => $q,
            'puo' => $request->user()->puoModificare('prima_nota'),
            'usi' => $usi,
        ]);
    }

    public function store(Request $request)
    {
        $this->richiediModifica($request);
        $dati = $this->valida($request, null, 'nuovo');
        Soggetto::create($dati + ['attivo' => true]);

        return redirect()->route('scontrini.fornitori.index')->with('success', "Fornitore «{$dati['nome']}» aggiunto.");
    }

    public function update(Request $request, Soggetto $fornitore)
    {
        $this->richiediModifica($request);
        $dati = $this->valida($request, $fornitore, 'fornitore-' . $fornitore->id);
        $dati['attivo'] = $request->boolean('attivo');
        $prima = $fornitore->nome;

        DB::transaction(function () use ($fornitore, $dati, $prima) {
            $fornitore->update($dati);
            if ($prima !== $fornitore->nome) {
                $n = PrimaNota::where('soggetto', $prima)->update(['soggetto' => $fornitore->nome]);
                AuditLogService::scrivi([
                    'evento' => 'FORNITORE_RINOMINATO',
                    'entita_tipo' => 'Soggetto',
                    'entita_id' => $fornitore->id,
                    'livello_rischio' => 'MEDIUM',
                    'dettaglio' => "Fornitore n. {$fornitore->id} rinominato: aggiornati {$n} movimenti di Prima Nota.",
                    'old_values' => ['nome' => $prima],
                    'new_values' => ['nome' => $fornitore->nome],
                ]);
            }
        });

        return redirect()->route('scontrini.fornitori.index', array_filter(['q' => $request->input('q')]))->with('success', "Fornitore «{$fornitore->nome}» aggiornato.");
    }

    /** Unisce due doppioni: movimenti, scontrini ed erogazioni passano a quello che resta. */
    public function unisci(Request $request)
    {
        $this->richiediModifica($request);
        $dati = $request->validateWithBag('unisci', [
            'tieni' => ['required', 'integer', 'exists:gl_soggetti,id'],
            'togli' => ['required', 'integer', 'exists:gl_soggetti,id', 'different:tieni'],
        ], ['togli.different' => 'Scegli due fornitori diversi.']);

        $risultato = DB::transaction(function () use ($dati) {
            $tieni = Soggetto::lockForUpdate()->findOrFail($dati['tieni']);
            $togli = Soggetto::lockForUpdate()->findOrFail($dati['togli']);
            $movimenti = PrimaNota::where('soggetto', $togli->nome)->update(['soggetto' => $tieni->nome]);
            $scontrini = PrimaNotaScontrino::where('soggetto_id', $togli->id)->update(['soggetto_id' => $tieni->id]);
            ErogazioneLiberale::where('soggetto_id', $togli->id)->update(['soggetto_id' => $tieni->id]);

            // I dati che mancano a quello che resta si prendono dall'altro
            $piva = $togli->partita_iva;
            $completa = array_filter([
                'partita_iva' => $tieni->partita_iva ? null : $piva,
                'indirizzo' => $tieni->indirizzo ? null : $togli->indirizzo,
                'comune' => $tieni->comune ? null : $togli->comune,
            ]);
            Soggetto::withoutEvents(fn () => $togli->delete());
            if ($completa) {
                $tieni->update($completa);
            }
            AuditLogService::scrivi([
                'evento' => 'FORNITORI_UNITI',
                'entita_tipo' => 'Soggetto',
                'entita_id' => $tieni->id,
                'livello_rischio' => 'MEDIUM',
                'dettaglio' => "Fornitore n. {$togli->id} unito al n. {$tieni->id}: {$movimenti} movimenti e {$scontrini} scontrini spostati.",
                'old_values' => ['tolto' => $togli->nome, 'partita_iva' => $togli->partita_iva],
                'new_values' => ['resta' => $tieni->nome],
            ]);

            return [$tieni->nome, $togli->nome, $movimenti + $scontrini];
        });

        return redirect()->route('scontrini.fornitori.index')->with('success', "«{$risultato[1]}» unito a «{$risultato[0]}».");
    }

    /** Si elimina solo un fornitore mai usato; gli altri si tolgono dalla lista. */
    public function destroy(Request $request, Soggetto $fornitore)
    {
        $this->richiediModifica($request);
        $usato = PrimaNota::where('soggetto', $fornitore->nome)->exists()
            || PrimaNotaScontrino::where('soggetto_id', $fornitore->id)->exists()
            || ErogazioneLiberale::where('soggetto_id', $fornitore->id)->exists();
        if ($usato) {
            return back()->with('error', "«{$fornitore->nome}» è usato in Prima Nota: non si elimina, si toglie dalla lista (Modifica › «In elenco»).");
        }
        $nome = $fornitore->nome;
        $fornitore->delete();

        return redirect()->route('scontrini.fornitori.index')->with('success', "Fornitore «{$nome}» eliminato.");
    }

    private function valida(Request $request, ?Soggetto $attuale, string $bag): array
    {
        $dati = $request->validateWithBag($bag, [
            'nome' => ['required', 'string', 'max:200', Rule::unique('gl_soggetti', 'nome')->ignore($attuale?->id)],
            'partita_iva' => ['nullable', 'regex:/^\d{11}$/', Rule::unique('gl_soggetti', 'partita_iva')->ignore($attuale?->id), function ($a, $v, $fail) {
                if ($v && ! LettoreScontrino::partitaIvaValida($v)) {
                    $fail('La partita IVA non torna (la cifra di controllo è sbagliata).');
                }
            }],
            'indirizzo' => ['nullable', 'string', 'max:200'],
            'comune' => ['nullable', 'string', 'max:100'],
        ], [
            'nome.unique' => 'C\'è già un fornitore con questo nome: se è lo stesso, uniscili.',
            'partita_iva.unique' => 'Questa partita IVA è già di un altro fornitore: se è lo stesso, uniscili.',
            'partita_iva.regex' => 'La partita IVA ha 11 cifre.',
        ], ['nome' => 'ragione sociale']);
        $dati['nome'] = trim(preg_replace('/\s+/u', ' ', $dati['nome']));

        return $dati + ['partita_iva' => null, 'indirizzo' => null, 'comune' => null];
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('prima_nota'), 403, 'Correggono i fornitori il Presidente e il Tesoriere.');
    }
}
