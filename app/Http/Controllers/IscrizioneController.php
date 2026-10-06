<?php

namespace App\Http\Controllers;

use App\Http\Requests\IscrizioneDatiRequest;
use App\Http\Requests\IscrizioneInvioRequest;
use App\Mail\IscrizioneNuovaDomandaSegreteria;
use App\Mail\IscrizioneRicevuta;
use App\Models\Associazione;
use App\Models\Domanda;
use App\Models\Impostazione;
use App\Services\AuditLogService;
use App\Services\CodiceFiscale;
use App\Services\Documenti;
use App\Services\IscrizioneOnline;
use App\Services\ProfiloEnte;
use App\Services\RicevutaDomanda;
use App\Services\VotazioneCd;
use App\Support\InvioEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Domanda di iscrizione online: modulo pubblico, senza login
 * (domande-iscrizione.md §3, statuto v1.0 art. 6).
 *
 * Due passi, con i dati del passo 1 tenuti in sessione fino all'invio:
 * nel database entra solo una domanda completa. Dalla v0.30.0 la domanda
 * vale con l'invio (la conferma è il pulsante "Invia la domanda" dopo le
 * dichiarazioni) e va subito ai consiglieri; l'email porta la ricevuta e
 * il link per annullarla. Annullamento (e conferma delle vecchie domande
 * "in_verifica") dal link dell'email passano da una pagina con pulsante
 * (GET mostra, POST agisce): i filtri antispam di molte caselle aprono da
 * soli i link delle email, e con un GET che agisce annullerebbero domande
 * al posto del richiedente.
 */
class IscrizioneController extends Controller
{
    private const SESSIONE = 'iscrizione.dati';

    /** Quando sono stati aperti statuto e informativa (pagine su telefono): ['statuto' => ISO, 'informativa' => ISO]. */
    private const LETTI = 'iscrizione.letti';

    public function __construct(private IscrizioneOnline $iscrizione, private VotazioneCd $votazione)
    {
    }

    // ── Passo 1: dati ────────────────────────────────────────────────────

    public function dati(Request $request): View
    {
        if ($this->chiusePerChiVisita()) {
            return view('iscrizione.chiuse');
        }
        $this->richiediTestiPubblicati();

        return view('iscrizione.dati', [
            'prova' => $this->iscrizioniChiuse(),
            'testi' => $this->testi(),
            'precedenti' => $request->session()->get(self::SESSIONE . '.input', []),
            'documenti' => IscrizioneDatiRequest::DOCUMENTI,
            'quote' => $this->quote(),
        ]);
    }

    public function salvaDati(IscrizioneDatiRequest $request): RedirectResponse
    {
        if ($this->chiusePerChiVisita()) {
            return redirect()->route('iscrizione.dati');
        }
        $this->richiediTestiPubblicati();
        $validati = $request->validated();

        // Solo chi diventa socio (adulto o minore) non deve avere domande
        // attive: il genitore può iscrivere più figli.
        if ($motivo = $this->iscrizione->motivoBlocco($validati['codice_fiscale'])) {
            return back()->withInput()->withErrors(['codice_fiscale' => $motivo]);
        }

        $request->session()->put(self::SESSIONE, [
            'input' => $validati,
            'dati' => $this->iscrizione->prepara($validati),
        ]);

        return redirect()->route('iscrizione.riepilogo');
    }

    // ── Passo 2: controlla e invia ───────────────────────────────────────

    public function riepilogo(Request $request): View|RedirectResponse
    {
        $sessione = $request->session()->get(self::SESSIONE);
        if (! $sessione || $this->chiusePerChiVisita()) {
            return redirect()->route('iscrizione.dati');
        }

        // Versioni in vigore oggi secondo il profilo dell'ente (v0.32.0).
        $versioneInformativa = ProfiloEnte::versione('informativa');
        $versioneStatuto = ProfiloEnte::versione('statuto');
        $d = $sessione['dati'];

        return view('iscrizione.riepilogo', [
            'prova' => $this->iscrizioniChiuse(),
            'testi' => $this->testi(),
            'd' => $d,
            // Assenso del minore dai 14 anni (art. 16 D.Lgs. 36/2021): stessa
            // regola della request, che lo rende obbligatorio all'invio.
            'assenso' => $d['minore'] && Domanda::assensoRichiesto($d['data_nascita']),
            'etaMinore' => $d['minore'] ? Domanda::anniCompiuti($d['data_nascita']) : null,
            // Testi per la finestra sulla pagina (desktop) e documenti già
            // aperti nella loro pagina (telefono, tornando alla domanda).
            'statutoHtml' => Documenti::html('statuto', $versioneStatuto),
            'informativaHtml' => Documenti::html('informativa', $versioneInformativa),
            'letti' => $request->session()->get(self::LETTI, []),
            'documenti' => IscrizioneDatiRequest::DOCUMENTI,
            'quote' => $this->quote(),
            'versioneInformativa' => $versioneInformativa,
            'versioneStatuto' => $versioneStatuto,
            'impronta' => $this->improntaTesti(),
        ]);
    }

    public function invia(IscrizioneInvioRequest $request): RedirectResponse
    {
        if ($this->chiusePerChiVisita()) {
            return redirect()->route('iscrizione.dati');
        }
        $sessione = $request->session()->get(self::SESSIONE);
        if (! $sessione) {
            return redirect()->route('iscrizione.dati')
                ->withErrors(['sessione' => 'La sessione è scaduta: ricompila i dati, per favore.']);
        }

        $this->richiediTestiPubblicati();
        $dati = $sessione['dati'];

        // Passaggio a un nuovo periodo tra il riepilogo e l'invio (per esempio
        // a mezzanotte del 31/12): la persona ha letto altri testi o un'altra
        // denominazione, quindi li rilegge prima di inviare (v0.32.0).
        if ($request->input('impronta_testi') !== $this->improntaTesti()) {
            return redirect()->route('iscrizione.riepilogo')->withErrors(['impronta_testi' =>
                "Da oggi sono in vigore nuovi testi dell'associazione: rileggi statuto e informativa e invia di nuovo la domanda."]);
        }

        // Di nuovo, perché tra il passo 1 e l'invio può essere passato tempo.
        if ($motivo = $this->iscrizione->motivoBlocco($dati['codice_fiscale'])) {
            return redirect()->route('iscrizione.dati')->withErrors(['codice_fiscale' => $motivo]);
        }

        // Ora di apertura dei testi, se sono stati aperti (non obbligatorio):
        // quella registrata dalla pagina del documento (telefono) o, per la
        // finestra sulla pagina, quella dell'invio. Altrimenti vuota.
        $letti = $request->session()->get(self::LETTI, []);
        $aperto = fn (string $doc, string $campo) => match (true) {
            isset($letti[$doc]) => \Illuminate\Support\Carbon::parse($letti[$doc]),
            $request->boolean($campo) => now(),
            default => null,
        };

        [$domanda, $token] = $this->iscrizione->crea($dati, [
            'foto_video' => $request->boolean('foto_video'),
            'assenso_minore' => $request->assensoRichiesto() && $request->boolean('assenso_minore'),
            'informativa_aperta_il' => $aperto('informativa', 'informativa_aperta'),
            'statuto_aperto_il' => $aperto('statuto', 'statuto_aperto'),
        ], $request);

        $request->session()->forget([self::SESSIONE, self::LETTI]);
        $this->audit($domanda, 'DOMANDA_ONLINE_INVIATA');
        $this->dopoLaPresentazione($domanda, $token);

        return redirect()->route('iscrizione.inviata')->with('domanda_inviata', [
            'saluto' => $domanda->genitore_nome ? explode(' ', $domanda->genitore_nome)[0] : $domanda->nome,
            'email' => self::maschera($domanda->emailDestinatario()),
            'presentata' => $domanda->created_at->toIso8601String(),
            'scadenza' => $domanda->scadenza_cd?->toIso8601String(),
        ]);
    }

    public function inviata(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('domanda_inviata')) {
            return redirect()->route('iscrizione.dati');
        }

        return view('iscrizione.inviata', ['d' => $request->session()->get('domanda_inviata')]);
    }

    /**
     * Anteprima dei dati ricavati da un codice fiscale, per l'aiuto sotto il
     * campo. Non legge il database: risponde solo con ciò che il codice
     * stesso contiene, quindi non rivela nulla di altre persone.
     */
    public function anteprimaCodiceFiscale(Request $request): \Illuminate\Http\JsonResponse
    {
        $esito = CodiceFiscale::analizza((string) $request->input('cf'));
        if (! $esito['valido']) {
            return response()->json(['valido' => false, 'errore' => $esito['errore']]);
        }

        // "Nato il 10/05/1990 a Terni (TR)": solo data e luogo di nascita.
        $nato = $esito['sesso'] === 'F' ? 'Nata' : 'Nato';
        $preposizione = $esito['estero'] ? 'in' : 'a';

        // Per l'avviso "risulta minorenne / maggiorenne" accanto alla scelta
        // Maggiorenne / Minorenne: lo stesso controllo lo rifà il server.
        $minorenne = $esito['data_nascita']->addYears(18)->greaterThan(now()->startOfDay());

        return response()->json([
            'valido' => true,
            'minorenne' => $minorenne,
            // Per il modulo del socio nell'area riservata, che compila data,
            // luogo e sesso dal codice (solo ciò che il codice contiene).
            'data_nascita' => $esito['data_nascita']->format('Y-m-d'),
            'luogo' => CodiceFiscale::luogoLeggibile($esito),
            'sesso' => $esito['sesso'],
            'descrizione' => sprintf('%s il %s %s %s', $nato,
                $esito['data_nascita']->format('d/m/Y'),
                $preposizione, CodiceFiscale::luogoLeggibile($esito)),
        ]);
    }

    // ── Conferma e annullamento dal link dell'email ──────────────────────

    public function mostraConferma(string $token): View
    {
        return $this->paginaToken($token, 'iscrizione.conferma');
    }

    public function conferma(string $token): View
    {
        $domanda = $this->iscrizione->perToken($token);
        if (! $domanda || $domanda->stato !== 'in_verifica' || $this->iscrizione->linkScaduto($domanda)) {
            return $this->paginaToken($token, 'iscrizione.conferma');
        }

        if (! $this->iscrizione->conferma($domanda)) {
            // Confermata nel frattempo da un'altra richiesta (doppio clic).
            return $this->paginaToken($token, 'iscrizione.conferma');
        }
        $this->audit($domanda, 'DOMANDA_ONLINE_CONFERMATA');
        $this->dopoLaPresentazione($domanda, $token, true);

        return view('iscrizione.confermata', ['domanda' => $domanda->fresh()]);
    }

    public function mostraAnnulla(string $token): View
    {
        $domanda = $this->iscrizione->perToken($token);
        if ($domanda && $domanda->stato !== 'in_verifica') {
            // Domanda già presentata: si può ritirare finché non è iscritta.
            return $domanda->annullabile()
                ? view('iscrizione.annulla', ['token' => $token, 'domanda' => $domanda])
                : view('iscrizione.gia-decisa', ['domanda' => $domanda]);
        }

        return $this->paginaToken($token, 'iscrizione.annulla');
    }

    public function annulla(string $token): View
    {
        $domanda = $this->iscrizione->perToken($token);
        if (! $domanda || ! $domanda->annullabile()) {
            // Già decisa, iscritta o cancellata: pagina dello stato attuale.
            return $this->mostraAnnulla($token);
        }

        $id = $domanda->id;
        $isTest = $domanda->is_test;
        $this->iscrizione->annulla($domanda);

        if (! $isTest) {
            AuditLogService::scrivi([
                'evento' => 'DOMANDA_ONLINE_ANNULLATA',
                'entita_tipo' => 'Domanda',
                'entita_id' => $id,
                'utente_nome' => 'Richiedente (link email)',
                'dettaglio' => "Domanda {$id} ritirata dal richiedente; dati cancellati.",
            ]);
        }

        return view('iscrizione.annullata');
    }

    // ── Documenti pubblici ───────────────────────────────────────────────

    public function informativa(Request $request): View
    {
        return $this->documento($request, 'informativa', 'Informativa privacy');
    }

    public function statuto(Request $request): View
    {
        return $this->documento($request, 'statuto', 'Statuto');
    }

    /**
     * Pagina del documento. Se c'è una domanda in corso (dati del passo 1 in
     * sessione) registra l'apertura, richiesta per l'invio, e mostra il link
     * per tornare alla domanda (su telefono i testi si aprono qui, non nella
     * finestra: collaudo A8, 29/09/2026).
     */
    private function documento(Request $request, string $tipo, string $titolo): View
    {
        $versione = ProfiloEnte::versione($tipo);
        $html = Documenti::html($tipo, $versione);
        abort_if($html === null, 404, "{$titolo}: testo non ancora pubblicato.");

        $inCorso = $request->session()->has(self::SESSIONE);
        if ($inCorso && ! $request->session()->has(self::LETTI . '.' . $tipo)) {
            $request->session()->put(self::LETTI . '.' . $tipo, now()->toIso8601String());
        }

        return view('iscrizione.documento', [
            'titolo' => $titolo,
            'versione' => $versione,
            'html' => $html,
            'ritorno' => $inCorso ? route('iscrizione.riepilogo') : null,
        ]);
    }

    // ── Utilità ──────────────────────────────────────────────────────────

    /**
     * Dopo la presentazione (o la conferma di una vecchia domanda): ricevuta
     * PDF, email al richiedente con il link per annullare, avviso alla
     * segreteria, link di voto ai consiglieri. Se la ricevuta o un'email
     * falliscono la domanda resta valida: l'errore finisce nel log e nel
     * registro degli invii.
     */
    private function dopoLaPresentazione(Domanda $domanda, string $token, bool $conConferma = false): void
    {
        $pdf = null;
        try {
            $pdf = app(RicevutaDomanda::class)->genera($domanda, $conConferma);
        } catch (\Throwable $e) {
            Log::error("Ricevuta domanda {$domanda->id} non generata: " . $e->getMessage());
        }

        InvioEmail::invia(new IscrizioneRicevuta($domanda, $token, $pdf),
            $domanda->emailDestinatario(), $domanda->nomeDestinatario(), 'domanda_ricevuta', $domanda);

        $segreteria = Associazione::istanza()->email;
        if ($segreteria) {
            InvioEmail::invia(new IscrizioneNuovaDomandaSegreteria($domanda), $segreteria, null, 'nuova_domanda_segreteria', $domanda);
        }

        $this->votazione->apri($domanda);
    }

    /**
     * Pagina con il pulsante di conferma o annullamento, oppure la pagina
     * di link non valido o scaduto.
     */
    private function paginaToken(string $token, string $vista): View
    {
        $domanda = $this->iscrizione->perToken($token);
        if (! $domanda) {
            return view('iscrizione.link-non-valido');
        }
        if ($domanda->stato === 'scaduta') {
            return view('iscrizione.link-scaduto');
        }
        if ($domanda->stato !== 'in_verifica') {
            // Già confermata: si mostra a che punto è, senza pulsanti.
            return view('iscrizione.gia-confermata', ['domanda' => $domanda]);
        }
        if ($this->iscrizione->linkScaduto($domanda)) {
            // Oltre le 48 ore la domanda non vale più (non si conferma né
            // serve annullarla): la cancella il comando giornaliero.
            $this->iscrizione->segnaScaduta($domanda);

            return view('iscrizione.link-scaduto');
        }

        return view($vista, ['token' => $token, 'domanda' => $domanda]);
    }

    /**
     * Le domande si accettano solo se informativa e statuto della versione
     * in vigore sono pubblicati: chi firma deve poterli leggere, e la loro
     * impronta va salvata con la domanda.
     */
    private function richiediTestiPubblicati(): void
    {
        $informativa = ProfiloEnte::versione('informativa');
        $statuto = ProfiloEnte::versione('statuto');

        abort_unless(
            Documenti::esiste('informativa', $informativa) && Documenti::esiste('statuto', $statuto),
            503,
            "Le iscrizioni online non sono ancora aperte: mancano l'informativa o lo statuto."
        );
    }

    /**
     * Iscrizioni online chiuse (impostazione "iscrizioni_aperte" spenta,
     * profilo-ente.md, v0.32.0): fino all'apertura il modulo non è pubblico.
     */
    /** Periodo e versioni dei testi in vigore ora: il riepilogo li manda con l'invio. */
    private function improntaTesti(): string
    {
        $periodo = ProfiloEnte::al();

        return implode('|', [$periodo->id ?? 0, $periodo->statuto_versione, $periodo->informativa_versione]);
    }

    private function iscrizioniChiuse(): bool
    {
        return ! (bool) Impostazione::get('iscrizioni_aperte', false);
    }

    /** Chiuse e visitatore senza accesso all'area riservata: vede la pagina "non ancora aperte". */
    private function chiusePerChiVisita(): bool
    {
        return $this->iscrizioniChiuse() && ! auth()->check();
    }

    /** Testi della pagina (resources/content/iscrizione/testi.php). */
    private function testi(): array
    {
        return require resource_path('content/iscrizione/testi.php');
    }

    /** Quote per categoria, fissate dal CD (statuto art. 5.4). */
    private function quote(): array
    {
        return [
            'ordinario' => (int) Impostazione::get('quota_ordinario', 70),
            'minorenne' => (int) Impostazione::get('quota_giovane', 30),
        ];
    }

    /**
     * Evento di business nell'audit, senza dati personali (il dettaglio
     * entra nell'hash). Le domande di prova non vi finiscono mai.
     */
    private function audit(Domanda $domanda, string $evento): void
    {
        if ($domanda->is_test) {
            return;
        }

        AuditLogService::scrivi([
            'evento' => $evento,
            'entita_tipo' => 'Domanda',
            'entita_id' => $domanda->id,
            'utente_nome' => 'Richiedente (modulo pubblico)',
            'dettaglio' => "Domanda {$domanda->id}: {$domanda->stato}.",
        ]);
    }

    /** "laura.ferri@esempio.it" → "l•••••@esempio.it" */
    public static function maschera(string $email): string
    {
        [$locale, $dominio] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($locale, 0, 1) . '•••••@' . $dominio;
    }
}
