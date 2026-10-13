<?php

namespace App\Http\Controllers;

use App\Models\Ruolo;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Utenti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/**
 * Utenti del gestionale (v0.40.0, analisi-ruoli-utenti.md §3.4, tavole
 * UT-1…UT-3). Le regole sono nel servizio Utenti.
 */
class UtentiController extends Controller
{
    /** Cosa può fare ciascun ruolo, in breve (tavola UT-2). */
    public const DESCRIZIONI = [
        'presidente' => 'Tutto, compresi Utenti, Audit Log e riapertura degli anni chiusi.',
        'segretario' => 'Soci, domande, tessere, scadenzario e utenti; Prima Nota in lettura.',
        'tesoriere' => 'Prima Nota, rinnovi quota, bilancio, tessere, scadenzario.',
        'consigliere' => 'Membro del CD senza carica: carica gli scontrini, Prima Nota in lettura.',
        'revisore' => 'Organo di controllo, in sola lettura (contabilità, Audit Log).',
    ];

    public function __construct(private Utenti $utenti)
    {
    }

    public function index(Request $request)
    {
        return view('utenti.index', [
            'utenti' => User::with('ruoli')->orderByDesc('attivo')->orderBy('name')->get(),
            'puoModificare' => $request->user()->puoModificare('utenti'),
        ]);
    }

    public function create(Request $request)
    {
        $this->richiediModifica($request);

        return view('utenti.form', ['utente' => new User(['attivo' => true])] + $this->datiForm($request, null));
    }

    public function store(Request $request)
    {
        $this->richiediModifica($request);
        $dati = $this->valida($request, null);

        $utente = $this->utenti->salva(null, $dati, $request->user());

        $inviato = $request->boolean('invia_link') && $utente->attivo && $this->utenti->inviaLink($utente) === Password::RESET_LINK_SENT;
        if ($inviato) {
            $this->registraLink($utente);
        }

        return redirect()->route('utenti.index')->with('success', "Utente {$utente->name} creato"
            . ($inviato ? ": gli è arrivato il link per scegliere la password." : '. Per farlo entrare mandagli il link per la password (icona del lucchetto).'));
    }

    public function edit(Request $request, User $utente)
    {
        $this->richiediModifica($request);
        if ($motivo = Utenti::motivoBlocco($request->user(), $utente)) {
            return redirect()->route('utenti.index')->with('error', $motivo);
        }

        return view('utenti.form', ['utente' => $utente] + $this->datiForm($request, $utente));
    }

    public function update(Request $request, User $utente)
    {
        $this->richiediModifica($request);
        $dati = $this->valida($request, $utente);

        $this->utenti->salva($utente, $dati, $request->user());

        return redirect()->route('utenti.index')->with('success', "Dati di {$utente->name} aggiornati. Ruoli: {$utente->etichettaRuoli()}.");
    }

    public function linkPassword(Request $request, User $utente)
    {
        $this->richiediModifica($request);
        if ($motivo = Utenti::motivoBlocco($request->user(), $utente)) {
            return back()->with('error', $motivo);
        }
        if (! $utente->attivo) {
            return back()->with('error', "{$utente->name} è disattivato: prima riattivalo.");
        }

        $esito = $this->utenti->inviaLink($utente);
        if ($esito === Password::RESET_THROTTLED) {
            return back()->with('error', 'Un link è appena partito: aspetta un minuto prima di mandarne un altro.');
        }
        if ($esito !== Password::RESET_LINK_SENT) {
            return back()->with('error', 'Link non inviato: controlla l\'email dell\'utente.');
        }
        $this->registraLink($utente);

        return back()->with('success', "Link per la password inviato a {$utente->email}: vale 24 ore.");
    }

    /** @return array{name: string, email: string, ruoli: list<string>, attivo: bool} */
    private function valida(Request $request, ?User $utente): array
    {
        $dati = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', Rule::unique('gl_users', 'email')->ignore($utente?->id)],
            'ruoli' => ['required', 'array', 'min:1'],
            'ruoli.*' => ['string', Rule::in(array_keys(Ruolo::etichette()))],
        ], ['ruoli.required' => 'Scegli almeno un ruolo.', 'email.unique' => 'Questa email è già di un altro utente.'], ['name' => 'nome e cognome']);

        return $dati + ['attivo' => $request->boolean('attivo')];
    }

    /** Ruoli e caselle bloccate per chi compila (tavola UT-2). */
    private function datiForm(Request $request, ?User $utente): array
    {
        $chi = $request->user();
        $limiti = Utenti::conLimiti($chi);
        $propri = $utente && $utente->is($chi);

        return [
            'ruoli' => Ruolo::etichette(),
            'descrizioni' => self::DESCRIZIONI,
            'scelti' => old('ruoli', $utente?->exists ? $utente->codiciRuoli() : []),
            // Ruoli che chi compila non può cambiare: restano come sono
            'bloccati' => $propri && $limiti ? array_keys(Ruolo::etichette()) : ($limiti ? ['presidente'] : []),
            'motivoBloccati' => $propri && $limiti ? 'I tuoi ruoli li cambia solo il Presidente.' : ($limiti ? 'Il ruolo di Presidente lo dà o lo toglie solo il Presidente.' : null),
            'propri' => $propri,
        ];
    }

    private function registraLink(User $utente): void
    {
        AuditLogService::scrivi([
            'evento' => 'UTENTE_LINK_PASSWORD',
            'operazione' => 'updated',
            'entita_tipo' => 'User',
            'entita_id' => $utente->id,
            'livello_rischio' => 'LOW',
            'dettaglio' => "Link per la password inviato all'utente n. {$utente->id}.",
        ]);
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('utenti'), 403, 'Il tuo ruolo può solo consultare gli utenti.');
    }
}
