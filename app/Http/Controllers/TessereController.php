<?php

namespace App\Http\Controllers;

use App\Models\PrimaNota;
use App\Models\Socio;
use App\Models\Tesseramento;
use App\Models\TesseraPacchetto;
use App\Models\TesseraTipo;
use App\Services\AuditLogService;
use App\Services\CaricoTessereCsen;
use App\Services\RinnoviQuota;
use App\Services\Tessere;
use App\Support\Importo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Tessere CSEN (analisi-tessere-scadenze.md, tavole TS-1…TS-5, v0.37.0).
 * Rotte sotto 'permesso:tesseramento': Presidente, Segretario e Tesoriere
 * modificano, il Revisore consulta (puoModificare('tesseramento')).
 */
class TessereController extends Controller
{
    use Concerns\Giustificativi;

    public const FILTRI = [
        'valida' => 'Valide',
        'in_scadenza' => 'In scadenza',
        'scaduta' => 'Scadute',
        'portale' => 'Da inserire sul portale CSEN',
    ];

    /** File del CSV in attesa di conferma: in sessione il percorso, il file in tmp/. */
    private const SESSIONE_CARICO = 'tessere_carico';

    public function __construct(private Tessere $tessere)
    {
    }

    // ----------------------------------------------------------------
    // Pagina Tessere (TS-1, TS-5)
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $oggi = today();
        $anno = $oggi->year;
        $soci = Socio::where('stato', 'attivo')->where('pseudonimizzato', false)->orderBy('cognome')->orderBy('nome')
            ->get(['id', 'cognome', 'nome', 'data_nascita', 'tipo']);
        $situazioni = Tessere::perSoci($soci->pluck('id'), $oggi);

        // Da rinnovare: in scadenza, scadute, mai tesserati, con data di nascita/quota
        $daRinnovare = $soci->filter(fn ($s) => in_array($situazioni[$s->id]['stato'], ['in_scadenza', 'scaduta', 'mancante', 'futura'], true))
            ->sortBy(fn ($s) => [
                ['scaduta' => 0, 'mancante' => 1, 'in_scadenza' => 2, 'futura' => 3][$situazioni[$s->id]['stato']],
                $situazioni[$s->id]['tessera']?->scadenza?->toDateString() ?? '',
            ])->values();

        $tipi = TesseraTipo::ordinati()->get();
        $pacchetti = TesseraPacchetto::with(['tipo', 'movimento:id'])
            ->orderByDesc('acquistato_il')->orderByDesc('id')->get()
            // Numeri liberi: contano anche le tessere caricate con un numero del pacchetto
            ->each(fn ($p) => $p->setAttribute('liberi', Tessere::disponibili($p)));
        $disponibiliPerTipo = $tipi->mapWithKeys(fn ($t) => [$t->id => $pacchetti->where('tipo_id', $t->id)->sum('liberi')])->filter();

        $valide = collect($situazioni)->whereIn('stato', ['valida', 'in_scadenza'])->count();
        $inScadenza = collect($situazioni)->where('stato', 'in_scadenza')->count();
        $mancanti = collect($situazioni)->where('stato', 'mancante')->count();

        return view('tessere.index', [
            'oggi' => $oggi,
            'anno' => $anno,
            'kpi' => [
                'soci' => $soci->count(),
                'valide' => $valide,
                'in_scadenza' => $inScadenza,
                'senza' => $soci->count() - $valide,
                'mancanti' => $mancanti,
                'disponibili' => $disponibiliPerTipo->sum(),
            ] + Tessere::costiAnno($anno),
            'daRinnovare' => $daRinnovare,
            'situazioni' => $situazioni,
            'quote' => $this->quoteDellAnno($daRinnovare, $anno),
            'tipi' => $tipi,
            'pacchetti' => $pacchetti,
            'disponibiliPerTipo' => $disponibiliPerTipo,
            'elenco' => $this->elenco($request),
            'filtro' => $this->filtro($request),
            'candidati' => $this->candidati($soci, $situazioni),
            'liberi' => $this->liberi($tipi),
            'puoModificare' => $request->user()->puoModificare('tesseramento'),
            'vediSoci' => $request->user()->puoAccedere('soci'),
            'apri' => $request->integer('emetti') ?: null,
        ]);
    }

    // ----------------------------------------------------------------
    // Emissione e rinnovo (TS-3)
    // ----------------------------------------------------------------

    public function store(Request $request)
    {
        $this->richiediModifica($request);
        $dati = $request->validateWithBag('emissione', [
            'socio_id' => ['required', 'integer', Rule::exists('gl_soci', 'id')->where('stato', 'attivo')->where('pseudonimizzato', false)],
            'tipo_id' => ['required', 'integer', Rule::exists('gl_tessere_tipi', 'id')],
            'numero' => ['required', 'string', 'max:30'],
            'data_rilascio' => ['required', 'date_format:Y-m-d', 'after_or_equal:' . today()->subYear()->toDateString(), 'before_or_equal:' . today()->addDays(60)->toDateString()],
            'portale' => ['nullable', 'boolean'],
            'conferma' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'socio_id.required' => 'Scegli il socio.',
            'socio_id.exists' => 'Scegli un socio attivo.',
            'tipo_id.required' => 'Scegli il tipo di tessera.',
            'numero.required' => 'Scrivi il numero della tessera.',
            'data_rilascio.after_or_equal' => 'L\'emissione non può essere di più di un anno fa: per le tessere già emesse usa «Carica tessere già emesse».',
            'data_rilascio.before_or_equal' => 'L\'emissione può essere al massimo tra 60 giorni.',
        ], ['data_rilascio' => 'data di emissione']);

        try {
            $t = $this->tessere->emetti(Socio::findOrFail($dati['socio_id']), [
                'tipo_id' => (int) $dati['tipo_id'],
                'numero' => $dati['numero'],
                'data_rilascio' => $dati['data_rilascio'],
                'portale' => $request->boolean('portale'),
                'conferma' => $request->boolean('conferma'),
                'note' => $dati['note'] ?? null,
            ], $request->user());
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['numero' => $e->getMessage()], 'emissione')
                ->with('serve_conferma', $e instanceof \App\Services\TesseraSenzaQuota);
        }

        $s = $t->socio;
        $messaggio = "Tessera {$t->numero_tessera_csen} di {$s->cognome} {$s->nome} emessa: vale dal {$t->data_rilascio->format('d/m/Y')} al {$t->scadenza->format('d/m/Y')}."
            . ($t->portale_csen_il ? '' : ' Ricordati di inserirla sul portale CSEN.');

        return $request->input('da') === 'socio' && $request->user()->puoAccedere('soci')
            ? redirect()->to(route('soci.show', $s) . '#tessera')->with('success', $messaggio)
            : redirect()->route('tesseramento.index')->with('success', $messaggio);
    }

    /** Spunta «inserita sul portale CSEN» dall'elenco. */
    public function portale(Request $request, Tesseramento $tessera)
    {
        $this->richiediModifica($request);
        $tessera->forceFill(['portale_csen_il' => $tessera->portale_csen_il ? null : now()])->save();

        return back()->with('success', $tessera->portale_csen_il
            ? "Tessera {$tessera->numero_tessera_csen}: inserita sul portale CSEN."
            : "Tessera {$tessera->numero_tessera_csen}: di nuovo da inserire sul portale CSEN.");
    }

    public function destroy(Request $request, Tesseramento $tessera)
    {
        $this->richiediModifica($request);
        $dati = $request->validateWithBag('elimina' . $tessera->id, [
            'motivo' => ['required', 'string', 'min:15', 'max:1000'],
        ], ['motivo.required' => 'Scrivi perché elimini la tessera.', 'motivo.min' => 'La motivazione deve avere almeno 15 caratteri.']);

        try {
            $this->tessere->elimina($tessera, $dati['motivo']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Tessera {$tessera->numero_tessera_csen} eliminata: il numero torna libero.");
    }

    // ----------------------------------------------------------------
    // Pacchetti (TS-2)
    // ----------------------------------------------------------------

    public function createPacchetto(Request $request)
    {
        $this->richiediModifica($request);
        $tipi = TesseraTipo::ordinati()->where('attivo', true)->get();

        return view('tessere.pacchetto', [
            'p' => null,
            'tipi' => $tipi,
            'prezzi' => $this->prezziProposti($tipi),
            'annoAvvio' => RinnoviQuota::annoAvvio(),
            'tenuti' => self::fileTenuti(),
        ]);
    }

    public function storePacchetto(Request $request)
    {
        $this->richiediModifica($request);
        $erroriFile = $this->trattieniFile($request);
        $d = $this->validaPacchetto($request);
        $movimento = $request->boolean('movimento');
        $extra = $request->validate([
            'pagato_il' => [Rule::requiredIf($movimento), 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'conto' => [Rule::requiredIf($movimento), 'nullable', Rule::in(array_keys(PrimaNota::CONTI))],
        ], ['pagato_il.before_or_equal' => 'Il pagamento non può essere nel futuro.'], ['pagato_il' => 'data del pagamento']);
        if ($erroriFile) {
            return back()->withInput()->withErrors(['documenti' => $erroriFile]);
        }

        try {
            [$p, $m, $collegate] = $this->archiviaInSicurezza(fn () => $this->tessere->registraPacchetto($d + [
                'movimento' => $movimento,
                'pagato_il' => $extra['pagato_il'] ?? null,
                'conto' => $extra['conto'] ?? null,
            ], $request->user(), fn (PrimaNota $m) => $this->salvaFile($m, (array) $request->input('file_temp', []), $request)));
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $this->liberaFile($request, (array) $request->input('file_temp', []));

        $messaggio = "Pacchetto {$p->tipo->nome} {$p->intervallo()} registrato: {$p->quantita()} tessere, " . Importo::it($p->totale, true)
            . ($m ? ", uscita n. {$m->id} in Prima Nota" : ', senza movimento (da collegare a mano)')
            . ($collegate ? "; {$collegate} tessere già caricate collegate al pacchetto" : '') . '.';

        return redirect()->route('tesseramento.index')->with('success', $messaggio);
    }

    public function editPacchetto(Request $request, TesseraPacchetto $pacchetto)
    {
        $this->richiediModifica($request);
        $pacchetto->load('tipo', 'movimento')->setAttribute('usate', count(Tessere::usati($pacchetto)));

        return view('tessere.pacchetto', [
            'p' => $pacchetto,
            'tipi' => collect([$pacchetto->tipo]),
            'prezzi' => [],
            'annoAvvio' => RinnoviQuota::annoAvvio(),
            'tenuti' => [],
        ]);
    }

    public function updatePacchetto(Request $request, TesseraPacchetto $pacchetto)
    {
        $this->richiediModifica($request);
        $request->merge(['tipo_id' => $pacchetto->tipo_id]);   // il tipo non cambia
        $d = $this->validaPacchetto($request);
        $conTessere = (bool) Tessere::usati($pacchetto);   // anche tessere caricate con un suo numero
        $motivo = $request->validate([
            'motivo' => [Rule::requiredIf($conTessere), 'nullable', 'string', 'min:15', 'max:1000'],
        ], ['motivo.required' => 'Il pacchetto ha tessere emesse: scrivi il motivo della correzione.', 'motivo.min' => 'La motivazione deve avere almeno 15 caratteri.'])['motivo'] ?? '';

        try {
            $this->tessere->correggiPacchetto($pacchetto, $d, (string) $motivo, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('tesseramento.index')->with('success', "Pacchetto {$pacchetto->fresh()->intervallo()} corretto.");
    }

    public function destroyPacchetto(Request $request, TesseraPacchetto $pacchetto)
    {
        $this->richiediModifica($request);
        try {
            $this->tessere->eliminaPacchetto($pacchetto);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('tesseramento.index')->with('success', 'Pacchetto ' . $pacchetto->intervallo() . ' eliminato'
            . ($pacchetto->prima_nota_id ? ', con la sua uscita in Prima Nota.' : '.'));
    }

    // ----------------------------------------------------------------
    // Tipi di tessera
    // ----------------------------------------------------------------

    public function storeTipo(Request $request)
    {
        $this->richiediModifica($request);
        $d = $this->validaTipo($request, null);
        TesseraTipo::create($d + ['attivo' => true, 'ordine' => (int) TesseraTipo::max('ordine') + 10]);

        return back()->with('success', "Tipo «{$d['nome']}» aggiunto.");
    }

    public function updateTipo(Request $request, TesseraTipo $tipo)
    {
        $this->richiediModifica($request);
        $d = $this->validaTipo($request, $tipo);
        $tipo->update($d + ['attivo' => $request->boolean('attivo')]);

        return back()->with('success', "Tipo «{$tipo->nome}» aggiornato.");
    }

    // ----------------------------------------------------------------
    // Carico delle tessere già emesse (elenco del CSEN)
    // ----------------------------------------------------------------

    public function carica(Request $request)
    {
        $this->richiediModifica($request);
        $carico = $request->session()->get(self::SESSIONE_CARICO);
        $anteprima = null;
        if ($carico && Storage::disk('local')->exists($carico['path'])) {
            $anteprima = app(CaricoTessereCsen::class)->leggi(Storage::disk('local')->path($carico['path'])) + ['nome' => $carico['nome']];
        }

        return view('tessere.carica', ['anteprima' => $anteprima]);
    }

    public function caricaFile(Request $request)
    {
        $this->richiediModifica($request);
        $request->validate([
            'file' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
        ], ['file.required' => 'Scegli il file CSV esportato dal portale CSEN.', 'file.mimes' => 'Serve il file CSV (testo separato da punto e virgola).']);

        $this->scartaCarico($request);
        $path = $request->file('file')->storeAs('tmp/tessere', Str::random(40) . '.csv', 'local');
        $request->session()->put(self::SESSIONE_CARICO, [
            'path' => $path,
            'nome' => mb_substr($request->file('file')->getClientOriginalName(), 0, 100),
        ]);

        return redirect()->route('tesseramento.carica');
    }

    public function caricaConferma(Request $request)
    {
        $this->richiediModifica($request);
        $carico = $request->session()->get(self::SESSIONE_CARICO);
        if (! $carico || ! Storage::disk('local')->exists($carico['path'])) {
            return redirect()->route('tesseramento.carica')->with('error', 'Il file non c\'è più: caricalo di nuovo.');
        }
        $servizio = app(CaricoTessereCsen::class);
        try {
            $letto = $servizio->leggi(Storage::disk('local')->path($carico['path']));
            $esito = $servizio->registra($letto['righe'], $request->user());
        } finally {
            $this->scartaCarico($request);   // il file (dati personali) non resta, anche se il carico fallisce
        }

        $messaggio = "{$esito['caricate']} tessere caricate dall'elenco del CSEN.";
        if ($esito['tipi_nuovi']) {
            $messaggio .= ' Tipi nuovi senza prezzo: ' . implode(', ', $esito['tipi_nuovi']) . ' (prezzo da scrivere prima di comprarne un pacchetto).';
        }

        return redirect()->route('tesseramento.index')->with('success', $messaggio);
    }

    public function caricaAnnulla(Request $request)
    {
        $this->richiediModifica($request);
        $this->scartaCarico($request);

        return redirect()->route('tesseramento.carica');
    }

    // ----------------------------------------------------------------
    // Supporto
    // ----------------------------------------------------------------

    /** Il CSV contiene dati personali: si cancella appena non serve più. */
    private function scartaCarico(Request $request): void
    {
        if ($vecchio = $request->session()->pull(self::SESSIONE_CARICO)) {
            Storage::disk('local')->delete($vecchio['path']);
        }
        foreach (Storage::disk('local')->files('tmp/tessere') as $f) {
            if (Storage::disk('local')->lastModified($f) < now()->subDay()->timestamp) {
                Storage::disk('local')->delete($f);
            }
        }
    }

    private function validaPacchetto(Request $request): array
    {
        $d = $request->validate([
            'tipo_id' => ['required', 'integer', Rule::exists('gl_tessere_tipi', 'id')],
            'acquistato_il' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'prezzo_unitario' => ['required', Importo::regola()],
            'dal' => ['required', 'string', 'max:20'],
            'al' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'acquistato_il.before_or_equal' => 'La data d\'acquisto non può essere nel futuro.',
        ], ['tipo_id' => 'tipo di tessera', 'acquistato_il' => 'data d\'acquisto', 'prezzo_unitario' => 'prezzo per tessera', 'dal' => 'dal numero', 'al' => 'al numero']);

        $dal = Tessere::leggi($d['dal']);
        $al = Tessere::leggi($d['al']);
        $errori = [];
        if (! $dal) {
            $errori['dal'] = 'Il numero deve finire con delle cifre (es. 1201 o 26B2749167).';
        }
        if (! $al) {
            $errori['al'] = 'Il numero deve finire con delle cifre (es. 1230 o 26B2749185).';
        }
        if ($dal && $al) {
            if (strlen($dal['prefisso']) > 10) {
                $errori['dal'] = 'Il prefisso (le lettere e cifre prima del numero) è al massimo di 10 caratteri.';
            } elseif ($al['prefisso'] !== '' && $al['prefisso'] !== $dal['prefisso']) {
                $errori['al'] = "Il prefisso deve essere lo stesso del primo numero ({$dal['prefisso']}), oppure scrivi solo le cifre.";
            } elseif ($al['progressivo'] < $dal['progressivo']) {
                $errori['al'] = 'L\'ultimo numero deve essere maggiore del primo.';
            } elseif ($al['progressivo'] - $dal['progressivo'] + 1 > Tessere::MAX_PACCHETTO) {
                $errori['al'] = 'Al massimo ' . Tessere::MAX_PACCHETTO . ' tessere per pacchetto: controlla i numeri.';
            }
        }
        $prezzo = (float) Importo::leggi($d['prezzo_unitario']);
        if ($prezzo <= 0) {
            $errori['prezzo_unitario'] = 'Il prezzo deve essere maggiore di zero.';
        }
        if ($errori) {
            throw \Illuminate\Validation\ValidationException::withMessages($errori);
        }

        return [
            'tipo_id' => (int) $d['tipo_id'],
            'acquistato_il' => $d['acquistato_il'],
            'prefisso' => $dal['prefisso'],
            'dal' => $dal['progressivo'],
            'al' => $al['progressivo'],
            'cifre' => max($dal['cifre'], $al['cifre']),
            'prezzo_unitario' => $prezzo,
            'note' => $d['note'] ?? null,
        ];
    }

    private function validaTipo(Request $request, ?TesseraTipo $tipo): array
    {
        $bag = 'tipo' . ($tipo?->id ?? 'nuovo');
        $d = $request->validateWithBag($bag, [
            'nome' => ['required', 'string', 'max:50', Rule::unique('gl_tessere_tipi', 'nome')->ignore($tipo?->id)],
            'prezzo' => ['required', Importo::regola()],
        ], ['nome.unique' => 'Questo tipo c\'è già.'], ['prezzo' => 'prezzo per tessera']);
        $prezzo = (float) Importo::leggi($d['prezzo']);
        if ($prezzo <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['prezzo' => 'Il prezzo deve essere maggiore di zero.'])->errorBag($bag);
        }

        return ['nome' => trim($d['nome']), 'prezzo' => $prezzo];
    }

    /** Prezzo proposto per tipo: dall'ultimo pacchetto dello stesso tipo, altrimenti dal listino. */
    private function prezziProposti($tipi): array
    {
        return $tipi->mapWithKeys(function ($t) {
            $ultimo = TesseraPacchetto::where('tipo_id', $t->id)->orderByDesc('acquistato_il')->orderByDesc('id')->value('prezzo_unitario');
            $p = $ultimo ?? $t->prezzo;

            return [$t->id => $p !== null ? Importo::it($p) : null];
        })->all();
    }

    /** Dati proposti nella finestra di emissione, per socio attivo. */
    private function candidati($soci, array $situazioni): array
    {
        $tipoBase = TesseraTipo::ordinati()->where('attivo', true)->whereNotNull('prezzo')->value('id');
        $out = [];
        foreach ($soci as $s) {
            $sit = $situazioni[$s->id];
            $ultima = $sit['tessera'];
            $data = Tessere::dataProposta($ultima);
            $q = Tessere::quota($s, $data);
            $out[$s->id] = [
                'nome' => "{$s->cognome} {$s->nome}",
                'rinnovo' => (bool) $ultima,
                'tipo' => $ultima?->tipo_id ?? $tipoBase,
                'data' => $data->toDateString(),
                'dataAiuto' => $ultima?->scadenza && $ultima->scadenza->gte(today()) ? 'Il giorno dopo la scadenza della precedente.' : 'Oggi.',
                'quota' => ['titolo' => $q['titolo'], 'testo' => $q['testo'], 'variante' => $q['variante'], 'conferma' => $q['conferma']],
            ];
        }

        return $out;
    }

    private function liberi($tipi): array
    {
        return $tipi->mapWithKeys(function ($t) {
            $l = Tessere::primoLibero($t->id);

            return [$t->id => $l ? ['numero' => $l['numero'], 'pacchetto' => $l['pacchetto']->intervallo()] : null];
        })->all();
    }

    /** Situazione della quota dell'anno per l'elenco «Da rinnovare». */
    private function quoteDellAnno($soci, int $anno): array
    {
        $versate = \App\Models\Rinnovo::valido()->where('anno', '>=', $anno)->whereIn('socio_id', $soci->pluck('id'))->pluck('socio_id')->flip();
        $nonVersata = $anno < RinnoviQuota::annoAvvio()
            ? ['Nel vecchio gestionale', 'neutral']   // quote prima del 2027: non nel gestionale
            : RinnoviQuota::SITUAZIONI[RinnoviQuota::situazione(false, $anno)];

        return $soci->mapWithKeys(fn ($s) => [$s->id => isset($versate[$s->id])
            ? RinnoviQuota::SITUAZIONI[RinnoviQuota::IN_REGOLA]
            : $nonVersata])->all();
    }

    private function filtro(Request $request): array
    {
        return [
            'stato' => array_key_exists($s = (string) $request->query('stato', ''), self::FILTRI) ? $s : '',
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /** Tutte le tessere, con filtro per stato e ricerca (cognome, nome, numero). */
    private function elenco(Request $request)
    {
        $f = $this->filtro($request);
        $oggi = today()->toDateString();
        $limite = today()->addDays(Tesseramento::GIORNI_PREAVVISO)->toDateString();

        return Tesseramento::query()
            ->with(['socio:id,cognome,nome,stato', 'tipo:id,nome', 'pacchetto', 'successiva:id,precedente_id'])
            ->when($f['stato'] === 'valida', fn ($q) => $q->where('data_rilascio', '<=', $oggi)->where('scadenza', '>', $limite))
            ->when($f['stato'] === 'in_scadenza', fn ($q) => $q->where('data_rilascio', '<=', $oggi)->whereBetween('scadenza', [$oggi, $limite])->whereDoesntHave('successiva'))
            ->when($f['stato'] === 'scaduta', fn ($q) => $q->where('scadenza', '<', $oggi))
            ->when($f['stato'] === 'portale', fn ($q) => $q->whereNull('portale_csen_il'))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $t = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $f['q']) . '%';
                $q->where(fn ($w) => $w->where('numero_tessera_csen', 'like', $t)
                    ->orWhereHas('socio', fn ($s) => $s->where('cognome', 'like', $t)->orWhere('nome', 'like', $t)));
            })
            ->orderByDesc('data_rilascio')->orderByDesc('id')
            ->paginate(20, ['*'], 'pagina')->withQueryString()->fragment('elenco');
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('tesseramento'), 403, 'Il tuo ruolo può solo consultare le tessere.');
    }
}
