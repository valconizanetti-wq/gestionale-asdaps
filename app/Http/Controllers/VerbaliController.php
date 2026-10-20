<?php

namespace App\Http\Controllers;

use App\Models\Consultazione;
use App\Models\OrganoDocumento;
use App\Models\Seduta;
use App\Models\Verbale;
use App\Models\VerbaleRettifica;
use App\Services\DocumentiOrgani;
use App\Services\Riunioni;
use App\Services\Verbali;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Libri dei verbali (v0.44.0, specifiche/verbali.md): VB-1 elenco per libro
 * e anno, VB-2 verbale della riunione per punti, VB-3 scheda (definitivo,
 * copia firmata, rettifica, documenti), VB-5 verbale registrato da fuori.
 *
 * Rotte nel gruppo 'permesso:verbali': leggono il CD, il Revisore e
 * l'Amministratore; scrivono il Presidente e il Segretario
 * (puoModificare('verbali'), analisi V12).
 */
class VerbaliController extends Controller
{
    public function __construct(private Verbali $verbali, private Riunioni $riunioni, private DocumentiOrgani $documenti)
    {
    }

    // ----------------------------------------------------------------
    // VB-1
    // ----------------------------------------------------------------

    public function index(Request $request): View
    {
        $libro = $request->query('libro') === 'assemblee' ? 'assemblee' : 'cd';
        $anni = Verbale::nelLibro()->where('libro', $libro)->whereNotNull('data_seduta')
            ->selectRaw('DISTINCT YEAR(data_seduta) AS a')->pluck('a')->map(fn ($a) => (int) $a)
            ->push(now()->year)->unique()->sortDesc()->values();
        $anno = (int) $request->query('anno', now()->year);
        $anno = $anni->contains($anno) ? $anno : now()->year;

        $verbali = Verbale::nelLibro()->where('libro', $libro)
            ->where(fn ($q) => $q->where('anno_libro', $anno)->orWhere(fn ($w) => $w->whereNull('anno_libro')->whereYear('data_seduta', $anno)))
            ->with('domanda:id,nome,cognome,is_test', 'seduta:id,stato')->withCount('rettifiche')
            ->orderByRaw('numero_libro IS NULL DESC')->orderByDesc('numero_libro')->orderByDesc('data_seduta')->orderByDesc('id')
            ->get();

        $ammissioniChiuse = Verbale::with('domanda')->nelLibro()->where('tipo', 'cd_ammissione')->where('stato', 'bozza')->get()->filter->ammissioneChiusa();

        return view('verbali.index', [
            'libro' => $libro,
            'anno' => $anno,
            'anni' => $anni,
            'verbali' => $verbali,
            'ammissioniChiuse' => $ammissioniChiuse->count(),
            'riunioni' => $libro === 'cd'
                ? Seduta::where('tipo', 'cd')->where(fn ($q) => $q->whereIn('stato', ['bozza', 'convocata', 'rinviata'])
                    ->orWhere(fn ($w) => $w->where('stato', 'svolta')->whereDoesntHave('verbale')))
                    ->orderBy('data')->get()
                : collect(),
            'consultazioni' => $libro === 'cd' ? Consultazione::whereIn('stato', ['bozza', 'in_corso'])->withCount(['voti', 'voti as votati_count' => fn ($q) => $q->whereNotNull('voto')])->orderBy('termine')->get() : collect(),
            'conti' => [
                'bozze' => $verbali->where('stato', 'bozza')->count(),
                'da_firmare' => $verbali->where('stato', 'definitivo')->count(),
                'firmati' => $verbali->where('stato', 'firmato')->count(),
            ],
        ]);
    }

    public function rendiDefinitiveAmmissioni(Request $request): RedirectResponse
    {
        $this->richiediModifica($request);
        $n = $this->verbali->rendiDefinitiveAmmissioni($request->user());

        return back()->with('success', $n === 1 ? 'Il verbale della domanda è definitivo, con il suo numero.' : "{$n} verbali delle domande sono definitivi, con i loro numeri.");
    }

    // ----------------------------------------------------------------
    // VB-3
    // ----------------------------------------------------------------

    public function show(Verbale $verbale): View
    {
        $verbale->load(['punti.voti', 'seduta.presenze', 'consultazione.voti', 'domanda', 'rettifiche.creataDa', 'definitivoDa', 'firmatoDa', 'redattoDa']);
        abort_if($verbale->domanda?->is_test, 404);

        return view('verbali.show', [
            'v' => $verbale,
            'documenti' => $verbale->documenti(),
            'mancanze' => $verbale->isBozza() ? Verbali::mancanze($verbale) : [],
        ]);
    }

    /** PDF: il definitivo salvato, oppure l'anteprima della bozza (con la scritta BOZZA). */
    public function pdf(Verbale $verbale)
    {
        abort_if($verbale->domanda?->is_test, 404);
        $nome = Verbali::nomeFile($verbale) . '.pdf';
        if ($verbale->isImmutabile() && $verbale->pdf_path && Storage::disk('local')->exists($verbale->pdf_path)) {
            return Storage::disk('local')->response($verbale->pdf_path, $nome, ['Content-Type' => 'application/pdf'], 'inline');
        }
        abort_unless($verbale->isBozza(), 404, 'Il PDF di questo verbale non c\'è: è stato registrato da fuori, c\'è solo la copia firmata.');

        return response($this->verbali->pdf($verbale), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="' . $nome . '"']);
    }

    public function definitivo(Request $request, Verbale $verbale): RedirectResponse
    {
        $this->richiediModifica($request);
        $v = $this->verbali->rendiDefinitivo($verbale, $request->user());

        return redirect()->route('verbali.show', $v)->with('success', "Verbale definitivo: è il n. {$v->numeroTesto()} del libro. Ora fallo firmare digitalmente e carica la copia firmata.");
    }

    public function firmato(Request $request, Verbale $verbale): RedirectResponse
    {
        $this->richiediModifica($request);
        $request->validateWithBag('firmato', [
            'firmato' => ['required', 'file', 'max:' . Verbali::MAX_KB_FIRMATO],
            'dichiarazione' => ['nullable', 'in:entrambi,segretario'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ], [
            'firmato.required' => 'Scegli il file firmato.',
            'firmato.max' => 'Il file firmato può pesare al massimo 10 MB.',
            'firmato.uploaded' => 'Il file non è arrivato per intero: riprova.',
        ]);
        $this->conBag('firmato', fn () => $this->verbali->caricaFirmato($verbale, $request->file('firmato'), $request->input('dichiarazione'), $request->input('motivo'), $request->user()));

        return redirect()->route('verbali.show', $verbale)->with('success', 'Copia firmata caricata: il verbale è firmato.');
    }

    public function vediFirmato(Verbale $verbale)
    {
        abort_unless($verbale->verbale_firmato_path && Storage::disk('local')->exists($verbale->verbale_firmato_path), 404);
        $ext = pathinfo($verbale->verbale_firmato_path, PATHINFO_EXTENSION);

        return $ext === 'pdf'
            ? Storage::disk('local')->response($verbale->verbale_firmato_path, $verbale->firmato_nome, ['Content-Type' => 'application/pdf'], 'inline')
            : Storage::disk('local')->download($verbale->verbale_firmato_path, $verbale->firmato_nome);
    }

    // ----------------------------------------------------------------
    // Rettifica
    // ----------------------------------------------------------------

    public function rettifica(Request $request, Verbale $verbale): RedirectResponse
    {
        $this->richiediModifica($request);
        $d = $request->validateWithBag('rettifica', [
            'testo_html' => ['required', 'string', 'max:60000'],
            'motivo' => ['required', 'string', 'min:15', 'max:500'],
        ], [
            'testo_html.required' => 'Scrivi il testo della rettifica.',
            'motivo.required' => 'Scrivi il motivo della rettifica.',
            'motivo.min' => 'Il motivo deve avere almeno :min caratteri.',
        ]);
        $r = $this->conBag('rettifica', fn () => $this->verbali->rettifica($verbale, $d['testo_html'], $d['motivo'], $request->user()));

        return redirect()->route('verbali.show', $verbale)->with('success', "Rettifica n. {$r->numero} aggiunta in fondo al verbale. Falla firmare e carica la copia firmata.");
    }

    public function pdfRettifica(Verbale $verbale, VerbaleRettifica $rettifica, ?string $parte = null)
    {
        abort_unless($rettifica->verbale_id === $verbale->id, 404);
        $path = $parte === 'firmata' ? $rettifica->firmato_path : $rettifica->pdf_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        $nome = $parte === 'firmata' ? $rettifica->firmato_nome : basename($path);

        return str_ends_with($path, '.pdf')
            ? Storage::disk('local')->response($path, $nome, ['Content-Type' => 'application/pdf'], 'inline')
            : Storage::disk('local')->download($path, $nome);
    }

    public function firmataRettifica(Request $request, Verbale $verbale, VerbaleRettifica $rettifica): RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($rettifica->verbale_id === $verbale->id, 404);
        $request->validateWithBag('rettifica_firmata', [
            'firmato_rettifica' => ['required', 'file', 'max:' . Verbali::MAX_KB_FIRMATO],
        ], ['firmato_rettifica.required' => 'Scegli il file firmato.', 'firmato_rettifica.max' => 'Il file può pesare al massimo 10 MB.']);
        $this->conBag('rettifica_firmata', fn () => $this->verbali->caricaFirmataRettifica($rettifica, $request->file('firmato_rettifica'), $request->user()));

        return redirect()->route('verbali.show', $verbale)->with('success', "Copia firmata della rettifica n. {$rettifica->numero} caricata.");
    }

    // ----------------------------------------------------------------
    // VB-2: verbale della riunione per punti
    // ----------------------------------------------------------------

    public function scrivi(Request $request, Verbale $verbale): View|RedirectResponse
    {
        $this->richiediModifica($request);
        if (! $verbale->isBozza() || $verbale->tipo !== 'cd_riunione' || ! $verbale->seduta) {
            return redirect()->route('verbali.show', $verbale);
        }
        $verbale->load(['punti.voti', 'seduta.presenze']);
        $s = $verbale->seduta;

        return view('verbali.scrivi', [
            'v' => $verbale,
            's' => $s,
            'presenti' => $s->presenze->filter->presente()->values(),
            'costituita' => Riunioni::costituita($s),
            'assenze' => Riunioni::assenzeConsecutive($s),
            'documenti' => $verbale->documenti(),
        ]);
    }

    public function salva(Request $request, Verbale $verbale): RedirectResponse
    {
        $this->richiediModifica($request);
        $request->validate([
            'punti' => ['nullable', 'array'],
            'punti.*.discussione_html' => ['nullable', 'string', 'max:60000'],
            'punti.*.ha_delibera' => ['nullable', 'boolean'],
            'punti.*.tipo_delibera' => ['nullable', 'string'],
            'punti.*.delibera_html' => ['nullable', 'string', 'max:60000'],
            'punti.*.voti' => ['nullable', 'array'],
            'ora_chiusura' => ['nullable', 'date_format:H:i'],
            ...DocumentiOrgani::regole(),
        ], DocumentiOrgani::messaggi() + ['ora_chiusura.date_format' => 'Scrivi l\'ora come 21:30.']);

        $scritti = [];
        try {
            DB::transaction(function () use ($request, $verbale, &$scritti) {
                $this->riunioni->salvaVerbale($verbale, (array) $request->input('punti', []), $request->input('ora_chiusura'), $request->user());
                $scritti = $this->documenti->salva('verbale', $verbale->id, $request->file('documenti'), $request->user());
            });
        } catch (\Throwable $e) {
            DocumentiOrgani::cancellaFile($scritti);
            throw $e;
        }

        return $request->input('dopo') === 'scheda'
            ? redirect()->route('verbali.show', $verbale)->with('success', 'Verbale salvato.')
            : redirect()->route('verbali.scrivi', $verbale)->with('success', 'Verbale salvato: esiti ricalcolati.');
    }

    // ----------------------------------------------------------------
    // Documenti
    // ----------------------------------------------------------------

    public function documento(Verbale $verbale, OrganoDocumento $documento)
    {
        abort_unless($documento->allegabile_type === 'verbale' && $documento->allegabile_id === $verbale->id, 404);
        abort_unless(Storage::disk('local')->exists($documento->path), 404);

        return Storage::disk('local')->response($documento->path, $documento->nome, ['Content-Type' => 'application/pdf'], 'inline');
    }

    public function aggiungiDocumenti(Request $request, Verbale $verbale): RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($verbale->isBozza(), 403, 'I documenti si aggiungono finché il verbale è in bozza.');
        $request->validateWithBag('documenti', DocumentiOrgani::regole(), DocumentiOrgani::messaggi());
        $this->documenti->salva('verbale', $verbale->id, $request->file('documenti'), $request->user());

        return back()->with('success', 'Documenti aggiunti.');
    }

    public function eliminaDocumento(Request $request, Verbale $verbale, OrganoDocumento $documento): RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($documento->allegabile_type === 'verbale' && $documento->allegabile_id === $verbale->id, 404);
        abort_unless($verbale->isBozza(), 403, 'Un documento si toglie finché il verbale è in bozza.');
        $this->documenti->elimina($documento);

        return back()->with('success', 'Documento tolto.');
    }

    // ----------------------------------------------------------------
    // VB-5: registra un verbale già redatto
    // ----------------------------------------------------------------

    public function registra(Request $request): View
    {
        $this->richiediModifica($request);
        $libro = $request->query('libro') === 'cd' ? 'cd' : 'assemblee';

        return view('verbali.registra', [
            'libro' => $libro,
            'proposti' => ['cd' => Verbali::prossimoNumero('cd', now()->year), 'assemblee' => Verbali::prossimoNumero('assemblee', now()->year)],
        ]);
    }

    public function salvaRegistrato(Request $request): RedirectResponse
    {
        $this->richiediModifica($request);
        $d = $request->validate([
            'libro' => ['required', Rule::in(array_keys(Verbale::LIBRI))],
            'organo' => ['nullable', 'required_if:libro,assemblee', Rule::in(['ordinaria', 'straordinaria'])],
            'data' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'presidente' => ['required', 'string', 'max:150'],
            'segretario' => ['required', 'string', 'max:150'],
            'presenti' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'titolo' => ['required', 'string', 'max:255'],
            'delibere_html' => ['required', 'string', 'max:60000'],
            'numero' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'approva_rendiconto' => ['nullable', 'boolean'],
            'approva_rendiconto_anno' => ['nullable', 'required_if:approva_rendiconto,1', 'integer', 'min:2000', 'max:2100'],
            'firmato' => ['required', 'file', 'max:' . Verbali::MAX_KB_FIRMATO],
            'dichiarazione' => ['nullable', 'in:entrambi,segretario'],
            ...DocumentiOrgani::regole(),
        ], DocumentiOrgani::messaggi() + [
            'organo.required_if' => 'Scegli il tipo di Assemblea.',
            'data.before_or_equal' => 'La data della seduta non può essere nel futuro.',
            'delibere_html.required' => 'Scrivi in breve le delibere.',
            'firmato.required' => 'Carica la copia firmata digitalmente.',
            'firmato.max' => 'Il file firmato può pesare al massimo 10 MB.',
            'approva_rendiconto_anno.required_if' => 'Scrivi l\'anno del rendiconto approvato.',
        ], ['presidente' => 'presidente della seduta', 'segretario' => 'segretario verbalizzante']);
        $d['approva_rendiconto_anno'] = ! empty($d['approva_rendiconto']) ? (int) $d['approva_rendiconto_anno'] : null;
        $d['organo'] ??= 'ordinaria';

        $v = $this->verbali->registraDaFuori($d, $request->file('firmato'), $request->file('documenti'), $request->user());

        return redirect()->route('verbali.show', $v)->with('success', "Verbale registrato nel libro con il n. {$v->numeroTesto()}.");
    }

    // ----------------------------------------------------------------

    /** Errori del servizio nella «bag» del modulo che li ha causati. */
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
        abort_unless($request->user()->puoModificare('verbali'), 403, 'Il tuo ruolo può solo consultare i verbali.');
    }
}
