<?php

namespace App\Http\Controllers;

use App\Models\AnnoContabile;
use App\Models\PrimaNota;
use App\Models\Verbale;
use App\Services\AnniContabili;
use App\Services\AuditLogService;
use App\Support\Importo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Anni contabili: saldi di apertura del primo anno, chiusura dopo
 * l'approvazione del rendiconto, riapertura (prima-nota.md §3, tavola PN-8).
 * Rotte sotto 'permesso:prima_nota'. Riapertura solo al Presidente.
 */
class AnniContabiliController extends Controller
{
    public function __construct(private AnniContabili $anni)
    {
    }

    public function index(Request $request)
    {
        $righe = AnnoContabile::orderByDesc('anno')->get();

        // Senza nessun anno: si propone l'anno in corso, per scrivere i saldi
        // di partenza prima ancora del primo movimento.
        $primo = $righe->sortBy('anno')->first();
        $annoSaldi = $primo?->anno ?? now()->year;

        $elenco = $righe->map(fn ($r) => [
            'riga' => $r,
            'apertura' => $this->anni->apertura($r->anno),
            'finali' => $r->isChiuso() && $r->saldo_chiusura_cassa !== null
                ? ['cassa' => (float) $r->saldo_chiusura_cassa, 'banca' => (float) $r->saldo_chiusura_banca]
                : $this->anni->finali($r->anno),
            'movimenti' => PrimaNota::query()->anno($r->anno)->count(),
        ]);

        // Si chiude un anno alla volta, dal più vecchio aperto.
        $daChiudere = $righe->where('stato', 'aperto')->sortBy('anno')->first();
        $daRiaprire = $righe->where('stato', 'chiuso')->sortByDesc('anno')->first();

        return view('prima-nota.anni', [
            'elenco' => $elenco,
            'annoSaldi' => $annoSaldi,
            'rigaSaldi' => $primo,
            'aperturaSaldi' => $this->anni->apertura($annoSaldi),
            'movimentiSaldi' => PrimaNota::query()->anno($annoSaldi)->count(),
            'daChiudere' => $daChiudere,
            'controlli' => $daChiudere ? $this->anni->controlliChiusura($daChiudere->anno) : [],
            'chiudibile' => $daChiudere ? $this->anni->chiudibile($daChiudere->anno) : false,
            'verbali' => $daChiudere ? $this->anni->verbaliApprovazione($daChiudere->anno) : collect(),
            'daRiaprire' => $daRiaprire,
        ]);
    }

    /** Saldi di apertura del primo anno del gestionale. */
    public function saldi(Request $request, int $anno)
    {
        $this->richiediModifica($request);
        abort_unless($this->anni->primoAnno($anno), 422, "I saldi di apertura si scrivono solo per il primo anno: dagli anni dopo vengono dai saldi finali dell'anno prima.");

        $riga = $this->anni->trova($anno);
        if ($riga?->isChiuso()) {
            return back()->with('error', "L'anno {$anno} è chiuso.");
        }
        $conMovimenti = PrimaNota::query()->anno($anno)->exists();
        $giaScritti = $riga && $riga->saldo_apertura_cassa !== null;
        $serveMotivo = $conMovimenti && $giaScritti;

        // Zero ammesso (conto senza soldi), negativi no.
        $nonNegativo = function ($attr, $v, $fail) {
            if (Importo::leggi($v) === null) {
                $fail('Il saldo non è valido: scrivilo come 380,00 o 2.950,40.');
            }
        };

        $dati = $request->validate([
            'saldo_apertura_cassa' => ['required', $nonNegativo],
            'saldo_apertura_banca' => ['required', $nonNegativo],
            'saldi_fonte' => ['nullable', 'string', 'max:255'],
            'documento' => ['nullable', 'file', 'mimes:pdf,csv,txt,jpg,jpeg,png,xlsx,xls', 'max:8192'],
            'motivazione' => [Rule::requiredIf($serveMotivo), 'nullable', 'string', 'min:15', 'max:1000'],
        ], [
            'motivazione.required' => "L'anno ha già movimenti: scrivi perché cambi i saldi di apertura.",
            'motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.',
        ], ['saldo_apertura_cassa' => 'saldo di cassa', 'saldo_apertura_banca' => 'saldo di banca', 'saldi_fonte' => 'provenienza']);

        $leggi = fn ($v) => Importo::leggi($v) ?? '0.00';
        $vecchio = null;

        DB::transaction(function () use ($anno, $dati, $request, $leggi, $serveMotivo, &$vecchio) {
            $riga = $this->anni->assicura($anno);
            $vecchio = $riga->saldi_documento_path;
            $campi = [
                'saldo_apertura_cassa' => $leggi($dati['saldo_apertura_cassa']),
                'saldo_apertura_banca' => $leggi($dati['saldo_apertura_banca']),
                'saldi_fonte' => $dati['saldi_fonte'] ?? null,
            ];
            if ($request->hasFile('documento')) {
                $campi['saldi_documento_path'] = $request->file('documento')->store('prima-nota/saldi', 'local');
                $campi['saldi_documento_nome'] = mb_substr($request->file('documento')->getClientOriginalName(), 0, 255);
            }
            $riga->update($campi);

            if ($serveMotivo) {
                AuditLogService::scrivi([
                    'evento' => 'SALDI_APERTURA_MODIFICATI',
                    'entita_tipo' => 'AnnoContabile',
                    'entita_id' => $riga->id,
                    'anno' => $anno,
                    'livello_rischio' => 'HIGH',
                    'dettaglio' => "Saldi di apertura {$anno} cambiati con movimenti già registrati. Motivazione: {$dati['motivazione']}",
                ]);
            }
        });

        if ($request->hasFile('documento') && $vecchio) {
            Storage::disk('local')->delete($vecchio);
        }

        return redirect()->route('prima-nota.anni.index')->with('success', "Saldi di apertura {$anno} salvati.");
    }

    public function documento(int $anno)
    {
        $riga = $this->anni->trova($anno);
        abort_unless($riga?->saldi_documento_path && Storage::disk('local')->exists($riga->saldi_documento_path), 404);

        return Storage::disk('local')->download($riga->saldi_documento_path, $riga->saldi_documento_nome ?? 'saldi.pdf');
    }

    public function chiudi(Request $request, int $anno)
    {
        $this->richiediModifica($request);
        // Il verbale si sceglie dall'elenco delle Assemblee dell'anno in corso
        // (collaudo J11); data e riferimento si prendono dal verbale.
        $ammessi = $this->anni->verbaliApprovazione($anno)->pluck('id')->all();
        $dati = $request->validate([
            'verbale_id' => ['required', 'integer', Rule::in($ammessi)],
        ], [
            'verbale_id.required' => "Scegli il verbale dell'Assemblea che ha approvato il rendiconto {$anno}.",
            'verbale_id.in' => "Scegli il verbale firmato dell'Assemblea che approva il rendiconto {$anno}.",
        ]);

        try {
            $this->anni->chiudi($anno, Verbale::findOrFail($dati['verbale_id']), $request->user());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('prima-nota.anni.index')->with('success', "Anno {$anno} chiuso. I saldi finali sono l'apertura del " . ($anno + 1) . '.');
    }

    public function riapri(Request $request, int $anno)
    {
        abort_unless($request->user()->haRuolo('presidente'), 403, "Solo il Presidente può riaprire un anno chiuso.");
        $dati = $request->validate(['motivazione' => ['required', 'string', 'min:15', 'max:1000']],
            ['motivazione.min' => 'La motivazione deve avere almeno 15 caratteri.']);

        try {
            $this->anni->riapri($anno, $dati['motivazione']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('prima-nota.anni.index')->with('success', "Anno {$anno} riaperto.");
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('prima_nota'), 403, 'Il tuo ruolo può solo consultare la Prima Nota.');
    }
}
