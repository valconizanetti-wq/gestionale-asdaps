<?php

namespace App\Http\Controllers;

use App\Models\Ruolo;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ConsiglioDirettivo;
use App\Services\Utenti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/**
 * Utenti del gestionale (v0.40.0, analisi-ruoli-utenti.md §3.4, tavole
 * UT-1…UT-3; v0.42.0, tavole UT-8 e UT-9: gli utenti del CD nascono dal
 * Consiglio Direttivo, qui solo esterni, email, stato e Amministratore).
 * Le regole sono nel servizio Utenti.
 */
class UtentiController extends Controller
{
    /** Cosa può fare ciascun ruolo, in breve (tavole UT-2, UT-8). */
    public const DESCRIZIONI = [
        'presidente' => 'Tutto, compresi Utenti, Audit Log e riapertura degli anni chiusi.',
        'vicepresidente' => 'Come il Consigliere, più le sezioni del Presidente in lettura.',
        'segretario' => 'Soci, domande, tessere, scadenzario e utenti; Prima Nota in lettura.',
        'tesoriere' => 'Prima Nota, rinnovi quota, bilancio, tessere, scadenzario.',
        'consigliere' => 'Membro del CD senza carica: carica gli scontrini, Prima Nota in lettura.',
        'revisore' => 'Organo di controllo, anche non socio, fuori dal CD: contabilità e Audit Log in lettura. Uno solo.',
        'amministratore' => 'Legge tutto il gestionale per l\'assistenza; scrive solo gli Utenti e può mandare il link della password anche al Presidente. Non con Presidente o Revisore. Uno solo.',
    ];

    public function __construct(private Utenti $utenti)
    {
    }

    public function index(Request $request)
    {
        return view('utenti.index', [
            'utenti' => User::with(['ruoli', 'socio'])->orderByDesc('attivo')->orderBy('cognome')->orderBy('nome')->get(),
            'puoModificare' => $request->user()->puoModificare('utenti'),
            'nelCd' => User::whereIn('socio_id', \App\Models\GovernanceCd::where('in_carica', true)->pluck('socio_id'))->pluck('id')->all(),
        ]);
    }

    /** Nuovo utente: solo esterni (Revisore o Amministratore); quelli del CD nascono dal CD. */
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
            . ($inviato ? ': gli è arrivato il link per scegliere la password.' : '. Per farlo entrare mandagli il link per la password (icona del lucchetto).'));
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
        $utente->refresh();

        return redirect()->route('utenti.index')->with('success', "Dati di {$utente->name} aggiornati. Ruoli: " . ($utente->etichettaRuoli() ?: 'nessuno') . '.');
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

    /** @return array{cognome?: string, nome?: string, email: string, revisore?: bool, amministratore: bool, attivo: bool} */
    private function valida(Request $request, ?User $utente): array
    {
        $delCd = $utente && Utenti::delCd($utente);
        $regole = [
            'email' => ['required', 'email', 'max:255', Rule::unique('gl_users', 'email')->ignore($utente?->id)],
        ];
        if (! $delCd) {
            $regole += [
                'cognome' => ['required', 'string', 'max:100'],
                'nome' => ['required', 'string', 'max:100'],
                'ruolo' => ['required', Rule::in(['revisore', 'amministratore'])],
            ];
        }
        $dati = $request->validate($regole, [
            'email.unique' => 'Questa email è già di un altro utente.',
            'ruolo.required' => 'Scegli il ruolo.',
        ]);

        return [
            'cognome' => $dati['cognome'] ?? null,
            'nome' => $dati['nome'] ?? null,
            'email' => $dati['email'],
            'revisore' => ($dati['ruolo'] ?? null) === 'revisore',
            'amministratore' => $delCd ? $request->boolean('amministratore') : ($dati['ruolo'] ?? null) === 'amministratore',
            'attivo' => $request->boolean('attivo'),
        ];
    }

    /** Dati per il modulo (tavole UT-8a e UT-8b). */
    private function datiForm(Request $request, ?User $utente): array
    {
        $chi = $request->user();
        $propri = $utente && $utente->is($chi);
        $codici = $utente?->exists ? $utente->codiciRuoli() : [];

        return [
            'delCd' => $utente?->exists && Utenti::delCd($utente),
            'ruoliCd' => array_values(array_intersect($codici, ConsiglioDirettivo::RUOLI_CD)),
            'etichette' => Ruolo::etichette(),
            'descrizioni' => self::DESCRIZIONI,
            'ruoloEsterno' => old('ruolo', in_array('revisore', $codici, true) ? 'revisore' : ($utente?->exists ? 'amministratore' : 'revisore')),
            'amministratore' => (bool) old('amministratore', in_array('amministratore', $codici, true)),
            // L'Amministratore non si unisce a Presidente e Revisore; i propri ruoli li cambia solo il Presidente
            'adminBloccato' => in_array('presidente', $codici, true) ? 'Non con il Presidente.'
                : ($propri && Utenti::conLimiti($chi) ? 'I tuoi ruoli li cambia solo il Presidente.' : null),
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
