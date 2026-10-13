<?php

namespace App\Http\Controllers;

use App\Models\Domanda;
use App\Models\Impostazione;
use App\Models\VotoCd;
use App\Services\AuditLogService;
use App\Services\IscrizioneSocio;
use App\Services\Quota;
use App\Services\VotazioneCd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Area riservata delle domande di adesione (domande-iscrizione.md §3,
 * tavole "Domande — …"): elenco, scheda, voto del CD, decisioni del
 * Presidente, iscrizione nel libro soci.
 *
 * Permessi: sezione "domande" per vedere (middleware), puoModificare
 * per reinviare link, iscrivere soci e cambiare la promozione; ruolo
 * "presidente" per decidere la parità e inviare il diniego (statuto artt.
 * 6.5 e 16.4). La votazione non si chiude a mano: alla scadenza o quando
 * hanno votato tutti (Paolo, 29/09/2026).
 */
class DomandeController extends Controller
{
    /** Filtri dell'elenco: etichetta e stati. */
    private const FILTRI = [
        'aperte' => 'Tutte tranne le chiuse',
        'da_fare' => 'Da fare',
        'in_votazione' => 'In votazione',
        'accolte' => 'Accolte, quota da versare',
        'non_accolte' => 'Non accolte',
        'iscritte' => 'Iscritte nel libro soci',
        'decadute' => 'Decadute',
        'tutte' => 'Tutte',
    ];

    public function __construct(private VotazioneCd $votazione)
    {
    }

    public function index(Request $request): View
    {
        $filtri = [
            'stato' => array_key_exists($request->query('stato'), self::FILTRI) ? $request->query('stato') : 'aperte',
            'anno' => (int) $request->query('anno') ?: null,
            'q' => trim((string) $request->query('q', '')),
        ];

        $query = Domanda::query()->with('voti:id,domanda_id,voto');
        match ($filtri['stato']) {
            'aperte' => $query->where(fn ($q) => $q->whereIn('stato', ['in_verifica', 'in_attesa', 'da_rifiutare', 'in_parita'])
                ->orWhere(fn ($q) => $q->where('stato', 'approvata')->whereNull('socio_id'))),
            'da_fare' => $query->whereIn('stato', ['da_rifiutare', 'in_parita']),
            'in_votazione' => $query->where('stato', 'in_attesa'),
            'accolte' => $query->where('stato', 'approvata')->whereNull('socio_id'),
            'non_accolte' => $query->whereIn('stato', ['da_rifiutare', 'rifiutata']),
            'iscritte' => $query->whereNotNull('socio_id'),
            'decadute' => $query->where('stato', 'decaduta'),
            default => null,
        };
        if ($filtri['anno']) {
            $query->whereYear('created_at', $filtri['anno']);
        }
        if ($filtri['q'] !== '') {
            $termine = '%' . str_replace(['%', '_'], ['\%', '\_'], $filtri['q']) . '%';
            $query->where(fn ($q) => $q->where('cognome', 'like', $termine)->orWhere('nome', 'like', $termine)
                ->orWhere('codice_fiscale', 'like', $termine)->orWhere('genitore_nome', 'like', $termine));
        }

        $annoCorrente = now()->year;

        return view('domande.index', [
            'domande' => $query->latest('id')->paginate(25)->withQueryString(),
            'filtri' => $filtri,
            'opzioniStato' => self::FILTRI,
            'anni' => Domanda::selectRaw('YEAR(created_at) as anno')->distinct()->orderByDesc('anno')->pluck('anno'),
            'kpi' => [
                'da_fare' => Domanda::whereIn('stato', ['da_rifiutare', 'in_parita'])->count(),
                'in_votazione' => Domanda::where('stato', 'in_attesa')->count(),
                'quota' => Domanda::where('stato', 'approvata')->whereNull('socio_id')->count(),
                'nuovi_soci' => Domanda::whereNotNull('socio_id')->whereYear('quota_versata_il', $annoCorrente)->count(),
            ],
            'annoCorrente' => $annoCorrente,
            'promozione' => Quota::promozioneAttiva(),
        ]);
    }

    public function show(Domanda $domanda): View
    {
        $domanda->load(['voti.membroCd', 'socio', 'verbaleCd']);

        return view('domande.show', [
            'domanda' => $domanda,
            'presidente' => auth()->user()->haRuolo('presidente'),
            'puoModificare' => auth()->user()->puoModificare('domande'),
            'metodi' => IscrizioneSocio::METODI,
            'iban' => \App\Models\Associazione::istanza()->iban,
            'promozione' => Quota::promozioneAttiva(),
        ]);
    }

    // ── Voto del CD ──────────────────────────────────────────────────────

    public function reinviaLink(Request $request, Domanda $domanda, VotoCd $voto): RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($voto->domanda_id === $domanda->id, 404);

        return $this->votazione->reinviaLink($voto)
            ? back()->with('success', "Link rimandato a {$voto->consigliere_nome}.")
            : back()->with('error', 'Il link non si può rimandare: il consigliere ha già votato, non ha un indirizzo email o la votazione è chiusa.');
    }

    public function decidiParita(Request $request, Domanda $domanda): RedirectResponse
    {
        $this->richiediPresidente($request);
        $dati = $request->validate([
            'decisione' => ['required', 'in:accogli,respingi'],
            'motivo' => ['nullable', 'required_if:decisione,respingi', 'string', 'min:20', 'max:2000'],
        ], [
            'motivo.required_if' => 'Per non accogliere la domanda serve la motivazione per il richiedente.',
            'motivo.min' => 'La motivazione deve avere almeno :min caratteri.',
            'motivo.max' => 'La motivazione non deve superare :max caratteri.',
        ],
            ['motivo' => 'motivazione', 'decisione' => 'decisione']);

        $fatto = $this->votazione->decidiParita($domanda, $dati['decisione'] === 'accogli', isset($dati['motivo']) ? trim($dati['motivo']) : null, $request->user());

        return $fatto
            ? redirect()->route('domande.show', $domanda)->with('success', $dati['decisione'] === 'accogli'
                ? 'Domanda accolta: al richiedente è partita l\'email con la quota.'
                : 'Domanda non accolta: al richiedente è partito il diniego motivato.')
            : back()->with('error', 'La domanda non è più in attesa della tua decisione.');
    }

    public function diniego(Request $request, Domanda $domanda): RedirectResponse
    {
        $this->richiediPresidente($request);
        $dati = $request->validate(['motivo' => ['required', 'string', 'min:20', 'max:2000']], [
            'motivo.required' => 'Scrivi la motivazione del diniego per il richiedente.',
            'motivo.min' => 'La motivazione deve avere almeno :min caratteri.',
            'motivo.max' => 'La motivazione non deve superare :max caratteri.',
        ], ['motivo' => 'motivazione']);

        return $this->votazione->inviaDiniego($domanda, trim($dati['motivo']), $request->user())
            ? redirect()->route('domande.show', $domanda)->with('success', 'Diniego inviato al richiedente.')
            : back()->with('error', 'Il diniego è già stato inviato.');
    }

    // ── Quota e libro soci ───────────────────────────────────────────────

    public function iscrivi(Request $request, Domanda $domanda, IscrizioneSocio $iscrizione): RedirectResponse
    {
        $this->richiediModifica($request);
        $dati = $request->validate([
            'quota_versata_il' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . ($domanda->esito_il ?? $domanda->created_at)->toDateString()],
            'metodo' => ['required', 'in:' . implode(',', array_keys(IscrizioneSocio::METODI))],
            'promo' => ['nullable', 'boolean'],
        ], [
            'quota_versata_il.after_or_equal' => 'La quota non può essere stata versata prima dell\'accoglimento.',
        ], ['quota_versata_il' => 'data del versamento', 'metodo' => 'modalità']);

        try {
            $socio = $iscrizione->iscrivi($domanda, Carbon::parse($dati['quota_versata_il']), $dati['metodo'], $request->boolean('promo'), $request->user());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('soci.show', $socio)->with('success', "{$socio->cognome} {$socio->nome} è iscritto nel libro soci; quota registrata in Prima Nota.");
    }

    public function promozione(Request $request): RedirectResponse
    {
        $this->richiediModifica($request);
        $attiva = $request->boolean('attiva');
        Impostazione::set('quota_promo_ultimo_trimestre', $attiva, 'boolean',
            "Se attiva, chi è accolto da ottobre a dicembre versa la quota dell'anno successivo: diventa socio subito e la quota vale fino al 31 dicembre dell'anno dopo. Si può cambiare domanda per domanda.");

        AuditLogService::scrivi([
            'evento' => 'IMPOSTAZIONE_MODIFICATA',
            'entita_tipo' => 'Impostazione',
            'dettaglio' => 'Promozione ottobre–dicembre ' . ($attiva ? 'attivata' : 'disattivata') . '.',
        ]);

        return back()->with('success', 'Promozione di ottobre–dicembre ' . ($attiva ? 'attivata.' : 'disattivata.'));
    }

    // ── Documenti ────────────────────────────────────────────────────────

    public function ricevuta(Domanda $domanda): StreamedResponse
    {
        abort_unless($domanda->ricevuta_path && Storage::disk('local')->exists($domanda->ricevuta_path), 404, 'Ricevuta non disponibile.');

        return Storage::disk('local')->download($domanda->ricevuta_path, "domanda-adesione-{$domanda->id}.pdf");
    }

    public function verbale(Domanda $domanda): View
    {
        abort_unless($domanda->verbaleCd, 404, 'Verbale non ancora redatto.');

        return view('domande.verbale', ['domanda' => $domanda, 'verbale' => $domanda->verbaleCd]);
    }

    // ── Domande di prova ─────────────────────────────────────────────────

    /** Solo domande di prova: si eliminano del tutto (non sono mai entrate nell'audit). */
    public function destroy(Request $request, Domanda $domanda): RedirectResponse
    {
        $this->richiediModifica($request);
        abort_unless($domanda->is_test && $domanda->socio_id === null, 403, 'Si eliminano solo le domande di prova.');

        $domanda->verbaleCd?->delete();
        $domanda->cancellaPerFineConservazione();

        return redirect()->route('domande.index')->with('success', 'Domanda di prova eliminata.');
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('domande'), 403, 'Il tuo ruolo può solo consultare questa sezione.');
    }

    private function richiediPresidente(Request $request): void
    {
        abort_unless($request->user()->haRuolo('presidente'), 403, 'Solo il Presidente può farlo (statuto, artt. 6.5 e 16.4).');
    }
}
