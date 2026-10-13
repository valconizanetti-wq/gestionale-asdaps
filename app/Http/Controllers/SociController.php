<?php

namespace App\Http\Controllers;

use App\Http\Requests\SocioRequest;
use App\Models\Impostazione;
use App\Models\Socio;
use App\Services\AuditLogService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Modulo Soci (vedi soci.md). Rotte protette da 'permesso:soci'.
 * Le azioni di modifica verificano anche puoModificare('soci').
 */
class SociController extends Controller
{
    /** Motivi di cessazione ammessi. Da allineare allo statuto quando sarà redatto. */
    public const MOTIVI_CESSAZIONE = [
        'Dimissioni volontarie',
        'Mancato rinnovo della quota',
        'Esclusione deliberata dal Consiglio Direttivo',
        'Decesso',
    ];

    public const TIPI = [
        'ordinario' => 'Ordinario',
        'giovane'   => 'Giovane',
        'minorenne' => 'Minorenne',
    ];

    // ----------------------------------------------------------------
    // Elenco
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $filtri = $this->filtri($request);

        $certificatoRichiesto = (bool) Impostazione::get('certificato_medico_richiesto');

        $soci = $this->query($filtri)
            ->with('documenti:id,socio_id,categoria')
            ->when($certificatoRichiesto, fn ($q) => $q->withExists([
                'certificatiMedici as certificato_valido' => fn ($c) => Socio::queryCertificatoValido($c),
            ]))
            ->orderBy('cognome')->orderBy('nome')
            ->paginate(25)
            ->withQueryString();

        $oggi = now()->toDateString();
        $attivi = Socio::where('stato', 'attivo');

        $kpi = [
            'attivi'     => (clone $attivi)->count(),
            'versata'    => (clone $attivi)->whereDate('quota_scadenza', '>=', $oggi)->count(),
            'minorenni'  => (clone $attivi)->minorenniPerEta()->count(),
        ];
        $kpi['da_versare'] = $kpi['attivi'] - $kpi['versata'];

        // Tessera interna ancora da decidere (soci.md §3): la colonna
        // compare solo se almeno un socio ne ha una.
        $mostraTessera = Socio::whereNotNull('numero_tessera')->exists();

        // Invio in blocco del link privacy (privacy-link.md §3): solo per chi modifica.
        $linkPrivacy = $request->user()->puoModificare('soci') ? app(\App\Services\LinkPrivacy::class)->riepilogo() : null;

        // Tessera CSEN di ogni socio della pagina (badge come nella tavola TS-4)
        $tessere = \App\Services\Tessere::perSoci($soci->pluck('id'));

        return view('soci.index', compact('soci', 'filtri', 'kpi', 'mostraTessera', 'certificatoRichiesto', 'linkPrivacy', 'tessere'));
    }

    public function export(Request $request): StreamedResponse
    {
        $filtri = $this->filtriEsportazione($request);
        $soci = $this->query($filtri)->orderBy('cognome')->orderBy('nome')->get();
        $oggi = now();
        $this->registraEsportazione('CSV', $soci->count(), $filtri);

        return response()->streamDownload(function () use ($soci, $oggi) {
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF"); // BOM: Excel riconosce l'UTF-8

            fputcsv($fh, [
                'N. tessera', 'Tipo', 'Stato', 'Cognome', 'Nome', 'Data di nascita', 'Luogo di nascita',
                'Codice fiscale', 'Sesso', 'Email', 'Telefono', 'Indirizzo', 'CAP', 'Città',
                'Provincia', 'Iscritto dal', 'Quota valida fino al', 'Minorenne', 'Tutore', 'Telefono tutore',
            ], ';');

            foreach ($soci as $s) {
                fputcsv($fh, [
                    $s->numero_tessera,
                    self::TIPI[$s->tipo] ?? $s->tipo,
                    ucfirst($s->stato),
                    $s->cognome,
                    $s->nome,
                    $s->data_nascita?->format('d/m/Y'),
                    $s->luogo_nascita,
                    $s->codice_fiscale,
                    $s->sesso,
                    $s->email,
                    $s->telefono,
                    $s->indirizzo,
                    $s->cap,
                    $s->citta,
                    $s->provincia,
                    $s->data_prima_iscrizione?->format('d/m/Y'),
                    $s->quota_scadenza?->format('d/m/Y'),
                    $s->isMinorenne() ? 'Sì' : 'No',
                    $s->tutore_nome,
                    $s->tutore_telefono,
                ], ';');
            }
            fclose($fh);
        }, 'soci-' . $oggi->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Libro soci in PDF (A4 verticale, layout ripreso dal Gestionale APS),
     * con gli stessi filtri dell'elenco. Con stato "tutti" è il registro
     * completo, cessati compresi.
     */
    public function pdf(Request $request)
    {
        $filtri = $this->filtriEsportazione($request);
        $certificatoRichiesto = (bool) Impostazione::get('certificato_medico_richiesto');
        $soci = $this->query($filtri)
            ->with('documenti:id,socio_id,categoria')
            ->when($certificatoRichiesto, fn ($q) => $q->withExists([
                'certificatiMedici as certificato_valido' => fn ($c) => Socio::queryCertificatoValido($c),
            ]))
            ->orderBy('cognome')->orderBy('nome')
            ->get();

        $riepilogo = [
            'quota_valida' => $soci->filter(fn ($s) => $s->quota_scadenza?->gte(today()))->count(),
            'minorenni' => $soci->filter(fn ($s) => $s->isMinorenne())->count(),
            'documenti_completi' => $soci->filter(fn ($s) => $s->documentiMancanti($certificatoRichiesto) === 0)->count(),
        ];
        $riepilogo['da_versare'] = $soci->count() - $riepilogo['quota_valida'];

        $etichettaStato = [
            'attivo' => 'soci attivi', 'sospeso' => 'soci sospesi',
            'cancellato' => 'soci cessati', 'tutti' => 'tutti i soci, compresi i cessati',
        ][$filtri['stato']] ?? 'soci';

        $pdf = Pdf::loadView('pdf.libro-soci', [
            'soci' => $soci,
            'tipi' => self::TIPI,
            'etichettaStato' => $etichettaStato,
            'filtriAttivi' => $filtri['q'] !== '' || $filtri['tipo'] !== '' || $filtri['quota'] !== '',
            'generatoIl' => now(),
            'mostraTessera' => $soci->whereNotNull('numero_tessera')->isNotEmpty(),
            'riepilogo' => $riepilogo,
            'certificatoRichiesto' => $certificatoRichiesto,
        ])
            ->setPaper('a4', 'portrait');

        // Si registra l'esportazione solo dopo che il PDF è stato generato:
        // se la generazione fallisce, nel registro non resta un'esportazione
        // mai avvenuta.
        $contenuto = $pdf->output();
        $this->registraEsportazione('PDF', $soci->count(), $filtri);

        return response($contenuto, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="libro-soci-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    // ----------------------------------------------------------------
    // Scheda
    // ----------------------------------------------------------------

    public function show(Socio $socio)
    {
        $socio->load([
            'tesseramenti' => fn ($q) => $q->with(['tipo', 'pacchetto'])->orderByDesc('data_rilascio')->orderByDesc('id'),
            'caricheCd' => fn ($q) => $q->where('in_carica', true),
            'documenti' => fn ($q) => $q->orderByDesc('created_at'),
            // Domanda online da cui è nato il socio: ricevuta e scheda
            // raggiungibili dalla scheda del socio (v0.30.3).
            'domandaOrigine',
            'privacyLinkAperto',
            'rinnovi.annullataDa:id,name',
        ]);

        return view('soci.show', [
            'socio' => $socio,
            'motivi' => self::MOTIVI_CESSAZIONE,
            'tipi' => self::TIPI,
            'requisiti' => $socio->requisitiIscrizione((bool) Impostazione::get('certificato_medico_richiesto')),
            'categorie' => config('soci.categorie_documenti'),
            // Tessera CSEN (tavola TS-4): situazione di oggi
            'tessera' => \App\Services\Tessere::situazione($socio->tesseramenti),
        ]);
    }

    /**
     * Invio in blocco del link privacy ai soci attivi che non hanno letto
     * l'informativa in vigore (privacy-link.md §3, tavola Privacy-5).
     * Senza la spunta, chi ha già un link aperto non riceve nulla.
     */
    public function inviaLinkPrivacy(Request $request, \App\Services\LinkPrivacy $servizio)
    {
        $this->richiediModifica($request);

        $esito = $servizio->inviaATutti($request->boolean('anche_in_attesa'), $request->user());

        if ($esito['inviati'] === 0 && $esito['falliti']->isEmpty()) {
            return redirect()->route('soci.index')->with('success', 'Nessun link da inviare.');
        }

        $messaggio = $esito['inviati'] === 1 ? 'Inviato 1 link privacy.' : "Inviati {$esito['inviati']} link privacy.";
        if ($esito['falliti']->isNotEmpty()) {
            return redirect()->route('soci.index')->with('error', $messaggio . ' Non partiti (' . $esito['falliti']->count() . '): ' . $esito['falliti']->implode(', ') . '.');
        }

        return redirect()->route('soci.index')->with('success', $messaggio);
    }

    // ----------------------------------------------------------------
    // Inserimento e modifica
    // ----------------------------------------------------------------

    public function create(Request $request)
    {
        $this->richiediModifica($request);

        return view('soci.create', [
            'socio' => new Socio(['tipo' => 'ordinario', 'nazionalita' => 'Italiana']),
            'tipi' => self::TIPI,
        ]);
    }

    public function store(SocioRequest $request)
    {
        $dati = $this->prepara($request);

        if ($risposta = $this->controllaCodiceFiscale($dati['codice_fiscale'])) {
            return $risposta;
        }

        $socio = Socio::create($dati + ['inserito_da' => $request->user()->id]);

        return redirect()->route('soci.show', $socio)
            ->with('success', "Socio {$socio->cognome} {$socio->nome} inserito.");
    }

    public function edit(Request $request, Socio $socio)
    {
        $this->richiediModifica($request);

        return view('soci.edit', ['socio' => $socio, 'tipi' => self::TIPI]);
    }

    public function update(SocioRequest $request, Socio $socio)
    {
        $dati = $this->prepara($request);

        if ($risposta = $this->controllaCodiceFiscale($dati['codice_fiscale'], $socio->id)) {
            return $risposta;
        }

        $socio->update($dati);

        return redirect()->route('soci.show', $socio)->with('success', 'Dati del socio aggiornati.');
    }

    // ----------------------------------------------------------------
    // Uscita del socio (soci.md §3)
    // ----------------------------------------------------------------

    /** Uscita normale: stato cancellato con data e motivo. Il socio resta nello storico. */
    public function cessazione(Request $request, Socio $socio)
    {
        $this->richiediModifica($request);

        $dati = $request->validate([
            'motivo_cancellazione' => ['required', 'in:' . implode(',', self::MOTIVI_CESSAZIONE)],
            'data_cancellazione'   => ['required', 'date', 'before_or_equal:today'],
        ], [], ['motivo_cancellazione' => 'motivo', 'data_cancellazione' => 'data di cessazione']);

        // Come nel Gestionale APS: non si cessa chi ha una carica attiva nel CD.
        $carica = $socio->caricheCd()->where('in_carica', true)->first();
        if ($carica) {
            return back()->with('error',
                "Il socio ricopre la carica di {$carica->carica} nel Consiglio Direttivo: "
                . 'va conclusa prima la carica, poi si può registrare la cessazione.');
        }

        $socio->update($dati + ['stato' => 'cancellato']);

        return redirect()->route('soci.show', $socio)->with('success', 'Cessazione registrata.');
    }

    /** Annulla una cessazione registrata per errore, se non pseudonimizzato. */
    public function riattiva(Request $request, Socio $socio)
    {
        $this->richiediModifica($request);

        if ($socio->pseudonimizzato) {
            return back()->with('error', 'Un socio pseudonimizzato non può essere riattivato.');
        }

        $socio->update(['stato' => 'attivo', 'data_cancellazione' => null, 'motivo_cancellazione' => null]);

        return redirect()->route('soci.show', $socio)->with('success', 'Socio riattivato.');
    }

    /**
     * Eliminazione (soft delete): solo per correggere un inserimento
     * sbagliato, solo Presidente, con motivazione (collaudo K7). Non per un
     * socio con quote valide, movimenti o tessere: per chi esce si registra
     * la cessazione; una quota sbagliata si annulla prima.
     */
    public function destroy(Request $request, Socio $socio)
    {
        $this->richiediPresidente($request);
        $dati = $request->validateWithBag('elimina', [
            'motivo' => ['required', 'string', 'min:15', 'max:1000'],
        ], ['motivo.required' => 'Scrivi perché elimini la scheda.', 'motivo.min' => 'La motivazione deve avere almeno 15 caratteri.']);

        if ($blocchi = $this->bloccaEliminazione($socio)) {
            return back()->with('error', 'La scheda non si elimina: ' . implode('; ', $blocchi)
                . '. Per un socio che esce usa «Registra cessazione»; una quota registrata per errore si annulla prima dalla scheda.');
        }

        // Una riga di audit, nella stessa transazione dell'eliminazione. Nel
        // dettaglio (che entra nell'hash) niente dati personali: nomi e
        // motivazione, che potrebbe contenerne, stanno nei valori.
        DB::transaction(function () use ($socio, $dati) {
            Socio::withoutEvents(fn () => $socio->delete());

            return AuditLogService::scrivi([
                'evento' => 'SOCIO_DELETED',
                'operazione' => 'deleted',
                'entita_tipo' => 'Socio',
                'entita_id' => $socio->id,
                'livello_rischio' => 'HIGH',
                'dettaglio' => "Scheda del socio n. {$socio->id} eliminata (si può ripristinare), con motivazione.",
                'old_values' => ['cognome' => $socio->cognome, 'nome' => $socio->nome, 'codice_fiscale' => $socio->codice_fiscale],
                'new_values' => ['motivazione' => $dati['motivo']],
            ]);
        });

        return redirect()->route('soci.index')
            ->with('success', "Scheda di {$socio->cognome} {$socio->nome} eliminata. Si ripristina da Soci › Filtri › Stato «Schede eliminate».");
    }

    public function ripristina(Request $request, int $id)
    {
        $this->richiediPresidente($request);

        $socio = Socio::onlyTrashed()->findOrFail($id);
        $socio->restore();

        return redirect()->route('soci.show', $socio)->with('success', 'Scheda del socio ripristinata.');
    }

    /** Motivi per non eliminare la scheda (vuoto: si può). */
    private function bloccaEliminazione(Socio $socio): array
    {
        $blocchi = [];
        if ($socio->rinnovi()->valido()->exists()) {
            $ricevute = $socio->rinnovi()->valido()->pluck('numero_ricevuta')->filter();
            $blocchi[] = 'ha quote registrate' . ($ricevute->isNotEmpty() ? ' (ricevuta ' . $ricevute->implode(', ') . ')' : '');
        }
        if ($socio->movimentiPrimaNota()->exists()) {
            $blocchi[] = 'ha movimenti in Prima Nota';
        }
        if ($socio->tesseramenti()->exists()) {
            $blocchi[] = 'ha tessere CSEN';
        }

        return $blocchi;
    }

    // ----------------------------------------------------------------
    // Supporto
    // ----------------------------------------------------------------

    /** @return array{q: string, tipo: string, quota: string, stato: string} */
    private function filtri(Request $request): array
    {
        return [
            'q'     => trim((string) $request->query('q', '')),
            'tipo'  => (string) $request->query('tipo', ''),
            'quota' => (string) $request->query('quota', ''),
            // «Schede eliminate» (soft delete): solo il Presidente, che le ripristina
            'stato' => ($s = (string) $request->query('stato', 'attivo')) === 'eliminate' && ! $request->user()?->haRuolo('presidente') ? 'attivo' : $s,
        ];
    }

    /** CSV e libro soci: mai le schede eliminate (inserimenti sbagliati), anche dal filtro del Presidente. */
    private function filtriEsportazione(Request $request): array
    {
        $f = $this->filtri($request);
        if ($f['stato'] === 'eliminate') {
            $f['stato'] = 'attivo';
        }

        return $f;
    }

    private function query(array $filtri): Builder
    {
        $oggi = now()->toDateString();

        return Socio::query()
            ->when($filtri['stato'] === 'eliminate', fn ($q) => $q->onlyTrashed())
            ->when(! in_array($filtri['stato'], ['tutti', 'eliminate'], true), fn ($q) => $q->where('stato', $filtri['stato']))
            ->when(array_key_exists($filtri['tipo'], self::TIPI), fn ($q) => $q->where('tipo', $filtri['tipo']))
            ->when($filtri['quota'] === 'versata', fn ($q) => $q->whereDate('quota_scadenza', '>=', $oggi))
            ->when($filtri['quota'] === 'da_versare', fn ($q) => $q->where(fn ($w) => $w
                ->whereNull('quota_scadenza')->orWhereDate('quota_scadenza', '<', $oggi)))
            ->when($filtri['q'] !== '', function ($q) use ($filtri) {
                $testo = '%' . str_replace(['%', '_'], ['\%', '\_'], $filtri['q']) . '%';
                $q->where(fn ($w) => $w
                    ->where('cognome', 'like', $testo)
                    ->orWhere('nome', 'like', $testo)
                    ->orWhere('codice_fiscale', 'like', $testo)
                    ->orWhere(DB::raw("CONCAT(cognome, ' ', nome)"), 'like', $testo)
                    ->orWhere(DB::raw("CONCAT(nome, ' ', cognome)"), 'like', $testo));
            });
    }

    /** Dati validati pronti per il salvataggio. */
    private function prepara(SocioRequest $request): array
    {
        $dati = $request->validated();
        $dati['scheda_registrazione_presente'] = $request->boolean('scheda_registrazione_presente');
        $dati['numero_tessera_int'] = $dati['numero_tessera']
            ? (int) preg_replace('/\D/', '', $dati['numero_tessera'])
            : null;

        return $dati;
    }

    /**
     * Codice fiscale già presente? Se appartiene a un socio eliminato
     * (soft delete) si propone il ripristino invece di un errore generico.
     */
    private function controllaCodiceFiscale(string $cf, ?int $escludiId = null)
    {
        $esistente = Socio::withTrashed()
            ->where('codice_fiscale', $cf)
            ->when($escludiId, fn ($q) => $q->where('id', '!=', $escludiId))
            ->first();

        if (! $esistente) {
            return null;
        }

        $risposta = back()->withInput();

        if ($esistente->trashed()) {
            return $risposta
                ->withErrors(['codice_fiscale' => 'Questo codice fiscale appartiene a una scheda eliminata.'])
                ->with('ripristinabile', ['id' => $esistente->id, 'nome' => "{$esistente->cognome} {$esistente->nome}"]);
        }

        return $risposta->withErrors([
            'codice_fiscale' => "Questo codice fiscale è già registrato per {$esistente->cognome} {$esistente->nome}.",
        ]);
    }

    /**
     * Le esportazioni contengono dati personali di molti soci: si
     * registrano nell'audit log (chi, quando, quanti, con quali filtri).
     */
    private function registraEsportazione(string $formato, int $quanti, array $filtri): void
    {
        AuditLogService::scrivi([
            // operazione vuota: è un evento di business, non un'operazione su
            // un model (la colonna ammette solo created/updated/deleted/restored).
            'evento'          => 'SOCI_ESPORTATI',
            'entita_tipo'     => 'Socio',
            // Il testo cercato NON si registra: potrebbe essere un nome, e il
            // dettaglio entra nell'hash della catena (audit-log.md §8).
            'dettaglio'       => "Esportazione {$formato} del libro soci: {$quanti} soci. Filtri: "
                . collect($filtri)->filter(fn ($v) => $v !== '')
                    ->map(fn ($v, $k) => $k === 'q' ? 'ricerca testuale' : "{$k}={$v}")->implode(', ') . '.',
            'livello_rischio' => 'MEDIUM',
        ]);
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('soci'), 403, 'Il tuo ruolo può solo consultare questa sezione.');
    }

    /** Eliminazione e ripristino: riservati al Presidente (soci.md §3). */
    private function richiediPresidente(Request $request): void
    {
        abort_unless($request->user()->haRuolo('presidente'), 403, 'Solo il Presidente può eliminare o ripristinare una scheda.');
    }
}
