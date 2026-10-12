<?php

namespace App\Http\Controllers;

use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\PrimaNotaDocumento;
use App\Models\Progetto;
use App\Models\Socio;
use App\Models\Soggetto;
use App\Models\Tag;
use App\Services\AnniContabili;
use App\Services\AuditLogService;
use App\Support\Importo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prima Nota: movimenti, giroconti, giustificativi, esportazioni
 * (prima-nota.md, tavole PN-1…PN-3). Rotte protette da 'permesso:prima_nota';
 * le modifiche verificano puoModificare('prima_nota') (Presidente e
 * Tesoriere; il Revisore consulta).
 *
 * Su un anno chiuso ogni modifica o eliminazione chiede una motivazione,
 * registrata nell'Audit Log, e aggiorna la fotografia dei saldi finali
 * (regola 8 di gestionale-acv, prima-nota.md §3).
 */
class PrimaNotaController extends Controller
{
    use Concerns\Giustificativi;

    public const TIPI_FILTRO = [
        'entrata' => 'Entrate',
        'uscita' => 'Uscite',
        'giroconto' => 'Giroconti',
        'da_classificare' => 'Da classificare',
        'anticipi' => 'Anticipi da rimborsare',
    ];

    public function __construct(private AnniContabili $anni)
    {
    }

    // ----------------------------------------------------------------
    // Elenco
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $filtri = $this->filtri($request);
        $anno = $filtri['anno'];

        $movimenti = $this->query($filtri)
            ->with(['categoria:id,nome,codice,tipo', 'tag:id,nome', 'socio:id,nome,cognome', 'giroconto:id,conto'])
            ->withCount('documenti')
            ->orderByDesc('data')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $finali = $this->anni->finali($anno);
        $apertura = $this->anni->apertura($anno);
        $base = PrimaNota::query()->anno($anno)->effettivi()->whereNull('giroconto_id');

        $kpi = [
            'entrate' => (float) (clone $base)->where('tipo', 'entrata')->sum('importo'),
            'entrate_n' => (clone $base)->where('tipo', 'entrata')->count(),
            'uscite' => (float) (clone $base)->where('tipo', 'uscita')->sum('importo'),
            'uscite_n' => (clone $base)->where('tipo', 'uscita')->count(),
            'anticipi' => (float) PrimaNota::query()->anno($anno)->where('sospeso', true)->whereNull('rimborsato_il')->sum('importo'),
            'anticipi_n' => PrimaNota::query()->anno($anno)->where('sospeso', true)->whereNull('rimborsato_il')->count(),
            'da_classificare' => PrimaNota::query()->anno($anno)
                ->whereHas('categoria', fn ($q) => $q->where('codice', PrimaNotaCategoria::DA_CLASSIFICARE))->count(),
        ];

        // Totali del filtro: senza giroconti né anticipi aperti
        $filtrati = $this->query($filtri)->effettivi()->whereNull('giroconto_id');
        $totaliFiltro = [
            'entrate' => (float) (clone $filtrati)->where('tipo', 'entrata')->sum('importo'),
            'uscite' => (float) (clone $filtrati)->where('tipo', 'uscita')->sum('importo'),
        ];

        return view('prima-nota.index', [
            'movimenti' => $movimenti,
            'filtri' => $filtri,
            'anno' => $anno,
            'riga' => $this->anni->trova($anno),
            'anniDisponibili' => $this->anniDisponibili(),
            'finali' => $finali,
            'apertura' => $apertura,
            'kpi' => $kpi,
            'totaliFiltro' => $totaliFiltro,
            'filtroAttivo' => $filtri['conto'] !== '' || $filtri['tipo'] !== '' || $filtri['categoria'] || $filtri['tag'] || $filtri['q'] !== '',
            'categorie' => PrimaNotaCategoria::orderBy('tipo')->orderBy('ordine')->get(['id', 'nome', 'tipo']),
            'tags' => Tag::orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $filtri = $this->filtri($request);
        $movimenti = $this->query($filtri)->with(['categoria', 'tag', 'giroconto:id,conto'])
            ->orderBy('data')->orderBy('id')->get();
        $this->registraEsportazione('CSV', $movimenti->count(), $filtri);

        return response()->streamDownload(function () use ($movimenti) {
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['N.', 'Data', 'Tipo', 'Conto', 'Categoria', 'Descrizione', 'Fornitore o soggetto', 'Tag',
                'Documento', 'Entrata', 'Uscita', 'Note'], ';');
            // Un testo che comincia con = + - @ Excel lo eseguirebbe come formula.
            $t = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
            foreach ($movimenti as $m) {
                fputcsv($fh, array_map($t, [
                    $m->id,
                    $m->data->format('d/m/Y'),
                    $m->isGiroconto() ? 'Giroconto' : ($m->isEntrata() ? 'Entrata' : 'Uscita'),
                    $m->contoLabel(),
                    $m->categoria?->nome,
                    $m->descrizione,
                    $m->soggetto,
                    $m->tag?->nome,
                    trim(implode(' ', array_filter([PrimaNota::TIPI_DOCUMENTO[$m->tipo_documento] ?? null, $m->numero_documento,
                        $m->data_documento ? 'del ' . $m->data_documento->format('d/m/Y') : null]))),
                    $m->isEntrata() ? Importo::it($m->importo) : '',
                    $m->isEntrata() ? '' : Importo::it($m->importo),
                    $m->note,
                ]), ';');
            }
            fclose($fh);
        }, 'prima-nota-' . $filtri['anno'] . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Registro dei movimenti in PDF (A4 orizzontale), con gli stessi filtri dell'elenco. */
    public function exportPdf(Request $request)
    {
        $filtri = $this->filtri($request);
        $anno = $filtri['anno'];
        $movimenti = $this->query($filtri)->with(['categoria', 'tag', 'giroconto:id,conto'])
            ->orderBy('data')->orderBy('id')->get();

        $pdf = Pdf::loadView('pdf.prima-nota', [
            'movimenti' => $movimenti,
            'anno' => $anno,
            'apertura' => $this->anni->apertura($anno),
            'finali' => $this->anni->finali($anno),
            'riga' => $this->anni->trova($anno),
            'filtrato' => $filtri['conto'] !== '' || $filtri['tipo'] !== '' || $filtri['categoria'] || $filtri['tag'] || $filtri['q'] !== '',
            'generatoIl' => now(),
        ])->setPaper('a4', 'landscape');

        $contenuto = $pdf->output();
        $this->registraEsportazione('PDF', $movimenti->count(), $filtri);

        return response($contenuto, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="prima-nota-' . $anno . '.pdf"',
        ]);
    }

    // ----------------------------------------------------------------
    // Scheda
    // ----------------------------------------------------------------

    public function show(PrimaNota $movimento)
    {
        // Di un giroconto si mostra sempre la riga di uscita, con l'altra.
        if ($movimento->isGiroconto() && $movimento->isEntrata()) {
            return redirect()->route('prima-nota.show', $movimento->giroconto_id);
        }

        $movimento->load(['categoria', 'tag', 'socio', 'progetto', 'rinnovo', 'registratoDa', 'giroconto', 'scontrino.file', 'scontrino.movimenti:id,scontrino_id',
            'documenti' => fn ($q) => $q->orderBy('created_at')]);

        return view('prima-nota.show', [
            'm' => $movimento,
            'chiuso' => $this->anni->chiuso($movimento->annoMovimento()),
        ]);
    }

    // ----------------------------------------------------------------
    // Nuovo movimento e modifica
    // ----------------------------------------------------------------

    public function create(Request $request)
    {
        $this->richiediModifica($request);

        $m = new PrimaNota([
            'tipo' => $request->query('tipo') === 'entrata' ? 'entrata' : 'uscita',
            'conto' => 'banca',
            'data' => today(),
        ]);

        return view('prima-nota.create', $this->datiForm($m) + ['m' => $m]);
    }

    public function store(Request $request)
    {
        $this->richiediModifica($request);
        $erroriFile = $this->trattieniFile($request);
        $dati = $this->valida($request, null, false, $erroriFile);

        $anno = (int) substr($dati['data'], 0, 4);
        if ($this->anni->chiuso($anno)) {
            return back()->withInput()->with('error', "L'anno {$anno} è chiuso: non si registrano nuovi movimenti.");
        }

        $movimento = $this->archiviaInSicurezza(function () use ($dati, $request, $anno) {
            $this->anni->assicura($anno);
            $m = new PrimaNota();
            $m->fill($this->campi($dati))->forceFill(['registrato_da' => $request->user()->id])->save();
            $this->salvaFile($m, $dati['file_temp'] ?? [], $request);

            return $m;
        });

        $this->liberaFile($request, $dati['file_temp'] ?? []);

        if ($request->boolean('salva_altro')) {
            return redirect()->route('prima-nota.create', ['tipo' => $movimento->tipo])
                ->with('success', "Movimento n. {$movimento->id} registrato. Puoi inserire il prossimo.");
        }

        return redirect()->route('prima-nota.show', $movimento)->with('success', "Movimento n. {$movimento->id} registrato.");
    }

    public function edit(Request $request, PrimaNota $movimento)
    {
        $this->richiediModifica($request);
        if ($risposta = $this->anticipoAperto($movimento)) {
            return $risposta;
        }

        if ($movimento->isGiroconto()) {
            $uscita = $movimento->isEntrata() ? $movimento->giroconto : $movimento;

            return view('prima-nota.giroconto', [
                'g' => $uscita, 'chiuso' => $this->anni->chiuso($uscita->annoMovimento()),
            ]);
        }

        return view('prima-nota.edit', $this->datiForm($movimento) + [
            'm' => $movimento,
            'chiuso' => $this->anni->chiuso($movimento->annoMovimento()),
        ]);
    }

    public function update(Request $request, PrimaNota $movimento)
    {
        $this->richiediModifica($request);
        if ($risposta = $this->anticipoAperto($movimento)) {
            return $risposta;
        }

        if ($movimento->isGiroconto()) {
            return $this->aggiornaGiroconto($request, $movimento->isEntrata() ? $movimento->giroconto : $movimento);
        }

        // Movimento di uno scontrino: importo, data e conto si cambiano solo
        // dalla scheda dello scontrino (scontrini.md §4); qui categoria,
        // descrizione, tag e il resto.
        if ($movimento->scontrino_id) {
            $request->merge([
                'tipo' => 'uscita',
                'conto' => $movimento->conto,
                'data' => $movimento->data->toDateString(),
                'importo' => Importo::it($movimento->importo),
            ]);
        }

        $annoPrima = $movimento->annoMovimento();
        $chiusoPrima = $this->anni->chiuso($annoPrima);
        $erroriFile = $this->trattieniFile($request);
        $dati = $this->valida($request, $movimento, $chiusoPrima, $erroriFile);
        $annoDopo = (int) substr($dati['data'], 0, 4);

        if ($risposta = $this->controllaAnni($annoPrima, $annoDopo, $chiusoPrima)) {
            return $risposta;
        }

        $this->archiviaInSicurezza(function () use ($movimento, $dati, $request, $annoDopo, $chiusoPrima, $annoPrima) {
            $this->anni->assicura($annoDopo);
            $movimento->fill($this->campi($dati))->save();
            $this->salvaFile($movimento, $dati['file_temp'] ?? [], $request);
            if ($movimento->scontrino_id) {
                // Le righe dello scontrino seguono la categoria del loro movimento
                \App\Models\PrimaNotaScontrinoRiga::where('prima_nota_id', $movimento->id)->update(['categoria_id' => $movimento->categoria_id]);
                $movimento->scontrino?->ricalcolaStato();
            }
            if ($chiusoPrima) {
                $this->registraCorrezione($movimento, 'modifica', $dati['motivazione']);
                $this->anni->aggiornaChiusura($annoPrima);
            }
        });
        $this->liberaFile($request, $dati['file_temp'] ?? []);

        return redirect()->route('prima-nota.show', $movimento)->with('success', 'Movimento aggiornato.');
    }

    public function destroy(Request $request, PrimaNota $movimento)
    {
        $this->richiediModifica($request);

        if ($movimento->rinnovo_id) {
            return back()->with('error', 'Il movimento è la quota di un socio: si annulla dalla quota, non da qui.');
        }
        if ($movimento->scontrino_id) {
            return back()->with('error', 'Il movimento fa parte di uno scontrino: si toglie dalla scheda dello scontrino.');
        }

        $anno = $movimento->annoMovimento();
        $chiuso = $this->anni->chiuso($anno);
        $request->validate([
            'conferma' => ['required', 'in:si'],
            'motivazione' => [Rule::requiredIf($chiuso), 'nullable', 'string', 'min:15', 'max:1000'],
        ], [
            'conferma.*' => "Conferma l'eliminazione.",
            'motivazione.required' => "L'anno è chiuso: scrivi perché elimini il movimento.",
            'motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.',
        ]);

        // Di un giroconto si elimina con gli eventi solo la riga di uscita
        // (con il verso nei valori dell'audit, PrimaNota::auditContesto);
        // l'altra riga e i giustificativi si tolgono senza eventi: una
        // operazione, una riga nell'Audit Log (collaudo J9).
        if ($movimento->isGiroconto() && $movimento->isEntrata()) {
            $movimento = $movimento->giroconto;
        }
        $movimento->load('documenti');
        $compagna = $movimento->isGiroconto() ? $movimento->giroconto : null;
        $righe = collect([$movimento, $compagna])->filter();
        $numero = $movimento->id;

        DB::transaction(function () use ($movimento, $compagna, $chiuso, $request, $anno) {
            if ($chiuso) {
                $this->registraCorrezione($movimento, 'eliminazione', $request->input('motivazione'));
            }
            PrimaNota::withoutEvents(function () use ($movimento, $compagna) {
                PrimaNotaDocumento::whereIn('prima_nota_id', array_filter([$movimento->id, $compagna?->id]))->delete();
                $compagna?->delete();
            });
            $movimento->delete();
            if ($chiuso) {
                $this->anni->aggiornaChiusura($anno);
            }
        });
        // File cancellati solo dopo la transazione: se fallisce, restano.
        foreach ($righe as $r) {
            foreach ($r->documenti ?? [] as $d) {
                Storage::disk('local')->delete($d->path);
            }
        }

        return redirect()->route('prima-nota.index', ['anno' => $anno])->with('success', "Movimento n. {$numero} eliminato.");
    }

    // ----------------------------------------------------------------
    // Giroconto
    // ----------------------------------------------------------------

    public function createGiroconto(Request $request)
    {
        $this->richiediModifica($request);

        return view('prima-nota.giroconto', ['g' => null, 'chiuso' => false]);
    }

    public function storeGiroconto(Request $request)
    {
        $this->richiediModifica($request);
        $dati = $this->validaGiroconto($request);
        $anno = (int) substr($dati['data'], 0, 4);
        if ($this->anni->chiuso($anno)) {
            return back()->withInput()->with('error', "L'anno {$anno} è chiuso: non si registrano nuovi movimenti.");
        }

        $uscita = DB::transaction(function () use ($dati, $request, $anno) {
            $this->anni->assicura($anno);
            $comune = ['data' => $dati['data'], 'importo' => $dati['importo'], 'descrizione' => $dati['descrizione'], 'note' => $dati['note']];
            // Nell'Audit Log una riga sola, quella dell'uscita (con il verso):
            // la riga di entrata si scrive senza eventi.
            $entrata = new PrimaNota();
            PrimaNota::withoutEvents(fn () => $entrata->fill($comune + ['tipo' => 'entrata', 'conto' => $dati['conto_a']])
                ->forceFill(['registrato_da' => $request->user()->id])->save());
            $uscita = new PrimaNota();
            $uscita->fill($comune + ['tipo' => 'uscita', 'conto' => $dati['conto_da'], 'giroconto_id' => $entrata->id])
                ->forceFill(['registrato_da' => $request->user()->id])->save();
            PrimaNota::withoutEvents(fn () => $entrata->update(['giroconto_id' => $uscita->id]));

            return $uscita;
        });

        return redirect()->route('prima-nota.show', $uscita)->with('success', 'Giroconto registrato.');
    }

    private function aggiornaGiroconto(Request $request, PrimaNota $uscita)
    {
        $annoPrima = $uscita->annoMovimento();
        $chiusoPrima = $this->anni->chiuso($annoPrima);
        $dati = $this->validaGiroconto($request, $chiusoPrima);
        $annoDopo = (int) substr($dati['data'], 0, 4);
        if ($risposta = $this->controllaAnni($annoPrima, $annoDopo, $chiusoPrima)) {
            return $risposta;
        }

        DB::transaction(function () use ($uscita, $dati, $annoDopo, $chiusoPrima, $annoPrima) {
            $this->anni->assicura($annoDopo);
            $comune = ['data' => $dati['data'], 'importo' => $dati['importo'], 'descrizione' => $dati['descrizione'], 'note' => $dati['note']];
            $uscita->update($comune + ['conto' => $dati['conto_da']]);
            PrimaNota::withoutEvents(fn () => $uscita->giroconto->update($comune + ['conto' => $dati['conto_a']]));
            if ($chiusoPrima) {
                $this->registraCorrezione($uscita, 'modifica', $dati['motivazione']);
                $this->anni->aggiornaChiusura($annoPrima);
            }
        });

        return redirect()->route('prima-nota.show', $uscita)->with('success', 'Giroconto aggiornato.');
    }

    // ----------------------------------------------------------------
    // Giustificativi
    // ----------------------------------------------------------------

    public function aggiungiDocumenti(Request $request, PrimaNota $movimento)
    {
        $this->richiediModifica($request);
        $chiuso = $this->anni->chiuso($movimento->annoMovimento());
        $erroriFile = $this->trattieniFile($request);
        $dati = Validator::make($request->all(), [
            'file_temp' => ['required', 'array', 'max:' . self::MAX_FILE],
            'file_temp.*' => ['string'],
            'motivazione' => [Rule::requiredIf($chiuso), 'nullable', 'string', 'min:15', 'max:1000'],
        ], [
            'file_temp.required' => 'Scegli almeno un file.',
            'file_temp.max' => 'Al massimo ' . self::MAX_FILE . ' giustificativi per volta.',
            'motivazione.required' => "L'anno è chiuso: scrivi perché aggiungi il giustificativo.",
            'motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.',
        ])->after(fn ($v) => array_map(fn ($e) => $v->errors()->add('documenti', $e), $erroriFile))
            ->validateWithBag('giustificativi');

        $this->archiviaInSicurezza(function () use ($movimento, $request, $chiuso, $dati) {
            $this->salvaFile($movimento, $dati['file_temp'], $request);
            if ($chiuso) {
                $this->registraCorrezione($movimento, 'giustificativo aggiunto', $request->input('motivazione'));
            }
        });
        $this->liberaFile($request, $dati['file_temp']);

        return redirect()->route('prima-nota.show', $movimento)->with('success', 'Giustificativi aggiunti.');
    }

    public function documento(Request $request, PrimaNota $movimento, PrimaNotaDocumento $documento)
    {
        abort_unless((int) $documento->prima_nota_id === (int) $movimento->id, 404);
        abort_unless(Storage::disk('local')->exists($documento->path), 404, 'Il file non si trova più.');

        // Nome con accenti: forma estesa (RFC 6266) più un ripiego ASCII.
        $nome = str_replace(['/', '\\', '"', "\r", "\n"], '', $documento->nome) ?: 'giustificativo';
        $disposizione = \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition(
            $request->boolean('scarica') ? 'attachment' : 'inline', $nome, \Illuminate\Support\Str::ascii($nome) ?: 'giustificativo');

        return response()->file(Storage::disk('local')->path($documento->path), [
            'Content-Type' => Storage::disk('local')->mimeType($documento->path),
            'Content-Disposition' => $disposizione,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function eliminaDocumento(Request $request, PrimaNota $movimento, PrimaNotaDocumento $documento)
    {
        $this->richiediModifica($request);
        abort_unless((int) $documento->prima_nota_id === (int) $movimento->id, 404);

        $chiuso = $this->anni->chiuso($movimento->annoMovimento());
        $request->validate(['motivazione' => [Rule::requiredIf($chiuso), 'nullable', 'string', 'min:15', 'max:1000']],
            ['motivazione.required' => "L'anno è chiuso: scrivi perché togli il giustificativo.", 'motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.']);

        $path = $documento->path;
        DB::transaction(function () use ($documento, $chiuso, $movimento, $request) {
            $documento->delete();
            if ($chiuso) {
                $this->registraCorrezione($movimento, 'giustificativo tolto', $request->input('motivazione'));
            }
        });
        Storage::disk('local')->delete($path);

        return redirect()->route('prima-nota.show', $movimento)->with('success', 'Giustificativo tolto.');
    }

    // ----------------------------------------------------------------
    // Supporto
    // ----------------------------------------------------------------

    /** @return array{anno: int, conto: string, tipo: string, categoria: int, tag: int, q: string} */
    private function filtri(Request $request): array
    {
        $anno = (int) $request->query('anno', now()->year);

        return [
            'anno' => $anno >= 2000 && $anno <= 2100 ? $anno : now()->year,
            'conto' => array_key_exists($c = (string) $request->query('conto', ''), PrimaNota::CONTI) ? $c : '',
            'tipo' => array_key_exists($t = (string) $request->query('tipo', ''), self::TIPI_FILTRO) ? $t : '',
            'categoria' => (int) $request->query('categoria', 0),
            'tag' => (int) $request->query('tag', 0),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    private function query(array $f): Builder
    {
        return PrimaNota::query()->anno($f['anno'])
            ->when($f['conto'] !== '', fn ($q) => $q->where('conto', $f['conto']))
            // Senza filtro di conto un giroconto compare una volta sola (la riga di uscita)
            ->when($f['conto'] === '', fn ($q) => $q->where(fn ($w) => $w->whereNull('giroconto_id')->orWhere('tipo', 'uscita')))
            ->when(in_array($f['tipo'], ['entrata', 'uscita'], true), fn ($q) => $q->where('tipo', $f['tipo'])->whereNull('giroconto_id'))
            ->when($f['tipo'] === 'giroconto', fn ($q) => $q->whereNotNull('giroconto_id'))
            ->when($f['tipo'] === 'da_classificare', fn ($q) => $q->whereHas('categoria', fn ($c) => $c->where('codice', PrimaNotaCategoria::DA_CLASSIFICARE)))
            ->when($f['tipo'] === 'anticipi', fn ($q) => $q->where('sospeso', true)->whereNull('rimborsato_il'))
            ->when($f['categoria'], fn ($q) => $q->where('categoria_id', $f['categoria']))
            ->when($f['tag'], fn ($q) => $q->where('tag_id', $f['tag']))
            ->when($f['q'] !== '', function ($q) use ($f) {
                $testo = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $f['q']) . '%';
                $q->where(fn ($w) => $w->where('descrizione', 'like', $testo)
                    ->orWhere('soggetto', 'like', $testo)
                    ->orWhere('numero_documento', 'like', $testo)
                    ->orWhere('note', 'like', $testo));
            });
    }

    /** Anni con una riga o con movimenti, più l'anno in corso, dal più recente. */
    private function anniDisponibili(): array
    {
        return \App\Models\AnnoContabile::pluck('anno')
            ->merge(PrimaNota::selectRaw('DISTINCT YEAR(data) AS a')->pluck('a'))
            ->push(now()->year)
            ->map(fn ($a) => (int) $a)->unique()->sortDesc()->values()->all();
    }

    private function datiForm(PrimaNota $m): array
    {
        return [
            'categorieEntrata' => PrimaNotaCategoria::perMovimenti('entrata', $m->tipo === 'entrata' ? $m->categoria_id : null),
            'categorieUscita' => PrimaNotaCategoria::perMovimenti('uscita', $m->tipo === 'uscita' ? $m->categoria_id : null),
            'soci' => Socio::where(fn ($q) => $q->where('stato', 'attivo')->when($m->socio_id, fn ($w) => $w->orWhere('id', $m->socio_id)))
                ->orderBy('cognome')->orderBy('nome')->get(['id', 'nome', 'cognome']),
            // Fornitori in elenco (quelli tolti dalla lista no, v0.39.0), più quello del movimento
            'soggetti' => Soggetto::where(fn ($q) => $q->where('attivo', true)->when($m->soggetto, fn ($w) => $w->orWhere('nome', $m->soggetto)))->orderBy('nome')->pluck('nome'),
            'tags' => Tag::orderBy('nome')->pluck('nome'),
            'progetti' => Progetto::orderByDesc('id')->get(),
        ];
    }

    /** Dati validati del movimento (non giroconto). */
    /** @param string[] $erroriFile giustificativi rifiutati da trattieniFile() */
    private function valida(Request $request, ?PrimaNota $attuale = null, bool $chiuso = false, array $erroriFile = []): array
    {
        $tipo = $request->input('tipo') === 'entrata' ? 'entrata' : 'uscita';
        $categoriaAttuale = $attuale && $attuale->tipo === $tipo ? $attuale->categoria_id : null;

        $dati = Validator::make($request->all(), [
            'tipo' => ['required', 'in:entrata,uscita'],
            'conto' => ['required', Rule::in(array_keys(PrimaNota::CONTI))],
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:' . today()->addYear()->toDateString()],
            'importo' => ['required', Importo::regola()],
            'categoria_id' => ['required', 'integer', function ($attr, $valore, $fail) use ($tipo, $categoriaAttuale) {
                $c = PrimaNotaCategoria::find($valore);
                $ok = $c && $c->tipo === $tipo
                    && ((int) $valore === (int) $categoriaAttuale || ($c->attiva && $c->uso === 'movimenti'));
                if (! $ok) {
                    $fail('Scegli una categoria ' . ($tipo === 'entrata' ? 'di entrata' : 'di uscita') . ' tra quelle in elenco.');
                }
            }],
            'descrizione' => ['required', 'string', 'max:255'],
            'soggetto' => ['nullable', 'string', 'max:200'],
            'tag' => ['nullable', 'string', 'max:100'],
            'progetto_id' => ['nullable', 'integer', 'exists:gl_progetti,id'],
            'socio_id' => ['nullable', 'integer', 'exists:gl_soci,id'],
            'tipo_documento' => ['nullable', Rule::in(array_keys(PrimaNota::TIPI_DOCUMENTO))],
            'numero_documento' => ['nullable', 'string', 'max:100'],
            'data_documento' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:2000'],
            // I file arrivano già messi da parte (trattieniFile): qui i loro gettoni.
            'file_temp' => ['nullable', 'array', 'max:' . self::MAX_FILE],
            'file_temp.*' => ['string'],
            'motivazione' => [Rule::requiredIf($chiuso), 'nullable', 'string', 'min:15', 'max:1000'],
        ], [
            'data.before_or_equal' => 'La data è troppo avanti nel tempo.',
            'motivazione.required' => "L'anno è chiuso: scrivi perché correggi il movimento.",
            'motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.',
            'file_temp.max' => 'Al massimo ' . self::MAX_FILE . ' giustificativi per volta.',
        ], [
            'categoria_id' => 'categoria', 'progetto_id' => 'progetto', 'socio_id' => 'socio',
            'tipo_documento' => 'tipo di documento', 'numero_documento' => 'numero', 'data_documento' => 'data del documento',
        ])->after(fn ($v) => array_map(fn ($e) => $v->errors()->add('documenti', $e), $erroriFile))->validate();

        $dati['importo'] = Importo::leggi($dati['importo']);
        if ($dati['tipo'] === 'uscita') {
            $dati['socio_id'] = null;
        }

        return $dati;
    }

    private function validaGiroconto(Request $request, bool $chiuso = false): array
    {
        $dati = $request->validate([
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:' . today()->addYear()->toDateString()],
            'conto_da' => ['required', Rule::in(array_keys(PrimaNota::CONTI))],
            'conto_a' => ['required', Rule::in(array_keys(PrimaNota::CONTI)), 'different:conto_da'],
            'importo' => ['required', Importo::regola()],
            'descrizione' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'motivazione' => [Rule::requiredIf($chiuso), 'nullable', 'string', 'min:15', 'max:1000'],
        ], [
            'conto_a.different' => 'Il conto di arrivo deve essere diverso da quello di partenza.',
            'motivazione.required' => "L'anno è chiuso: scrivi perché correggi il giroconto.",
            'motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.',
        ], ['conto_da' => 'da', 'conto_a' => 'a']);

        $dati += ['descrizione' => null, 'note' => null, 'motivazione' => null];
        $dati['importo'] = Importo::leggi($dati['importo']);
        $dati['descrizione'] = $dati['descrizione'] ?: ($dati['conto_da'] === 'cassa' ? 'Versamento contanti in banca' : 'Prelievo dalla banca per la cassa');

        return $dati;
    }

    /** Campi del movimento dai dati validati; fornitore e tag trova-o-crea (dentro la transazione). */
    private function campi(array $dati): array
    {
        $soggetto = trim(preg_replace('/\s+/u', ' ', (string) ($dati['soggetto'] ?? '')));
        if ($soggetto !== '') {
            $esistente = Soggetto::whereRaw('LOWER(nome) = ?', [mb_strtolower($soggetto)])->first();
            $soggetto = $esistente ? $esistente->nome : Soggetto::create(['nome' => mb_substr($soggetto, 0, 200)])->nome;
        }

        return [
            'tipo' => $dati['tipo'],
            'conto' => $dati['conto'],
            'data' => $dati['data'],
            'importo' => $dati['importo'],
            'categoria_id' => (int) $dati['categoria_id'],
            'descrizione' => trim($dati['descrizione']),
            'soggetto' => $soggetto !== '' ? $soggetto : null,
            'tag_id' => Tag::trovaOCrea($dati['tag'] ?? null)?->id,
            'progetto_id' => $dati['progetto_id'] ?? null,
            'socio_id' => $dati['socio_id'] ?? null,
            'tipo_documento' => $dati['tipo_documento'] ?? null,
            'numero_documento' => $dati['numero_documento'] ?? null,
            'data_documento' => $dati['data_documento'] ?? null,
            'note' => $dati['note'] ?? null,
        ];
    }

    /** Spesa anticipata non ancora rimborsata: si gestisce dal rimborso (v0.37.0), non dal modulo. */
    private function anticipoAperto(PrimaNota $m)
    {
        return $m->isAnticipoAperto()
            ? redirect()->route('prima-nota.show', $m)->with('error', 'Spesa anticipata ancora da rimborsare: si gestisce dalla nota di rimborso.')
            : null;
    }

    /** Un movimento di un anno chiuso resta nel suo anno; nessuno entra in un anno chiuso. */
    private function controllaAnni(int $annoPrima, int $annoDopo, bool $chiusoPrima)
    {
        if ($chiusoPrima && $annoDopo !== $annoPrima) {
            return back()->withInput()->with('error', "Il movimento è dell'anno chiuso {$annoPrima}: la data deve restare in quell'anno.");
        }
        if (! $chiusoPrima && $this->anni->chiuso($annoDopo)) {
            return back()->withInput()->with('error', "L'anno {$annoDopo} è chiuso: il movimento non si può spostare lì.");
        }

        return null;
    }

    private function registraCorrezione(PrimaNota $m, string $azione, string $motivazione): void
    {
        AuditLogService::scrivi([
            'evento' => 'PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO',
            'entita_tipo' => 'PrimaNota',
            'entita_id' => $m->id,
            'anno' => $m->annoMovimento(),
            'livello_rischio' => 'HIGH',
            'dettaglio' => "Movimento n. {$m->id} dell'anno chiuso {$m->annoMovimento()}: {$azione}. Motivazione: {$motivazione}",
        ]);
    }

    private function registraEsportazione(string $formato, int $quanti, array $filtri): void
    {
        AuditLogService::scrivi([
            'evento' => 'PRIMA_NOTA_ESPORTATA',
            'entita_tipo' => 'PrimaNota',
            'anno' => $filtri['anno'],
            'dettaglio' => "Esportazione {$formato} della Prima Nota {$filtri['anno']}: {$quanti} movimenti. Filtri: "
                . (collect($filtri)->except('anno')->filter()->map(fn ($v, $k) => $k === 'q' ? 'ricerca testuale' : "{$k}={$v}")->implode(', ') ?: 'nessuno') . '.',
        ]);
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('prima_nota'), 403, 'Il tuo ruolo può solo consultare la Prima Nota.');
    }
}
