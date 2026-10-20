<?php

namespace App\Http\Controllers;

use App\Models\Impostazione;
use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\PrimaNotaScontrino;
use App\Models\PrimaNotaScontrinoFile;
use App\Models\Soggetto;
use App\Models\User;
use App\Scontrini\Foto;
use App\Scontrini\Fornitori;
use App\Scontrini\LettoreScontrino;
use App\Scontrini\ServizioOcr;
use App\Services\AnniContabili;
use App\Services\Scontrini;
use App\Support\Importo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Scontrini dal telefono con OCR (specifiche/scontrini.md, tavole SN-1…SN-6).
 * Rotte sotto 'permesso:scontrini'.
 *
 * - Caricare (passi 1 e 2): chi ha la sezione 'scontrini' piena (tutto il
 *   CD). Il passo 2 è l'unico momento in cui chi carica corregge.
 * - Verificare, dividere, correggere, eliminare: chi modifica la Prima Nota
 *   (Presidente e Tesoriere).
 * - Vedere: chi ha la sezione (anche in sola lettura, il Revisore).
 *
 * Tra il passo 1 e il passo 2 nel database non c'è nulla: foto e dati letti
 * restano messi da parte in sessione (una «bozza»), per un giorno.
 */
class ScontriniController extends Controller
{
    private const SESSIONE = 'scontrini_bozze';

    private const MAX_KB = 8192;

    public function __construct(private AnniContabili $anni, private Scontrini $scontrini)
    {
    }

    // ----------------------------------------------------------------
    // Elenco (SN-5) e «I miei scontrini» (SN-4)
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $anni = PrimaNotaScontrino::query()->distinct()->orderByDesc('anno')->pluck('anno')->push(now()->year)->unique()->sortDesc()->values();
        $anno = (int) $request->query('anno', now()->year);
        $stato = array_key_exists((string) $request->query('stato'), PrimaNotaScontrino::STATI) ? (string) $request->query('stato') : '';
        $chi = (int) $request->query('chi');
        $q = trim((string) $request->query('q'));

        $base = PrimaNotaScontrino::where('anno', $anno);
        $scontrini = (clone $base)
            ->with(['soggetto:id,nome', 'registratoDa:id,name'])
            ->when($stato !== '', fn ($w) => $w->where('stato', $stato))
            ->when($chi, fn ($w) => $w->where('registrato_da', $chi))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nota', 'like', "%{$q}%")
                ->orWhere('ragione_sociale_raw', 'like', "%{$q}%")
                ->orWhereHas('soggetto', fn ($s) => $s->where('nome', 'like', "%{$q}%"))))
            ->orderByDesc('data_scontrino')->orderByDesc('numero')
            ->paginate(25)->withQueryString();

        $conteggi = (clone $base)->selectRaw('stato, COUNT(*) n, SUM(importo_totale) t')->groupBy('stato')->get()->keyBy('stato');

        return view('scontrini.index', [
            'scontrini' => $scontrini,
            'anno' => $anno,
            'anni' => $anni,
            'filtri' => ['stato' => $stato, 'chi' => $chi, 'q' => $q],
            'conteggi' => $conteggi,
            'totale' => (float) $conteggi->sum('t'),
            'numero' => (int) $conteggi->sum('n'),
            'caricatori' => User::whereIn('id', (clone $base)->select('registrato_da'))->orderBy('name')->get(['id', 'name']),
            'puoVerificare' => $this->puoVerificare($request->user()),
            'puoCaricare' => $request->user()->puoModificare('scontrini'),
            'chiuso' => $this->anni->chiuso($anno),
            // Stato della lettura automatica (analisi §3.9): chiave presente e ultimo esito
            'ocrConfigurato' => (string) config('services.ocr_space.key') !== '',
            'ultimaLettura' => PrimaNotaScontrino::whereNotNull('ocr_esito')->latest('id')->first(['id', 'ocr_esito', 'created_at']),
        ]);
    }

    public function miei(Request $request)
    {
        return view('scontrini.miei', [
            'scontrini' => PrimaNotaScontrino::with('soggetto:id,nome')
                ->where('registrato_da', $request->user()->id)
                ->orderByDesc('created_at')->limit(30)->get(),
            'puoCaricare' => $request->user()->puoModificare('scontrini'),
        ]);
    }

    // ----------------------------------------------------------------
    // Passo 1: foto e nota (SN-1)
    // ----------------------------------------------------------------

    public function create(Request $request)
    {
        $this->richiediCarica($request);
        $anno = now()->year;

        return view('scontrini.create', [
            'anno' => $anno,
            'chiuso' => $this->anni->chiuso($anno),
            // Prima dell'avvio della contabilità (2027) gli scontrini vanno in gestionale-acv
            'avvio' => \App\Services\RinnoviQuota::annoAvvio(),
        ]);
    }

    /** Riceve foto e nota, mette da parte la foto, la fa leggere, va al passo 2. */
    public function leggi(Request $request, ServizioOcr $ocr, LettoreScontrino $lettore)
    {
        $this->richiediCarica($request);
        $request->validate([
            'foto' => ['required', 'file', 'max:' . self::MAX_KB],
            'nota' => ['required', 'string', 'max:500'],
        ], [
            'foto.required' => 'Fotografa lo scontrino o scegli un file.',
            'foto.max' => 'Il file pesa più di 8 MB.',
            'foto.uploaded' => 'La foto non è arrivata per intero (forse pesa più di 8 MB): riprova.',
            'nota.required' => 'Scrivi che cosa hai comprato.',
        ]);

        $file = $request->file('foto');
        if (Foto::heic($file->getRealPath())) {
            return back()->withInput()->withErrors(['foto' => "La foto è in formato HEIC e non si può leggere. Sull'iPhone: Impostazioni › Fotocamera › Formati › «Più compatibile», poi rifai la foto."]);
        }
        $ext = Scontrini::estensione($file);
        if (! in_array($ext, ['jpg', 'png', 'pdf'], true)) {
            return back()->withInput()->withErrors(['foto' => 'Lo scontrino deve essere una foto (JPG o PNG) o un PDF.']);
        }

        $sessione = $this->pulisciBozze($request);
        $token = Str::random(32);
        $cartella = 'tmp/scontrini/' . $request->user()->id;
        $path = $file->storeAs($cartella, "{$token}.{$ext}", 'local');

        // Copia ridotta per la lettura e per l'anteprima (le foto: sempre;
        // i PDF vanno com'erano, se stanno sotto 1 MB)
        $ridotta = null;
        $contenuto = null;
        $mime = 'application/pdf';
        if ($ext !== 'pdf') {
            $copia = Foto::riduci($file->getRealPath());
            if ($copia) {
                $ridotta = "{$cartella}/{$token}-r.jpg";
                Storage::disk('local')->put($ridotta, $copia);
                $contenuto = $copia;
                $mime = 'image/jpeg';
            } else {
                $contenuto = file_get_contents($file->getRealPath());
                $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';
            }
        } else {
            $contenuto = file_get_contents($file->getRealPath());
        }

        $esito = $ocr->leggi($contenuto, $mime);
        $letto = $esito['esito'] === 'letto' ? $lettore->leggi($esito['testo']) : null;

        // Al più 5 bozze aperte per persona: le più vecchie si tolgono
        while (count($sessione) >= 5) {
            $vecchia = array_key_first($sessione);
            Storage::disk('local')->delete(array_filter([$sessione[$vecchia]['path'] ?? null, $sessione[$vecchia]['ridotta'] ?? null]));
            unset($sessione[$vecchia]);
        }
        $sessione[$token] = [
            'path' => $path,
            'ridotta' => $ridotta,
            'ext' => $ext,
            'originale' => mb_substr($file->getClientOriginalName(), 0, 255) ?: 'scontrino.' . $ext,
            'peso' => $file->getSize(),
            'nota' => trim($request->input('nota')),
            'ocr' => $esito,
            'letto' => $letto,
            'ora' => now()->timestamp,
        ];
        $request->session()->put(self::SESSIONE, $sessione);

        return redirect()->route('scontrini.conferma', $token);
    }

    // ----------------------------------------------------------------
    // Passo 2: dati letti da controllare (SN-2, SN-3)
    // ----------------------------------------------------------------

    public function conferma(Request $request, string $token, Fornitori $fornitori)
    {
        $this->richiediCarica($request);
        $bozza = $this->bozza($request, $token);
        if (! $bozza) {
            return redirect()->route('scontrini.create')->with('error', 'La foto messa da parte non c\'è più (più di un giorno o già registrata): ricomincia.');
        }
        $letto = $bozza['letto'] ?? [];

        return view('scontrini.conferma', [
            'token' => $token,
            'bozza' => $bozza,
            'letto' => $letto,
            'riconosciuto' => $fornitori->riconosci($letto['fornitore'] ?? null, $letto['partita_iva'] ?? null),
            'elenco' => $this->elencoFornitori(),
            'pagatoDaMe' => $this->socioCheAnticipa($request->user()) !== null,
        ]);
    }

    public function fotoBozza(Request $request, string $token)
    {
        $bozza = $this->bozza($request, $token);
        abort_unless($bozza, 404);
        $path = $bozza['ridotta'] ?? $bozza['path'];
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, 'scontrino.' . pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function store(Request $request, string $token)
    {
        $this->richiediCarica($request);
        $bozza = $this->bozza($request, $token);
        if (! $bozza) {
            return redirect()->route('scontrini.create')->with('error', 'La foto messa da parte non c\'è più (più di un giorno o già registrata): ricomincia.');
        }

        $nuovo = $request->input('fornitore') === 'nuovo';
        // «Pagato da me» (AN-1b, v0.43.0): solo per chi è collegato a un socio attivo maggiorenne
        $socio = $this->socioCheAnticipa($request->user());
        $conti = array_keys(PrimaNota::CONTI);
        if ($socio) {
            $conti[] = PrimaNotaScontrino::ANTICIPO;
        }
        $dati = Validator::make($request->all(), [
            'importo' => ['required', Importo::regola()],
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:' . today()->toDateString()],
            'conto' => ['required', Rule::in($conti)],
            'nota' => ['required', 'string', 'max:500'],
            'fornitore' => ['required', function ($a, $v, $fail) {
                if ($v !== 'nuovo' && ! Soggetto::whereKey((int) $v)->exists()) {
                    $fail('Scegli il fornitore o scrivi i dati di uno nuovo.');
                }
            }],
            'nuovo_nome' => [Rule::requiredIf($nuovo), 'nullable', 'string', 'max:200'],
            'nuovo_partita_iva' => ['nullable', 'regex:/^\d{11}$/', function ($a, $v, $fail) {
                if ($v && ! LettoreScontrino::partitaIvaValida($v)) {
                    $fail('La partita IVA non torna (la cifra di controllo è sbagliata): controllala sullo scontrino.');
                }
            }],
            'nuovo_indirizzo' => ['nullable', 'string', 'max:200'],
            'nuovo_comune' => ['nullable', 'string', 'max:100'],
            'aggiungi_piva' => ['nullable', 'regex:/^\d{11}$/'],
        ], [
            'data.before_or_equal' => 'La data non può essere nel futuro.',
            'data.required' => 'Scrivi la data dello scontrino.',
            'fornitore.required' => 'Scegli il fornitore o scrivi i dati di uno nuovo.',
            'nuovo_nome.required' => 'Scrivi la ragione sociale del fornitore nuovo.',
            'nuovo_partita_iva.regex' => 'La partita IVA ha 11 cifre.',
            'nota.required' => 'Scrivi che cosa hai comprato.',
            'conto.in' => 'Scegli come è stato pagato: cassa, banca' . ($socio ? ' o «Pagato da me»' : '') . '.',
        ], ['importo' => 'totale', 'conto' => 'pagato con'])->validate();

        $anno = (int) substr($dati['data'], 0, 4);
        $avvio = \App\Services\RinnoviQuota::annoAvvio();
        if ($anno < $avvio) {
            return back()->withInput()->withErrors(['data' => "Lo scontrino del {$anno} è prima dell'avvio della contabilità nel gestionale ({$avvio}): va registrato nel vecchio gestionale."]);
        }
        if ($this->anni->chiuso($anno)) {
            return back()->withInput()->withErrors(['data' => "L'anno {$anno} è chiuso: lo scontrino non si può registrare lì. Scrivi al Tesoriere."]);
        }

        $s = $this->scontrini->registra($bozza, [
            'importo' => Importo::leggi($dati['importo']),
            'data' => $dati['data'],
            'conto' => $dati['conto'],
            'anticipante_socio_id' => $socio?->id,
            'nota' => trim($dati['nota']),
            'fornitore_id' => $nuovo ? null : (int) $dati['fornitore'],
            'nuovo' => $nuovo ? [
                'nome' => $dati['nuovo_nome'],
                'partita_iva' => $dati['nuovo_partita_iva'] ?? null,
                'indirizzo' => $dati['nuovo_indirizzo'] ?? null,
                'comune' => $dati['nuovo_comune'] ?? null,
            ] : null,
            'aggiungi_piva' => $dati['aggiungi_piva'] ?? null,
        ], $request->user());

        $this->togliBozza($request, $token);

        // Presidente e Tesoriere vanno alla scheda (lo verificano subito); gli altri ai loro scontrini
        if ($this->puoVerificare($request->user())) {
            return redirect()->route('scontrini.show', $s)->with('success', "Scontrino n. {$s->numero} registrato su «Uscite da classificare»: scegli le categorie e registra la divisione.");
        }

        return redirect()->route('scontrini.miei')->with('registrato', $s->id);
    }

    public function annulla(Request $request, string $token)
    {
        $this->togliBozza($request, $token);

        return redirect()->route('scontrini.create');
    }

    // ----------------------------------------------------------------
    // Scheda (SN-6)
    // ----------------------------------------------------------------

    public function show(Request $request, PrimaNotaScontrino $scontrino)
    {
        $scontrino->load(['soggetto', 'registratoDa:id,name', 'file', 'anticipante', 'righe.categoria:id,nome',
            'movimenti' => fn ($q) => $q->with('categoria:id,nome,codice')->orderBy('id')]);
        $puo = $this->puoVerificare($request->user());
        $categorie = PrimaNotaCategoria::perMovimenti('uscita');
        // Categorie già usate dalle righe ma ora disattivate: restano sceglibili per quelle righe
        $usate = $scontrino->righe->pluck('categoria_id')->filter()->diff($categorie->pluck('id'));
        if ($usate->isNotEmpty()) {
            $categorie = $categorie->concat(PrimaNotaCategoria::whereIn('id', $usate)->get());
        }

        return view('scontrini.show', [
            's' => $scontrino,
            'nota' => $scontrino->notaRimborso(),
            'puo' => $puo,
            'chiuso' => $this->anni->chiuso($scontrino->anno),
            'categorie' => $categorie,
            'fornitori' => Soggetto::where(fn ($q) => $q->where('attivo', true)->orWhere('id', $scontrino->soggetto_id))->orderBy('nome')->get(),
            'splitAbilitato' => (bool) Impostazione::get('scontrini_split_abilitato', true),
        ]);
    }

    public function update(Request $request, PrimaNotaScontrino $scontrino)
    {
        $this->richiediVerifica($request);
        $chiuso = $this->anni->chiuso($scontrino->anno);
        $dati = Validator::make($request->all(), [
            'importo' => ['required', Importo::regola()],
            'data' => ['required', 'date_format:Y-m-d', 'before_or_equal:' . today()->toDateString(), function ($a, $v, $fail) use ($scontrino) {
                if ((int) substr((string) $v, 0, 4) !== (int) $scontrino->anno) {
                    $fail("La data deve restare nel {$scontrino->anno}: lo scontrino è numerato in quell'anno.");
                }
            }],
            'conto' => ['required', Rule::in(array_merge(array_keys(PrimaNota::CONTI), $scontrino->anticipante_socio_id ? [PrimaNotaScontrino::ANTICIPO] : []))],
            'soggetto_id' => ['required', 'integer', 'exists:gl_soggetti,id'],
            'motivazione' => [Rule::requiredIf($chiuso), 'nullable', 'string', 'min:15', 'max:1000'],
        ], [
            'data.before_or_equal' => 'La data non può essere nel futuro.',
            'motivazione.required' => "L'anno è chiuso: scrivi perché correggi lo scontrino.",
            'motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.',
        ], ['importo' => 'totale', 'soggetto_id' => 'fornitore'])->validateWithBag('dati');

        $this->conBorsa('dati', fn () => $this->scontrini->salvaDati($scontrino, [
            'importo' => Importo::leggi($dati['importo']),
            'data' => $dati['data'],
            'conto' => $dati['conto'],
            'soggetto_id' => (int) $dati['soggetto_id'],
            'motivazione' => $dati['motivazione'] ?? null,
        ]));
        $scontrino->refresh();

        return redirect()->route('scontrini.show', $scontrino)->with('success', $scontrino->stato === 'da_quadrare'
            ? 'Dati salvati. Il totale non torna più con i movimenti: rifai la divisione.'
            : 'Dati salvati.');
    }

    public function dividi(Request $request, PrimaNotaScontrino $scontrino)
    {
        $this->richiediVerifica($request);
        $chiuso = $this->anni->chiuso($scontrino->anno);
        $consentite = PrimaNotaCategoria::perMovimenti('uscita')->pluck('id')
            ->merge($scontrino->righe()->pluck('categoria_id'))->filter()->map(fn ($id) => (int) $id)->unique()->all();

        $v = Validator::make($request->all(), [
            'righe' => ['required', 'array', 'min:1', 'max:100'],
            'righe.*.descrizione' => ['required', 'string', 'max:200'],
            'righe.*.importo' => ['required', 'string', 'max:20'],
            'righe.*.categoria_id' => ['required', 'integer', Rule::in($consentite)],
            'motivazione' => [Rule::requiredIf($chiuso), 'nullable', 'string', 'min:15', 'max:1000'],
        ], [
            'righe.required' => 'Serve almeno una riga.',
            'righe.*.descrizione.required' => 'Ogni riga ha bisogno di una descrizione.',
            'righe.*.importo.required' => "Ogni riga ha bisogno dell'importo.",
            'righe.*.categoria_id.required' => 'Scegli la categoria di ogni riga.',
            'righe.*.categoria_id.in' => 'Scegli la categoria di ogni riga tra quelle di uscita.',
            'motivazione.required' => "L'anno è chiuso: scrivi perché rifai la divisione.",
            'motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.',
        ]);
        $righe = [];
        $v->after(function ($v) use ($request, $scontrino, &$righe) {
            $somma = 0;
            foreach ((array) $request->input('righe', []) as $i => $r) {
                $importo = self::importoConSegno($r['importo'] ?? null);
                if ($importo === null) {
                    $v->errors()->add("righe.{$i}.importo", 'Importo non valido in una riga: scrivilo come 12,50 (o -4,50 per uno sconto).');

                    continue;
                }
                $somma += Importo::centesimi($importo);
                $righe[] = ['descrizione' => trim((string) ($r['descrizione'] ?? '')), 'importo' => $importo, 'categoria_id' => (int) ($r['categoria_id'] ?? 0)];
            }
            if (! $v->errors()->has('righe.*') && $somma !== Importo::centesimi($scontrino->importo_totale)) {
                $v->errors()->add('righe', 'Le righe sommano ' . Importo::it(Importo::daCentesimi($somma), true) . ' e il totale è ' . Importo::it($scontrino->importo_totale, true) . ': devono tornare al centesimo.');
            }
            if (! Impostazione::get('scontrini_split_abilitato', true) && count(array_unique(array_column($righe, 'categoria_id'))) > 1) {
                $v->errors()->add('righe', 'La divisione su più categorie è spenta nelle impostazioni: usa una sola categoria.');
            }
        });
        $v->validateWithBag('divisione');

        $conti = $this->conBorsa('divisione', fn () => $this->scontrini->dividi($scontrino, $righe, $request->input('motivazione')));
        $n = count(array_unique(array_column($righe, 'categoria_id')));

        return redirect()->route('scontrini.show', $scontrino)->with('success', "Divisione registrata: {$n} "
            . ($n === 1 ? 'movimento' : 'movimenti') . ' in Prima Nota'
            . ($conti['tolti'] ? ', ' . $conti['tolti'] . ' ' . ($conti['tolti'] === 1 ? 'tolto' : 'tolti') : '') . '.');
    }

    public function aggiungiFoto(Request $request, PrimaNotaScontrino $scontrino)
    {
        $this->richiediVerifica($request);
        $chiuso = $this->anni->chiuso($scontrino->anno);
        $request->validateWithBag('foto', [
            'foto' => ['required', 'file', 'max:' . self::MAX_KB],
            'motivazione' => [Rule::requiredIf($chiuso), 'nullable', 'string', 'min:15', 'max:1000'],
        ], [
            'foto.required' => 'Scegli la foto o il PDF da aggiungere.',
            'foto.max' => 'Il file pesa più di 8 MB.',
            'motivazione.required' => "L'anno è chiuso: scrivi perché aggiungi la foto.",
        ]);
        $file = $request->file('foto');
        if (Foto::heic($file->getRealPath()) || ! in_array(Scontrini::estensione($file), ['jpg', 'png', 'pdf'], true)) {
            return back()->withErrors(['foto' => 'Serve una foto JPG o PNG o un PDF.'], 'foto');
        }
        $f = $this->scontrini->aggiungiFoto($scontrino, $file, $request->user(), $request->input('motivazione'));

        return redirect()->route('scontrini.show', $scontrino)->with('success', "Aggiunto {$f->nome}.");
    }

    public function foto(Request $request, PrimaNotaScontrino $scontrino, PrimaNotaScontrinoFile $file)
    {
        abort_unless($file->scontrino_id === $scontrino->id && Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->response($file->path, $file->nome, ['Cache-Control' => 'private, max-age=3600'],
            $request->boolean('scarica') ? 'attachment' : 'inline');
    }

    /** Anteprima ridotta (o miniatura con ?mini=1) per la scheda e l'elenco. */
    public function anteprima(Request $request, PrimaNotaScontrino $scontrino)
    {
        $path = $request->boolean('mini') ? ($scontrino->miniatura_path ?? $scontrino->anteprima_path) : $scontrino->anteprima_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, $scontrino->codice() . '.jpg', ['Cache-Control' => 'private, max-age=86400'], 'inline');
    }

    public function destroy(Request $request, PrimaNotaScontrino $scontrino)
    {
        $this->richiediVerifica($request);
        $chiuso = $this->anni->chiuso($scontrino->anno);
        $request->validateWithBag('elimina', [
            'conferma' => ['required', 'in:si'],
            'motivazione' => [Rule::requiredIf($chiuso), 'nullable', 'string', 'min:15', 'max:1000'],
        ], [
            'conferma.*' => "Conferma l'eliminazione.",
            'motivazione.required' => "L'anno è chiuso: scrivi perché elimini lo scontrino.",
            'motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.',
        ]);
        $codice = $scontrino->codice();
        $anno = $scontrino->anno;
        $this->conBorsa('elimina', fn () => $this->scontrini->elimina($scontrino, $request->input('motivazione')));

        return redirect()->route('scontrini.index', ['anno' => $anno])->with('success', "Scontrino {$codice} eliminato con i suoi movimenti.");
    }

    // ----------------------------------------------------------------
    // Supporto
    // ----------------------------------------------------------------

    /** «-4,50», «−4,50» o «4,50»: importo con due decimali, anche negativo (sconti). */
    public static function importoConSegno(mixed $grezzo): ?string
    {
        $t = trim(str_replace('−', '-', (string) $grezzo));
        $negativo = str_starts_with($t, '-');
        $valore = Importo::leggi(ltrim($t, '-'));
        if ($valore === null || Importo::centesimi($valore) === 0) {
            return null;
        }

        return $negativo ? '-' . $valore : $valore;
    }

    /** Fornitori in elenco per la finestra «Scegli dalla lista»: nome, dettaglio, quante volte usati. */
    private function elencoFornitori(): array
    {
        $usi = PrimaNota::whereNotNull('soggetto')->selectRaw('soggetto, COUNT(*) n')->groupBy('soggetto')->pluck('n', 'soggetto');

        return Soggetto::attivi()->orderBy('nome')->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'nome' => $s->nome,
                'dettaglio' => $s->dettaglio(),
                'partita_iva' => $s->partita_iva,
                'usi' => (int) ($usi[$s->nome] ?? 0),
            ])->all();
    }

    /** Socio dell'utente, se può anticipare una spesa (attivo e maggiorenne, E5). */
    private function socioCheAnticipa(User $u): ?\App\Models\Socio
    {
        $socio = $u->socio_id ? \App\Models\Socio::find($u->socio_id) : null;

        return \App\Services\NoteRimborso::puoAnticipare($socio) ? $socio : null;
    }

    /** Errori del servizio nella borsa della finestra da cui arriva la richiesta. */
    private function conBorsa(string $borsa, \Closure $operazione): mixed
    {
        try {
            return $operazione();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e->errorBag($borsa);
        }
    }

    private function puoVerificare(User $u): bool
    {
        return $u->puoModificare('prima_nota') && $u->puoAccedere('scontrini');
    }

    private function richiediCarica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('scontrini'), 403, 'Il tuo ruolo può solo consultare gli scontrini.');
    }

    private function richiediVerifica(Request $request): void
    {
        abort_unless($this->puoVerificare($request->user()), 403, 'Verificano e correggono gli scontrini il Presidente e il Tesoriere.');
    }

    /** Bozza del passo 1 (foto e dati letti), se c'è ancora. */
    private function bozza(Request $request, string $token): ?array
    {
        $b = $this->pulisciBozze($request)[$token] ?? null;

        return $b && Storage::disk('local')->exists($b['path']) ? $b : null;
    }

    private function togliBozza(Request $request, string $token): void
    {
        $sessione = (array) $request->session()->get(self::SESSIONE, []);
        if (isset($sessione[$token])) {
            Storage::disk('local')->delete(array_filter([$sessione[$token]['path'], $sessione[$token]['ridotta'] ?? null]));
            unset($sessione[$token]);
        }
        $request->session()->put(self::SESSIONE, $sessione);
    }

    /** Bozze più vecchie di un giorno: si cancellano con i loro file. */
    private function pulisciBozze(Request $request): array
    {
        $sessione = (array) $request->session()->get(self::SESSIONE, []);
        $limite = now()->subDay()->timestamp;
        foreach ($sessione as $t => $b) {
            if (($b['ora'] ?? 0) < $limite) {
                Storage::disk('local')->delete(array_filter([$b['path'] ?? null, $b['ridotta'] ?? null]));
                unset($sessione[$t]);
            }
        }
        $cartella = 'tmp/scontrini/' . $request->user()->id;
        $inUso = collect($sessione)->flatMap(fn ($b) => [$b['path'] ?? null, $b['ridotta'] ?? null])->filter()->all();
        foreach (Storage::disk('local')->files($cartella) as $path) {
            if (! in_array($path, $inUso, true) && Storage::disk('local')->lastModified($path) < $limite) {
                Storage::disk('local')->delete($path);
            }
        }
        $request->session()->put(self::SESSIONE, $sessione);

        return $sessione;
    }
}
