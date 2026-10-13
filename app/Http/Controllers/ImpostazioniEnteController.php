<?php

namespace App\Http\Controllers;

use App\Models\Associazione;
use App\Models\AssociazionePeriodo;
use App\Models\Impostazione;
use App\Models\Scadenza;
use App\Services\AuditLogService;
use App\Services\ProfiloEnte;
use App\Services\Scadenzario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Impostazioni › Ente (profilo-ente.md, tavole "Impostazioni › Ente"):
 * dati fissi dell'ente, periodi (forma, denominazione, sede, regime, testi
 * in vigore), lista di controllo del passaggio, apertura delle iscrizioni
 * online.
 *
 * Permessi: la pagina richiede la sezione "impostazioni"; le modifiche
 * solo il ruolo "presidente" (decisione di Paolo del 05/10/2026).
 * Un periodo già cominciato non cambia più, tranne RASD e atto: i documenti
 * emessi nel periodo ne riportano i dati, e una modifica li riscriverebbe.
 */
class ImpostazioniEnteController extends Controller
{
    public function index(): View
    {
        $periodi = ProfiloEnte::periodi();
        $oggi = today();
        $inVigore = ProfiloEnte::inVigoreOggi();
        $prossimo = $periodi->first(fn (AssociazionePeriodo $p) => $p->dal->gt($oggi));

        return view('impostazioni.ente', [
            'associazione' => Associazione::istanza(),
            'periodi' => $periodi,
            'inVigore' => $inVigore,
            'mostrato' => ProfiloEnte::al(),
            'prossimo' => $prossimo,
            'liste' => $periodi->filter(fn (AssociazionePeriodo $p) => $p->dal->gt($oggi))
                ->mapWithKeys(fn (AssociazionePeriodo $p) => [$p->id => ProfiloEnte::listaPassaggio($p)]),
            'iscrizioniAperte' => (bool) Impostazione::get('iscrizioni_aperte', false),
            'presidente' => $this->ePresidente(),
            // Affiliazione e assicurazione (tavole SC-2, SC-3): scadenze dello Scadenzario
            'affiliazione' => Scadenza::with('ultimoRinnovo.movimento')->where('chiave', Scadenza::AFFILIAZIONE)->first(),
            'polizza' => Scadenza::with('ultimoRinnovo.movimento')->where('chiave', Scadenza::POLIZZA)->first(),
            'avvisoAffiliazione' => Scadenzario::avvisoAffiliazione(),
        ]);
    }

    // ── Affiliazione e polizza RC (tavole SC-2, SC-3) ─────────────────────
    // La scadenza non si scrive qui: la tiene lo Scadenzario («Rinnovata»).

    public function updateAffiliazione(Request $request): RedirectResponse
    {
        $this->richiediPresidente($request);
        $dati = $request->validateWithBag('affiliazione', [
            'rete_affiliazione_codice' => ['nullable', 'string', 'max:255'],
            'rete_affiliazione_referente' => ['nullable', 'string', 'max:255'],
            'rete_affiliazione_contatto' => ['nullable', 'string', 'max:255'],
        ], [], ['rete_affiliazione_codice' => 'codice di affiliazione', 'rete_affiliazione_referente' => 'comitato / referente',
            'rete_affiliazione_contatto' => 'contatto del referente']);

        Associazione::istanza()->fill($dati)->save();
        $this->audit('Dati dell\'affiliazione all\'EPS modificati.');

        return redirect()->route('impostazioni.index')->with('success', 'Dati dell\'affiliazione salvati.');
    }

    public function updatePolizza(Request $request): RedirectResponse
    {
        $this->richiediPresidente($request);
        $dati = $request->validateWithBag('polizza', [
            'polizza_compagnia' => ['nullable', 'string', 'max:255'],
            'polizza_numero' => ['nullable', 'string', 'max:100'],
            'polizza_copertura' => ['nullable', 'string', 'max:255'],
        ], [], ['polizza_compagnia' => 'compagnia', 'polizza_numero' => 'numero di polizza', 'polizza_copertura' => 'copertura']);

        Associazione::istanza()->fill($dati)->save();
        $this->audit('Dati della polizza RC modificati.');

        return redirect()->route('impostazioni.index')->with('success', 'Dati della polizza salvati.');
    }

    // ── Dati fissi ───────────────────────────────────────────────────────

    public function editDati(Request $request): View
    {
        $this->richiediPresidente($request);

        return view('impostazioni.ente-dati', ['associazione' => Associazione::istanza()]);
    }

    public function updateDati(Request $request): RedirectResponse
    {
        $this->richiediPresidente($request);
        $request->merge([
            'iban' => $request->filled('iban') ? strtoupper(str_replace(' ', '', (string) $request->input('iban'))) : null,
            'codice_fiscale' => strtoupper(trim((string) $request->input('codice_fiscale'))),
        ]);
        $dati = $request->validate([
            'codice_fiscale' => ['required', 'regex:/^([0-9]{11}|[A-Z0-9]{16})$/'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'email_pec' => ['nullable', 'email:rfc', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'banca_nome' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/'],
            'bic_swift' => ['nullable', 'string', 'max:15'],
        ], [
            'codice_fiscale.regex' => 'Il codice fiscale dell\'ente ha 11 cifre (o 16 caratteri).',
            'iban.regex' => "L'IBAN non è valido: due lettere, due cifre e il resto del codice, senza spazi.",
        ], ['email_pec' => 'PEC', 'banca_nome' => 'banca', 'bic_swift' => 'BIC']);

        Associazione::istanza()->fill($dati)->save();
        $this->audit('Dati fissi dell\'ente modificati.');

        return redirect()->route('impostazioni.index')->with('success', 'Dati dell\'ente salvati.');
    }

    // ── Periodi ──────────────────────────────────────────────────────────

    public function createPeriodo(Request $request): View
    {
        $this->richiediPresidente($request);
        $ultimo = ProfiloEnte::periodi()->last();

        // Si parte dai dati dell'ultimo periodo: di solito cambiano solo forma,
        // denominazione e regime.
        $nuovo = new AssociazionePeriodo($ultimo ? $ultimo->only([
            'denominazione', 'sigla', 'forma_giuridica', 'forma_descrizione', 'sede_legale',
            'regime_fiscale', 'ente_affiliazione', 'statuto_versione', 'informativa_versione',
        ]) : ['statuto_versione' => '1.0', 'informativa_versione' => '1.0']);

        return view('impostazioni.periodo', ['periodo' => $nuovo, 'iniziato' => false, 'versioni' => $this->versioni()]);
    }

    public function storePeriodo(Request $request): RedirectResponse
    {
        $this->richiediPresidente($request);
        $periodo = new AssociazionePeriodo();
        $periodo->fill($this->validaPeriodo($request, null))->save();
        $this->audit("Periodo dal {$periodo->dal->format('d/m/Y')} creato ({$periodo->forma_giuridica}, {$periodo->regime_fiscale}).", $periodo);

        return redirect()->route('impostazioni.index')->with('success', "Periodo dal {$periodo->dal->format('d/m/Y')} salvato.");
    }

    public function editPeriodo(Request $request, AssociazionePeriodo $periodo): View
    {
        $this->richiediPresidente($request);

        return view('impostazioni.periodo', [
            'periodo' => $periodo,
            'iniziato' => $periodo->dal->lte(today()),
            'versioni' => $this->versioni(),
        ]);
    }

    public function updatePeriodo(Request $request, AssociazionePeriodo $periodo): RedirectResponse
    {
        $this->richiediPresidente($request);
        if ($periodo->dal->lte(today())) {
            // Periodo già cominciato: solo RASD e atto (gli altri campi sono
            // in sola lettura nella pagina e non arrivano).
            $dati = $request->validate([
                'rasd_numero' => ['nullable', 'string', 'max:50'],
                'rasd_data_iscrizione' => ['nullable', 'date'],
                'atto' => ['nullable', 'string', 'max:255'],
            ], [], ['rasd_data_iscrizione' => 'data di iscrizione al RASD']);
            if (! $periodo->sportivaDilettantistica()) {
                unset($dati['rasd_numero'], $dati['rasd_data_iscrizione']);
            }
        } else {
            $dati = $this->validaPeriodo($request, $periodo);
        }
        $periodo->fill($dati)->save();
        $this->audit("Periodo dal {$periodo->dal->format('d/m/Y')} modificato.", $periodo);

        return redirect()->route('impostazioni.index')->with('success', "Periodo dal {$periodo->dal->format('d/m/Y')} salvato.");
    }

    public function destroyPeriodo(Request $request, AssociazionePeriodo $periodo): RedirectResponse
    {
        $this->richiediPresidente($request);
        abort_if($periodo->dal->lte(today()), 403, 'Un periodo già cominciato non si elimina: i documenti emessi ne riportano i dati.');
        $dal = $periodo->dal->format('d/m/Y');
        $periodo->delete();
        $this->audit("Periodo dal {$dal} eliminato.");

        return redirect()->route('impostazioni.index')->with('success', "Periodo dal {$dal} eliminato.");
    }

    // ── Passaggio ────────────────────────────────────────────────────────

    public function passaggio(AssociazionePeriodo $periodo): View
    {
        $precedente = ProfiloEnte::periodi()->filter(fn (AssociazionePeriodo $p) => $p->dal->lt($periodo->dal))->last();

        return view('impostazioni.passaggio', [
            'periodo' => $periodo,
            'precedente' => $precedente,
            'voci' => ProfiloEnte::listaPassaggio($periodo),
            'iniziato' => $periodo->dal->lte(today()),
            'presidente' => $this->ePresidente(),
        ]);
    }

    public function spunta(Request $request, AssociazionePeriodo $periodo): RedirectResponse
    {
        $this->richiediPresidente($request);
        $dati = $request->validate([
            'codice' => ['required', Rule::in(array_keys(AssociazionePeriodo::SPUNTE_MANUALI))],
            'fatta' => ['required', 'boolean'],
        ]);

        $spunte = $periodo->spunte ?? [];
        if ($request->boolean('fatta')) {
            $spunte[$dati['codice']] = today()->toDateString();
        } else {
            unset($spunte[$dati['codice']]);
        }
        $periodo->spunte = $spunte ?: null;
        $periodo->save();
        $this->audit("Passaggio al periodo dal {$periodo->dal->format('d/m/Y')}: voce {$dati['codice']} " . ($request->boolean('fatta') ? 'spuntata' : 'tolta') . '.', $periodo);

        return redirect()->route('impostazioni.periodi.passaggio', $periodo);
    }

    // ── Iscrizioni online ────────────────────────────────────────────────

    public function iscrizioni(Request $request): RedirectResponse
    {
        $this->richiediPresidente($request);
        $aperte = $request->boolean('aperte');
        Impostazione::set('iscrizioni_aperte', $aperte, 'boolean',
            "Se spenta, il modulo pubblico /iscrizione mostra «Iscrizioni online non ancora aperte»; chi è collegato all'area riservata può provarlo e le domande nascono di prova (profilo-ente.md).");
        AuditLogService::scrivi([
            'evento' => 'IMPOSTAZIONE_MODIFICATA',
            'entita_tipo' => 'Impostazione',
            'dettaglio' => 'Iscrizioni online ' . ($aperte ? 'aperte' : 'chiuse') . '.',
        ]);

        return back()->with('success', $aperte ? 'Iscrizioni online aperte al pubblico.' : 'Iscrizioni online chiuse al pubblico.');
    }

    // ── Utilità ──────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function validaPeriodo(Request $request, ?AssociazionePeriodo $periodo): array
    {
        $dati = $request->validate([
            // Un periodo che comincia oggi o prima riscriverebbe i documenti già
            // emessi: si accetta solo come primo periodo dell'ente.
            'dal' => array_merge(['required', 'date', Rule::unique('gl_associazione_periodi', 'dal')->ignore($periodo?->id)],
                ProfiloEnte::periodi()->reject(fn (AssociazionePeriodo $p) => $p->is($periodo))->isEmpty() ? [] : ['after:today']),
            'denominazione' => ['required', 'string', 'max:255'],
            'sigla' => ['nullable', 'string', 'max:20'],
            'forma_giuridica' => ['required', Rule::in(array_keys(AssociazionePeriodo::FORME))],
            'forma_descrizione' => ['nullable', 'string', 'max:100'],
            'sede_legale' => ['required', 'string', 'max:255'],
            'rasd_numero' => ['nullable', 'string', 'max:50'],
            'rasd_data_iscrizione' => ['nullable', 'date'],
            'regime_fiscale' => ['required', Rule::in(array_keys(AssociazionePeriodo::REGIMI))],
            'ente_affiliazione' => ['nullable', 'string', 'max:100'],
            'statuto_versione' => ['required', 'regex:/^\d+\.\d+$/'],
            'informativa_versione' => ['required', 'regex:/^\d+\.\d+$/'],
            'atto' => ['nullable', 'string', 'max:255'],
        ], [
            'dal.unique' => 'Esiste già un periodo che comincia in questa data.',
            'dal.after' => 'Il nuovo periodo deve cominciare da domani in poi: i documenti già emessi conservano i dati del loro periodo.',
            'statuto_versione.regex' => 'La versione si scrive come 1.0, 2.0…',
            'informativa_versione.regex' => 'La versione si scrive come 1.0, 2.0…',
        ], [
            'dal' => 'data di inizio', 'forma_giuridica' => 'forma giuridica', 'sede_legale' => 'sede legale',
            'regime_fiscale' => 'regime fiscale', 'statuto_versione' => 'versione dello statuto',
            'informativa_versione' => "versione dell'informativa", 'rasd_data_iscrizione' => 'data di iscrizione al RASD',
        ]);

        // RASD solo per le forme sportive dilettantistiche.
        if (! in_array($dati['forma_giuridica'] ?? $periodo?->forma_giuridica, ['asd', 'ssd'], true)) {
            $dati['rasd_numero'] = null;
            $dati['rasd_data_iscrizione'] = null;
        }

        return $dati;
    }

    /** Versioni pubblicate di statuto e informativa (per i menu a tendina). */
    private function versioni(): array
    {
        $elenco = fn (string $tipo) => collect(glob(resource_path("content/{$tipo}/*.md")))
            ->map(fn ($f) => basename($f, '.md'))->filter(fn ($v) => preg_match('/^\d+\.\d+$/', $v))->sort()->values()->all();

        return ['statuto' => $elenco('statuto'), 'informativa' => $elenco('informativa')];
    }

    private function audit(string $dettaglio, ?AssociazionePeriodo $periodo = null): void
    {
        AuditLogService::scrivi([
            'evento' => 'PROFILO_ENTE_MODIFICATO',
            'entita_tipo' => $periodo ? 'AssociazionePeriodo' : 'Associazione',
            'entita_id' => $periodo?->id ?? 1,
            'dettaglio' => $dettaglio,
        ]);
    }

    private function ePresidente(): bool
    {
        return (bool) auth()->user()?->haRuolo('presidente') && auth()->user()->puoModificare('impostazioni');
    }

    private function richiediPresidente(Request $request): void
    {
        abort_unless($this->ePresidente(), 403, 'Solo il Presidente modifica il profilo dell\'ente.');
    }
}
