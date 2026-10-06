<?php

namespace App\Http\Controllers;

use App\Models\Promemoria;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Services\AuditLogService;
use App\Services\Quota;
use App\Services\RicevuteQuota;
use App\Services\RinnoviQuota;
use App\Support\Importo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rinnovi quota (rinnovi-quota.md, tavole RQ-1…RQ-6). Rotte sotto
 * 'permesso:rinnovi_quota': Presidente e Tesoriere registrano e annullano,
 * il Revisore consulta.
 */
class RinnoviController extends Controller
{
    public const FILTRI_SITUAZIONE = [
        'versata' => 'In regola',
        'non_versata' => 'Non ancora versata',
    ];

    public function __construct(private RinnoviQuota $rinnovi, private RicevuteQuota $ricevute)
    {
    }

    // ----------------------------------------------------------------
    // Elenco (RQ-1)
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $f = $this->filtri($request);
        $anno = $f['anno'];

        $soci = $this->query($f)
            ->with(['rinnovi' => fn ($q) => $q->valido()->where('anno', $anno)])
            ->orderBy('cognome')->orderBy('nome')
            ->paginate(25)->withQueryString();

        $base = $this->sociDellAnno($anno);
        $attivi = (clone $base)->count();
        $versate = Rinnovo::valido()->where('anno', $anno)->whereIn('socio_id', (clone $base)->select('id'));
        $inRegola = (clone $versate)->count();
        $nonVersata = RinnoviQuota::situazione(false, $anno);

        $giovani = (clone $base)->get(['id', 'data_nascita', 'tipo'])->filter(fn ($s) => RinnoviQuota::categoria($s, $anno) === 'giovane')->count();
        $ricevute = Rinnovo::where('anno', $anno)->valido();

        return view('rinnovi.index', [
            'soci' => $soci,
            'filtri' => $f,
            'anno' => $anno,
            'anni' => range(now()->year + 1, max(2026, (int) (Rinnovo::min('anno') ?? now()->year))),
            'kpi' => [
                'attivi' => $attivi,
                'giovani' => $giovani,
                'in_regola' => $inRegola,
                'incassato' => (float) (clone $versate)->sum('importo'),
                'mancanti' => $attivi - $inRegola,
                'ricevute' => (clone $ricevute)->count(),
                'inviate' => (clone $ricevute)->where('ricevuta_stato', 'inviata')->count(),
            ],
            'nonVersata' => $nonVersata,
            'totaleFiltro' => (float) Rinnovo::valido()->where('anno', $anno)->whereIn('socio_id', $this->query($f)->select('id'))->sum('importo'),
            // Nell'ordine dell'anno sociale: l'invito di dicembre (anno dopo) per primo
            'promemoria' => Promemoria::where('funzione', 'quote_non_versate')->get()
                ->sortBy(fn ($p) => sprintf('%d-%02d-%02d', $p->parametro('anno_sociale') === 'successivo' ? 0 : 1, $p->mese ?? 13, $p->giorno ?? 32))->values(),
            'puoModificare' => $request->user()->puoModificare('rinnovi_quota'),
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $f = $this->filtri($request);
        $anno = $f['anno'];
        $soci = $this->query($f)->with(['rinnovi' => fn ($q) => $q->valido()->where('anno', $anno)])
            ->orderBy('cognome')->orderBy('nome')->get();

        AuditLogService::scrivi([
            'evento' => 'RINNOVI_ESPORTATI',
            'entita_tipo' => 'Rinnovo',
            'anno' => $anno,
            'livello_rischio' => 'MEDIUM',
            'dettaglio' => "Esportazione CSV dei rinnovi {$anno}: {$soci->count()} soci.",
        ]);

        return response()->streamDownload(function () use ($soci, $anno) {
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['Cognome', 'Nome', 'Categoria', 'Quota dovuta', 'Situazione', 'Versata il', 'Modalità', 'Importo', 'Ricevuta'], ';');
            $t = fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
            foreach ($soci as $s) {
                $r = $s->rinnovi->first();
                $cat = RinnoviQuota::categoria($s, $anno);
                fputcsv($fh, array_map($t, [
                    $s->cognome, $s->nome, Rinnovo::CATEGORIE[$cat], Importo::it(RinnoviQuota::importo($cat)),
                    RinnoviQuota::SITUAZIONI[RinnoviQuota::situazione((bool) $r, $anno)][0],
                    $r?->data_pagamento?->format('d/m/Y'), $r?->metodoLabel(), $r ? Importo::it($r->importo) : '', $r?->numero_ricevuta,
                ]), ';');
            }
            fclose($fh);
        }, "rinnovi-{$anno}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ----------------------------------------------------------------
    // Registra quota (RQ-2)
    // ----------------------------------------------------------------

    public function create(Request $request)
    {
        $this->richiediModifica($request);

        $socio = $request->filled('socio') ? Socio::where('stato', 'attivo')->find((int) $request->query('socio')) : null;
        $anno = $socio ? (int) $request->query('anno', $this->rinnovi->annoProposto($socio)) : now()->year;
        $anno = max(now()->year - 1, min(now()->year + 1, $anno));
        $categoria = $socio ? RinnoviQuota::categoria($socio, $anno) : null;

        return view('rinnovi.create', [
            'socio' => $socio,
            'anno' => $anno,
            'categoria' => $categoria,
            'importo' => $categoria ? RinnoviQuota::importo($categoria) : null,
            'giaVersata' => $socio ? Rinnovo::valido()->where('socio_id', $socio->id)->where('anno', $anno)->first() : null,
            'serveAssenso' => $socio ? RinnoviQuota::serveAssenso($socio, today()) : false,
            'destinatario' => $socio?->destinatarioPrivacy(),
            'soci' => Socio::where('stato', 'attivo')->where('pseudonimizzato', false)->orderBy('cognome')->orderBy('nome')->get(['id', 'cognome', 'nome', 'data_nascita', 'tipo']),
            'prossimo' => $this->rinnovi->prossimoNumero(now()->year),
            'rasdOggi' => \App\Services\ProfiloEnte::al(today())->iscrittoRasd(),
            'annoAvvio' => RinnoviQuota::annoAvvio(),
            'quote' => ['ordinario' => RinnoviQuota::importo('ordinario'), 'giovane' => RinnoviQuota::importo('giovane')],
            'da' => $request->query('da') === 'socio' ? 'socio' : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->richiediModifica($request);

        $socio = Socio::where('stato', 'attivo')->where('pseudonimizzato', false)->find((int) $request->input('socio_id'));
        abort_unless($socio, 422, 'Scegli un socio attivo.');
        $anno = (int) $request->input('anno');
        $categoria = RinnoviQuota::categoria($socio, $anno ?: now()->year);
        $dovuto = RinnoviQuota::importo($categoria);
        $giovane = $categoria === 'giovane';
        $data = $request->input('data_pagamento');
        $assenso = $data && strtotime($data) ? RinnoviQuota::serveAssenso($socio, Carbon::parse($data)) : false;

        $dati = $request->validate([
            'anno' => ['required', 'integer', 'between:' . (now()->year - 1) . ',' . (now()->year + 1)],
            'importo' => ['required', Importo::regola()],
            'data_pagamento' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:' . (now()->year - 2) . '-01-01'],
            'metodo' => ['required', Rule::in(array_keys(Rinnovo::METODI))],
            'versato_da_nome' => [Rule::requiredIf($giovane), 'nullable', 'string', 'max:150'],
            'versato_da_cf' => [Rule::requiredIf($giovane), 'nullable', 'string', 'regex:/^[A-Za-z0-9]{16}$/'],
            // 'accepted' vale anche con il campo assente: solo se serve l'assenso
            'assenso' => $assenso ? ['required', 'accepted'] : ['nullable'],
            'note' => ['nullable', 'string', 'max:1000'],
            'invia' => ['nullable', 'boolean'],
        ], [
            'versato_da_nome.required' => 'Per un socio giovane indica il genitore che versa: compare sulla ricevuta.',
            'versato_da_cf.required' => 'Serve il codice fiscale di chi versa: compare sulla ricevuta.',
            'versato_da_cf.regex' => 'Il codice fiscale ha 16 caratteri, lettere e numeri.',
            'assenso.required' => 'Il socio ha 14 anni o più: serve il suo assenso al rinnovo (D.Lgs. 36/2021, art. 16).',
            'data_pagamento.before_or_equal' => 'La data del versamento non può essere futura.',
        ], ['data_pagamento' => 'data del versamento', 'metodo' => 'modalità', 'versato_da_nome' => 'versata da', 'versato_da_cf' => 'codice fiscale di chi versa']);

        $importo = (float) Importo::leggi($dati['importo']);
        if (abs($importo - $dovuto) >= 0.005 && blank($dati['note'] ?? null)) {
            return back()->withInput()->withErrors(['note' => 'L\'importo è diverso dalla quota ' . ($giovane ? '«giovane»' : '«ordinario»')
                . ' di ' . Importo::it($dovuto, true) . ': scrivi il motivo nelle note.']);
        }

        try {
            $r = $this->rinnovi->registra($socio, [
                'anno' => (int) $dati['anno'],
                'importo' => $importo,
                'data_pagamento' => $dati['data_pagamento'],
                'metodo' => $dati['metodo'],
                'versato_da_nome' => $dati['versato_da_nome'] ?? null,
                'versato_da_cf' => $dati['versato_da_cf'] ?? null,
                'assenso' => (bool) ($dati['assenso'] ?? false),
                'note' => $dati['note'] ?? null,
            ], $request->user());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $messaggio = "Quota {$r->anno} di {$socio->cognome} {$socio->nome} registrata: ricevuta n. {$r->numero_ricevuta}";
        try {
            $this->ricevute->genera($r);
            if ($request->boolean('invia')) {
                $dest = RicevuteQuota::destinatario($r);
                $messaggio .= ! $dest ? ' (nessuna email: scaricala e consegnala).'
                    : ($this->ricevute->invia($r) ? ", inviata a {$dest['email']}." : ': invio non riuscito, riprova dalla scheda.');
            } else {
                $messaggio .= '.';
            }
        } catch (\Throwable $e) {
            report($e);
            $messaggio .= ': ricevuta da rigenerare (errore nel PDF).';
        }

        return redirect($request->input('da') === 'socio' ? route('soci.show', $socio) . '#quote' : route('rinnovi.index', ['anno' => $r->anno]))
            ->with('success', $messaggio);
    }

    // ----------------------------------------------------------------
    // Ricevuta: scarica, invia, annulla
    // ----------------------------------------------------------------

    public function ricevuta(Request $request, Rinnovo $rinnovo)
    {
        abort_unless($rinnovo->numero_ricevuta, 404);
        $pdf = $this->ricevute->contenuto($rinnovo);
        $nome = 'Ricevuta-' . str_replace('/', '-', $rinnovo->numero_ricevuta) . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('scarica') ? 'attachment' : 'inline') . '; filename="' . $nome . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function invia(Request $request, Rinnovo $rinnovo)
    {
        $this->richiediModifica($request);
        abort_if($rinnovo->isAnnullato(), 422, 'La ricevuta è annullata.');
        $dest = RicevuteQuota::destinatario($rinnovo);
        if (! $dest) {
            return back()->with('error', 'Il socio non ha un indirizzo email (per un minore: quello del genitore). Scarica la ricevuta e consegnala.');
        }

        return $this->ricevute->invia($rinnovo)
            ? back()->with('success', "Ricevuta n. {$rinnovo->numero_ricevuta} inviata a {$dest['email']}.")
            : back()->with('error', 'Invio non riuscito: riprova più tardi. L\'errore è nel registro delle email.');
    }

    public function annulla(Request $request, Rinnovo $rinnovo)
    {
        $this->richiediModifica($request);
        $dati = $request->validateWithBag('annulla' . $rinnovo->id, [
            'motivo' => ['required', 'string', 'min:15', 'max:1000'],
            'avvisa' => ['nullable', 'boolean'],
        ], ['motivo.required' => 'Scrivi perché annulli la ricevuta.', 'motivo.min' => 'La motivazione deve avere almeno 15 caratteri.']);

        try {
            $r = $this->rinnovi->annulla($rinnovo, $dati['motivo'], $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $messaggio = "Ricevuta n. {$r->numero_ricevuta} annullata: il movimento è uscito dalla Prima Nota.";
        try {
            $this->ricevute->genera($r);   // timbro «annullata»
            if ($request->boolean('avvisa')) {
                $messaggio .= $this->ricevute->invia($r) ? ' Il socio è stato avvisato.' : ' Avviso al socio non inviato.';
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', $messaggio);
    }

    // ----------------------------------------------------------------
    // Supporto
    // ----------------------------------------------------------------

    /** Soci che devono la quota dell'anno: attivi, iscritti entro l'anno, non pseudonimizzati. */
    private function sociDellAnno(int $anno): Builder
    {
        return Socio::query()->where('stato', 'attivo')->where('pseudonimizzato', false)
            ->where(fn ($q) => $q->whereNull('data_prima_iscrizione')->orWhere('data_prima_iscrizione', '<=', "{$anno}-12-31"));
    }

    private function query(array $f): Builder
    {
        $anno = $f['anno'];
        $versata = fn ($q) => $q->valido()->where('anno', $anno);

        return $this->sociDellAnno($anno)
            ->when($f['situazione'] === 'versata', fn ($q) => $q->whereHas('rinnovi', $versata))
            ->when($f['situazione'] === 'non_versata', fn ($q) => $q->whereDoesntHave('rinnovi', $versata))
            ->when($f['categoria'] !== '', function ($q) use ($f, $anno) {
                // Giovane = minorenne al 1° gennaio (RinnoviQuota::categoria)
                $limite = Carbon::create($anno, 1, 1)->subYears(18)->toDateString();
                $f['categoria'] === 'giovane'
                    ? $q->where(fn ($w) => $w->where('data_nascita', '>', $limite)->orWhere(fn ($x) => $x->whereNull('data_nascita')->whereIn('tipo', ['giovane', 'minorenne'])))
                    : $q->where(fn ($w) => $w->where('data_nascita', '<=', $limite)->orWhere(fn ($x) => $x->whereNull('data_nascita')->where('tipo', 'ordinario')));
            })
            ->when($f['q'] !== '', function ($q) use ($f, $anno) {
                $t = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $f['q']) . '%';
                $q->where(fn ($w) => $w->where('cognome', 'like', $t)->orWhere('nome', 'like', $t)
                    ->orWhereHas('rinnovi', fn ($r) => $r->where('anno', $anno)->where('numero_ricevuta', 'like', $t)));
            });
    }

    /** @return array{anno: int, situazione: string, categoria: string, q: string} */
    private function filtri(Request $request): array
    {
        $anno = (int) $request->query('anno', now()->year);

        return [
            'anno' => $anno >= 2020 && $anno <= now()->year + 1 ? $anno : now()->year,
            'situazione' => array_key_exists($s = (string) $request->query('situazione', ''), self::FILTRI_SITUAZIONE) ? $s : '',
            'categoria' => in_array($c = (string) $request->query('categoria', ''), ['ordinario', 'giovane'], true) ? $c : '',
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('rinnovi_quota'), 403, 'Il tuo ruolo può solo consultare i rinnovi.');
    }
}
