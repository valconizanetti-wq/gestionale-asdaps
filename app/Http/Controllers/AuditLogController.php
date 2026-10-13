<?php

namespace App\Http\Controllers;

use App\Models\AuditLogIntegrity;
use App\Services\AuditConsultazione as Consultazione;
use App\Services\AuditIntegrityService;
use App\Services\AuditLogService;
use App\Support\FirmaDigitale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit Log: consultazione, esportazioni, verifica della catena,
 * certificato annuale e attestazioni tecniche (audit-log.md §9; tavole
 * "Audit Log 1–5"). Ripreso dal Gestionale APS con le correzioni elencate
 * nella scheda. Rotte protette da 'permesso:audit_log' (Presidente pieno,
 * Revisore in sola lettura); certificato e attestazione anche al Revisore.
 */
class AuditLogController extends Controller
{
    /** Righe al massimo nel PDF di esportazione (DomPDF). */
    private const MAX_RIGHE_PDF = 1000;

    /** Righe per pagina che si possono scegliere nell'elenco (0 = tutte). */
    private const PER_PAGINA = [10, 50, 100, 0];

    /** Scelta delle righe per pagina, ricordata per la sessione. */
    private const SESSIONE_PER_PAGINA = 'audit_per_pagina';

    // ── Elenco ────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $f = Consultazione::filtri($request);

        // Righe per pagina: 10, 50, 100 o tutte; la scelta resta per la
        // sessione. Senza scelta 50 (il telefono chiede 10 dalla pagina).
        $richiesta = $request->query('per_pagina');
        if ($richiesta !== null && in_array((int) $richiesta, self::PER_PAGINA, true) && ctype_digit((string) $richiesta)) {
            $request->session()->put(self::SESSIONE_PER_PAGINA, (int) $richiesta);
        }
        $scelta = $request->session()->get(self::SESSIONE_PER_PAGINA);
        $perPagina = $scelta ?? 50;
        $query = Consultazione::query($f);
        $righe = $query->paginate($perPagina ?: max(1, (clone $query)->count()))->withQueryString();

        return view('audit-log.index', [
            'filtri' => $f,
            'filtriAttivi' => Consultazione::attivi($f),
            'righe' => $righe,
            'scelte' => [10 => '10', 50 => '50', 100 => '100', 0 => 'Tutte'],
            'perPagina' => $perPagina,
            'perPaginaScelta' => $scelta !== null,
            'stat' => Consultazione::statistiche($f['anno']),
            'anni' => Consultazione::anni(),
            'opzioni' => Consultazione::opzioni($f['anno']),
            'stato' => Consultazione::stato($f['anno']),
            'hashUltima' => DB::table('gl_audit_log')->where('anno', $f['anno'])->orderByDesc('id')->value('hash'),
        ]);
    }

    // ── Esportazioni (dei risultati filtrati) ─────────────────────────

    public function exportCsv(Request $request): StreamedResponse
    {
        $f = Consultazione::filtri($request);
        $righe = Consultazione::query($f)->get();
        $this->registraEsportazione('CSV', $righe->count(), $f);

        return response()->streamDownload(function () use ($righe) {
            $fh = fopen('php://output', 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['Riga', 'Data e ora', 'Modulo', 'Riferimento', 'Operazione', 'Codice evento', 'Rischio', 'Operatore', 'IP', 'Riepilogo'], ';');
            foreach ($righe as $r) {
                fputcsv($fh, [
                    $r->id,
                    \Carbon\Carbon::parse($r->created_at)->format('d/m/Y H:i:s'),
                    Consultazione::modello($r->modello ?? $r->entita_tipo),
                    $r->entita_id,
                    Consultazione::operazione($r),
                    $r->evento,
                    Consultazione::rischio($r->livello_rischio)[0],
                    $r->utente_nome,
                    $r->ip_address,
                    $r->dettaglio,
                ], ';');
            }
            fclose($fh);
        }, 'audit-log-' . $f['anno'] . '-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(Request $request)
    {
        $f = Consultazione::filtri($request);
        $totale = Consultazione::query($f)->count();
        $righe = Consultazione::query($f)->limit(self::MAX_RIGHE_PDF)->get();

        // Hash reale dell'ultima riga del registro al momento dell'estrazione,
        // letto prima di registrare l'esportazione stessa (nel Gestionale APS
        // un'impronta di conteggio, id e data, non della catena).
        $ultima = DB::table('gl_audit_log')->orderByDesc('id')->first(['id', 'hash']);
        $this->registraEsportazione('PDF', $righe->count(), $f);

        $pdf = Pdf::loadView('pdf.audit-log', [
            'righe' => $righe,
            'totale' => $totale,
            'filtri' => $f,
            'opzioni' => Consultazione::opzioni($f['anno']),
            'ultima' => $ultima,
            'generatoIl' => now(),
            'operatore' => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="audit-log-' . $f['anno'] . '-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    // ── Verifica di integrità ─────────────────────────────────────────

    public function verifica(Request $request): View
    {
        $anno = Consultazione::filtri($request)['anno'];

        return view('audit-log.verifica', [
            'anno' => $anno,
            'anni' => Consultazione::anni(),
            'esito' => session('audit_esito'),
            'ultima' => Consultazione::stato($anno)['verifica'],
            'certificati' => AuditLogIntegrity::orderByDesc('anno')->orderByDesc('id')->get(),
            'attestazioni' => DB::table('gl_attestazioni_tecniche')->orderByDesc('data_firma')->orderByDesc('id')->get(),
            'puoCertificare' => $this->puoCertificare($request),
            'eccezioni' => AuditIntegrityService::eccezioniNote(),
        ]);
    }

    /**
     * Esegue la verifica (sola lettura sul registro), salva l'esito per la
     * fascia dell'elenco e torna alla pagina, che lo mostra. Nel Gestionale
     * APS era una chiamata che rispondeva in JSON, e il pulsante "Ricalcola"
     * dell'elenco mostrava il JSON grezzo.
     */
    public function eseguiVerifica(Request $request): RedirectResponse
    {
        $anno = (int) $request->input('anno', now()->year);
        $esito = AuditIntegrityService::verificaCatena();
        Consultazione::registraVerifica($esito, 'manuale');

        $anomalieAnno = array_values(array_filter($esito['anomalie'], fn ($a) => (int) $a['anno'] === $anno));
        $collegamento = array_column($esito['anomalie_collegamento'], 'id');

        return redirect()->route('audit-log.verifica', ['anno' => $anno])->with('audit_esito', [
            'anno' => $anno,
            'il' => now()->toIso8601String(),
            'riepilogo' => Consultazione::riepilogoAnno($anno),
            'righe_totali' => $esito['records_totali'],
            'anomalie_totali' => count($esito['anomalie']),
            'hash_finale' => $esito['hash_finale'],
            'anomalie' => array_map(fn ($a) => $a + ['tipo' => in_array($a['id'], $collegamento, true) ? 'collegamento' : 'contenuto'],
                array_slice($anomalieAnno, 0, 100)),
            'anomalie_anno' => count($anomalieAnno),
        ]);
    }

    public function generaCertificato(Request $request): RedirectResponse
    {
        $this->richiediCertificazione($request);
        $anno = (int) $request->input('anno');
        $riepilogo = Consultazione::riepilogoAnno($anno);
        if (! $riepilogo) {
            return back()->with('error', "Nessuna riga nel registro per il {$anno}.");
        }

        // La catena deve essere integra fino all'ultima riga dell'anno.
        $esito = AuditIntegrityService::verificaCatena();
        Consultazione::registraVerifica($esito, 'manuale');
        $anomalie = array_filter($esito['anomalie'], fn ($a) => $a['id'] <= $riepilogo['ultima_riga']);
        if ($anomalie) {
            return redirect()->route('audit-log.verifica', ['anno' => $anno])
                ->with('error', 'Certificato non generato: ' . count($anomalie) . ' anomalie nella catena fino alla riga n. ' . $riepilogo['ultima_riga'] . '.');
        }

        $uuid = (string) Str::uuid();
        $utente = $request->user();
        $generatoDa = $utente->name . ' (' . ($utente->haRuolo('revisore') ? 'Revisore' : 'Presidente') . ')';

        $pdf = Pdf::loadView('pdf.audit-certificato', [
            'anno' => $anno,
            'r' => $riepilogo,
            'uuid' => $uuid,
            'generatoDa' => $generatoDa,
            'generatoIl' => now(),
        ])->setPaper('a4');

        // Un file per certificato, con il codice nel nome: un secondo
        // certificato dello stesso anno non sovrascrive il primo (nel
        // Gestionale APS sì, e il codice non veniva salvato).
        Storage::disk('local')->put(self::percorsoCertificato($anno, $uuid), $pdf->output());

        $cert = AuditLogIntegrity::create([
            'anno' => $anno,
            'computed_at' => now(),
            'hash_chain' => $riepilogo['hash_ultima'],
            'records_count' => $riepilogo['totale'],
            'esito' => 'ok',
            'generato_da' => $generatoDa,
            'uuid_documento' => $uuid,
        ]);

        AuditLogService::scrivi([
            'evento' => 'CERTIFICATO_INTEGRITA_GENERATO',
            'entita_tipo' => 'AuditLog',
            'entita_id' => $cert->id,
            'livello_rischio' => 'MEDIUM',
            'dettaglio' => "Certificato di integrità {$anno}: {$riepilogo['totale']} righe, dalla n. {$riepilogo['prima_riga']} alla n. {$riepilogo['ultima_riga']}.",
        ]);

        return redirect()->route('audit-log.verifica', ['anno' => $anno])->with('success', "Certificato di integrità {$anno} generato.");
    }

    public function scaricaCertificato(AuditLogIntegrity $certificato)
    {
        $percorso = $certificato->uuid_documento ? self::percorsoCertificato($certificato->anno, $certificato->uuid_documento) : null;
        abort_unless($percorso && Storage::disk('local')->exists($percorso), 404, 'PDF del certificato non trovato.');

        return Storage::disk('local')->download($percorso, "certificato-integrita-{$certificato->anno}-" . substr($certificato->uuid_documento, 0, 8) . '.pdf');
    }

    // ── Attestazioni tecniche ─────────────────────────────────────────

    /** Bozza da firmare fuori dal gestionale: dati della verifica di oggi, non salvata. */
    public function bozzaAttestazione(Request $request)
    {
        $this->richiediCertificazione($request);
        $esito = AuditIntegrityService::verificaCatena();

        $pdf = Pdf::loadView('pdf.audit-attestazione', [
            'esito' => $esito,
            'eccezioni' => AuditIntegrityService::eccezioniNote(),
            'operatore' => $request->user()->name,
            'generatoIl' => now(),
        ])->setPaper('a4');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="bozza-attestazione-tecnica-' . now()->format('Y-m-d') . '.pdf"',
        ]);
    }

    public function caricaAttestazione(Request $request): RedirectResponse
    {
        $this->richiediCertificazione($request);
        $tipoFirma = null;

        $dati = $request->validate([
            'oggetto' => ['required', 'string', 'max:255'],
            'data_firma' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
            // PDF con la firma dentro (PAdES) o busta .p7m (CAdES): si
            // controlla che la firma ci sia, non la validità del certificato.
            'file' => ['required', 'file', 'max:20480', function ($attributo, $file, $errore) use (&$tipoFirma) {
                $estensione = strtolower($file->getClientOriginalExtension());
                if (! in_array($estensione, ['pdf', 'p7m'], true)) {
                    $errore('L\'attestazione firmata deve essere un PDF o un file .p7m.');

                    return;
                }
                $tipoFirma = FirmaDigitale::rileva($file->getRealPath(), $estensione);
                if (! $tipoFirma) {
                    $errore('Nel file non c\'è una firma digitale: firmalo (firma digitale o CIE) e caricalo di nuovo.');
                }
            }],
            'relazione' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
        ], [
            'file.max' => 'Il file supera i 20 MB.',
            'relazione.mimes' => 'La relazione deve essere PDF o Word.',
        ], ['data_firma' => 'data della firma', 'file' => 'PDF firmato', 'relazione' => 'relazione tecnica']);

        $esito = AuditIntegrityService::verificaCatena();
        $eccezioni = AuditIntegrityService::eccezioniNote();
        $salva = fn ($file) => $file->storeAs('audit/attestazioni', Str::uuid() . '.' . strtolower($file->getClientOriginalExtension()), 'local');

        $id = DB::table('gl_attestazioni_tecniche')->insertGetId([
            'oggetto' => $dati['oggetto'],
            'data_firma' => $dati['data_firma'],
            'note' => $dati['note'] ?? null,
            'eccezioni_ids' => implode(',', array_keys($eccezioni)) ?: null,
            'eccezioni_count' => count($eccezioni),
            'anomalie_count' => count($esito['anomalie']),
            'records_totali' => $esito['records_totali'],
            'record_pre_catena' => $esito['record_pre_catena'],
            'corretti' => $esito['corretti'],
            'gap_count' => $esito['gap_count'],
            'pdf_path' => $salva($request->file('file')),
            'pdf_filename' => $request->file('file')->getClientOriginalName(),
            'relazione_path' => $request->hasFile('relazione') ? $salva($request->file('relazione')) : null,
            'relazione_filename' => $request->hasFile('relazione') ? $request->file('relazione')->getClientOriginalName() : null,
            'caricato_da' => $request->user()->name,
            'created_at' => now(),
        ]);

        AuditLogService::scrivi([
            'evento' => 'ATTESTAZIONE_TECNICA_CARICATA',
            'entita_tipo' => 'AttestazioneTecnica',
            'entita_id' => $id,
            'livello_rischio' => 'MEDIUM',
            'dettaglio' => 'Attestazione tecnica n. ' . $id . ' firmata il ' . \Carbon\Carbon::parse($dati['data_firma'])->format('d/m/Y')
                . " (firma {$tipoFirma}); eccezioni note: " . count($eccezioni) . '.' . ($request->hasFile('relazione') ? ' Con relazione tecnica.' : ''),
        ]);

        return redirect()->route('audit-log.verifica')->with('success', 'Attestazione tecnica caricata.');
    }

    public function scaricaAttestazione(int $id, string $parte = 'firmata')
    {
        $att = DB::table('gl_attestazioni_tecniche')->where('id', $id)->first();
        $percorso = $parte === 'relazione' ? $att?->relazione_path : $att?->pdf_path;
        abort_unless($percorso && Storage::disk('local')->exists($percorso), 404, 'File non trovato.');

        $estensione = pathinfo($percorso, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($percorso, ($parte === 'relazione' ? 'relazione-tecnica-' : 'attestazione-tecnica-') . $att->data_firma . '.' . $estensione);
    }

    // ── Supporto ──────────────────────────────────────────────────────

    public static function percorsoCertificato(int $anno, string $uuid): string
    {
        return "audit/certificati/{$anno}-{$uuid}.pdf";
    }

    /** Certificato e attestazione: Presidente e, se c'è, Revisore (audit-log.md §9). */
    private function puoCertificare(Request $request): bool
    {
        return $request->user()->haRuolo('presidente', 'revisore');
    }

    private function richiediCertificazione(Request $request): void
    {
        abort_unless($this->puoCertificare($request), 403, 'Solo il Presidente o il Revisore.');
    }

    /** L'esportazione del registro (con IP e riepiloghi) resta tracciata, come quella dei soci. */
    private function registraEsportazione(string $formato, int $righe, array $f): void
    {
        $filtri = collect($f)->except('anno')->filter()->keys()->implode(', ');

        AuditLogService::scrivi([
            'evento' => 'AUDIT_ESPORTATO',
            'entita_tipo' => 'AuditLog',
            'livello_rischio' => 'MEDIUM',
            'dettaglio' => "Esportazione {$formato} dell'Audit Log {$f['anno']}: {$righe} righe" . ($filtri ? ", filtri: {$filtri}" : '') . '.',
        ]);
    }
}
