<?php

namespace App\Http\Controllers;

use App\Models\NotaRimborso;
use App\Models\PrimaNota;
use App\Models\Socio;
use App\Services\NoteRimborso;
use App\Support\Importo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Anticipi e note di rimborso (v0.43.0, specifiche/anticipi.md).
 * AN-3 elenco per socio e note; passo 1 (nota o rinuncia), scheda della nota
 * con i passi 2 e 3 (AN-4, AN-4b), PDF (AN-2).
 *
 * Rotte nel gruppo 'permesso:prima_nota': consultano tutti quelli che
 * vedono la Prima Nota (Revisore, CD); registrano Presidente e Tesoriere
 * (puoModificare('prima_nota')), anche le proprie spese (A5): l'operazione
 * arriva per email agli altri (rischio alto).
 */
class AnticipiController extends Controller
{
    public function __construct(private NoteRimborso $note)
    {
    }

    // ----------------------------------------------------------------
    // AN-3
    // ----------------------------------------------------------------

    public function index(Request $request)
    {
        $anniNote = NotaRimborso::distinct()->pluck('anno')->push(now()->year)->map(fn ($a) => (int) $a)->unique()->sortDesc()->values();
        $anno = (int) $request->query('anno', now()->year);
        $anno = $anniNote->contains($anno) ? $anno : now()->year;

        $spese = NoteRimborso::speseSenzaNota()
            ->with(['anticipante', 'categoria:id,nome,codice', 'documenti:id,prima_nota_id,nome,path', 'scontrino.file'])
            ->orderBy('data')->orderBy('id')->get();
        $gruppi = $this->perSocio($spese);

        $aperte = NoteRimborso::daRimborsare();
        $daFirmare = NotaRimborso::where('stato', 'emessa')->orderBy('anno')->orderBy('progressivo')->get(['id', 'numero', 'totale']);
        $daPagare = NotaRimborso::where('stato', 'firmata')->orderBy('anno')->orderBy('progressivo')->get(['id', 'numero', 'totale', 'tipo']);
        $pagate = NotaRimborso::where('stato', 'pagata')->where('tipo', 'rimborso')->whereYear('pagata_il', $anno);

        return view('anticipi.index', [
            'gruppi' => $gruppi,
            'note' => NotaRimborso::where('anno', $anno)->orderByDesc('progressivo')->get(),
            'anno' => $anno,
            'anniNote' => $anniNote,
            'kpi' => [
                'da_rimborsare' => (string) (clone $aperte)->sum('importo'),
                'da_rimborsare_n' => (clone $aperte)->count(),
                'da_rimborsare_soci' => (clone $aperte)->distinct()->count('anticipante_socio_id'),
                'da_firmare' => $daFirmare,
                'da_pagare' => $daPagare,
                'rimborsato' => (string) (clone $pagate)->sum('totale'),
                'rimborsato_n' => (clone $pagate)->count(),
            ],
        ]);
    }

    // ----------------------------------------------------------------
    // Passo 1
    // ----------------------------------------------------------------

    public function create(Request $request)
    {
        $this->richiediModifica($request);
        $tipo = $request->query('tipo') === 'rinuncia' ? 'rinuncia' : 'rimborso';
        $socio = Socio::withTrashed()->find((int) $request->query('socio'));
        if (! $socio) {
            return redirect()->route('prima-nota.anticipi.index')->with('error', 'Scegli le spese di un socio.');
        }
        try {
            $spese = NoteRimborso::speseScelte($socio, (array) $request->query('spese', []));
        } catch (ValidationException $e) {
            return redirect()->route('prima-nota.anticipi.index')->with('error', $e->validator->errors()->first());
        }

        $ultima = $spese->max(fn ($m) => $m->dataSpesa()->toDateString());
        $anno = (int) now()->year;

        return view('anticipi.create', [
            'tipo' => $tipo,
            'socio' => $socio,
            'carica' => NoteRimborso::caricaDi($socio),
            'spese' => $spese,
            'totale' => Importo::daCentesimi($spese->sum(fn ($m) => Importo::centesimi($m->importo))),
            'minimo' => $ultima,
            'prossimo' => sprintf('RIMB-%d-%03d', $anno, (int) NotaRimborso::where('anno', $anno)->max('progressivo') + 1),
            'visto' => NoteRimborso::visto($socio),
        ]);
    }

    public function store(Request $request)
    {
        $this->richiediModifica($request);
        $request->merge(['iban' => $request->filled('iban') ? strtoupper(preg_replace('/\s+/', '', (string) $request->input('iban'))) : null]);
        $tipo = $request->input('tipo') === 'rinuncia' ? 'rinuncia' : 'rimborso';

        $dati = $request->validate([
            'socio_id' => ['required', 'integer'],
            'spese' => ['required', 'array', 'min:1', 'max:100'],
            'spese.*' => ['integer'],
            'tipo' => ['required', 'in:rimborso,rinuncia'],
            'data_nota' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:' . today()->toDateString()],
            'modalita' => [Rule::requiredIf($tipo === 'rimborso'), 'nullable', Rule::in(['cassa', 'banca'])],
            'iban' => [Rule::requiredIf($tipo === 'rimborso' && $request->input('modalita') === 'banca'), 'nullable', 'string', 'max:34',
                fn ($a, $v, $fail) => $v && ! NoteRimborso::ibanValido($v) ? $fail('L\'IBAN non torna: controlla lettere e cifre (per l\'Italia 27 caratteri, IT…).') : null],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'data_nota.before_or_equal' => 'La data della nota non può essere nel futuro.',
            'modalita.required' => 'Scegli come sarà rimborsato: in contanti o con un bonifico.',
            'iban.required' => 'Per il bonifico serve l\'IBAN del socio.',
            'spese.required' => 'Scegli almeno una spesa.',
        ], ['data_nota' => 'data della nota', 'modalita' => 'rimborso']);

        $socio = Socio::withTrashed()->findOrFail((int) $dati['socio_id']);
        $nota = $this->note->prepara($socio, $dati['spese'], $tipo, $dati['data_nota'], $dati['modalita'] ?? null,
            $dati['iban'] ?? null, isset($dati['note']) ? trim($dati['note']) : null, $request->user());

        return redirect()->route('prima-nota.anticipi.show', $nota)->with('success', $nota->isRinuncia()
            ? "Dichiarazione di rinuncia {$nota->numero} generata: stampala e falla firmare, poi carica la copia firmata."
            : "Nota {$nota->numero} generata: stampala e falla firmare, poi carica la copia firmata.");
    }

    // ----------------------------------------------------------------
    // Scheda della nota (AN-4, AN-4b)
    // ----------------------------------------------------------------

    public function show(NotaRimborso $nota)
    {
        $nota->load(['socio', 'preparataDa:id,name', 'firmataDa:id,name', 'pagataDa:id,name', 'annullataDa:id,name']);
        $movimenti = PrimaNota::whereIn('id', collect($nota->dati['righe'] ?? [])->pluck('movimento_id'))
            ->with(['documenti:id,prima_nota_id,nome', 'scontrino.file'])->get()->keyBy('id');

        return view('anticipi.show', [
            'n' => $nota,
            'movimenti' => $movimenti,
            'donazione' => $nota->isRinuncia() ? $nota->movimenti()->where('sospeso', false)->first() : null,
            'chiusoPagamento' => $nota->pagata_il ? app(\App\Services\AnniContabili::class)->chiuso((int) $nota->pagata_il->year) : false,
        ]);
    }

    public function pdf(NotaRimborso $nota)
    {
        return response($this->note->contenutoPdf($nota), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nota->numero . '.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function firmata(Request $request, NotaRimborso $nota)
    {
        $this->richiediModifica($request);
        $request->validateWithBag('firmata', [
            'firmata' => ['required', 'file', 'max:' . NoteRimborso::MAX_KB, 'mimes:' . implode(',', NoteRimborso::ESTENSIONI)],
        ], [
            'firmata.required' => 'Scegli la scansione o la foto della nota firmata.',
            'firmata.max' => 'Il file pesa più di 8 MB.',
            'firmata.mimes' => 'La copia firmata deve essere un PDF o una foto (JPG, PNG).',
            'firmata.uploaded' => 'Il file non è arrivato per intero: riprova.',
        ]);

        try {
            $this->note->caricaFirmata($nota, $request->file('firmata'), $request->user());
        } catch (ValidationException $e) {
            throw $e->errorBag('firmata');
        }

        return redirect()->route('prima-nota.anticipi.show', $nota)->with('success', $nota->isRinuncia()
            ? 'Copia firmata caricata: ora si registra la donazione.'
            : 'Copia firmata caricata: ora si registra il pagamento.');
    }

    public function vediFirmata(Request $request, NotaRimborso $nota)
    {
        abort_unless($nota->firmata_path && Storage::disk('local')->exists($nota->firmata_path), 404, 'La copia firmata non c\'è.');

        return response()->file(Storage::disk('local')->path($nota->firmata_path), [
            'Content-Type' => Storage::disk('local')->mimeType($nota->firmata_path),
            'Content-Disposition' => ($request->boolean('scarica') ? 'attachment' : 'inline') . '; filename="' . $nota->firmata_nome . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pagamento(Request $request, NotaRimborso $nota)
    {
        $this->richiediModifica($request);
        $campo = $nota->isRinuncia() ? 'data_rinuncia' : 'data_pagamento';
        $dati = $request->validateWithBag('pagamento', [
            $campo => ['required', 'date_format:Y-m-d'],
        ], [], [$campo => 'data']);

        try {
            $this->note->registraPagamento($nota, $dati[$campo], $request->user());
        } catch (ValidationException $e) {
            throw $e->errorBag('pagamento');
        }

        return redirect()->route('prima-nota.anticipi.show', $nota)->with('success', $nota->isRinuncia()
            ? "Donazione registrata: spesa e donazione sono in Prima Nota in cassa con la data del {$nota->pagata_il->format('d/m/Y')}."
            : "Pagamento registrato: le spese sono in Prima Nota dalla " . mb_strtolower(PrimaNota::CONTI[$nota->conto]) . " con la data del {$nota->pagata_il->format('d/m/Y')}.");
    }

    public function annullaPagamento(Request $request, NotaRimborso $nota)
    {
        $this->richiediModifica($request);
        $dati = $this->motivazione($request, 'annulla_pagamento');
        try {
            $this->note->annullaPagamento($nota, $dati['motivazione'], $request->user());
        } catch (ValidationException $e) {
            throw $e->errorBag('annulla_pagamento');
        }

        return redirect()->route('prima-nota.anticipi.show', $nota)->with('success', $nota->isRinuncia()
            ? 'Registrazione annullata: le spese sono di nuovo da rimborsare e l\'entrata della donazione è tolta.'
            : 'Pagamento annullato: le spese sono di nuovo fuori dai saldi e la nota torna «firmata».');
    }

    public function annulla(Request $request, NotaRimborso $nota)
    {
        $this->richiediModifica($request);
        $dati = $this->motivazione($request, 'annulla');
        try {
            $this->note->annulla($nota, $dati['motivazione'], $request->user());
        } catch (ValidationException $e) {
            throw $e->errorBag('annulla');
        }

        return redirect()->route('prima-nota.anticipi.show', $nota)->with('success', "{$nota->numero} annullata: le spese sono tornate tra quelle senza nota.");
    }

    // ----------------------------------------------------------------
    // Supporto
    // ----------------------------------------------------------------

    /**
     * Spese raggruppate per socio: prima i membri del CD nell'ordine delle
     * cariche, poi gli altri in ordine alfabetico.
     *
     * @return Collection<int, array{socio: ?Socio, carica: ?string, spese: Collection}>
     */
    private function perSocio(Collection $spese): Collection
    {
        $ordine = NoteRimborso::sociAnticipanti()->pluck('carica_cd', 'id');
        $posizione = $ordine->keys()->flip();

        return $spese->groupBy('anticipante_socio_id')
            ->map(fn ($righe, $id) => [
                'socio' => $righe->first()->anticipante,
                'carica' => $ordine[$id] ?? ($righe->first()->anticipante ? NoteRimborso::caricaDi($righe->first()->anticipante) : null),
                'spese' => $righe,
            ])
            ->sortBy(fn ($g, $id) => sprintf('%05d', $posizione[$id] ?? 99999) . mb_strtolower(($g['socio']?->cognome ?? '') . ' ' . ($g['socio']?->nome ?? '')))
            ->values();
    }

    private function motivazione(Request $request, string $bag): array
    {
        return $request->validateWithBag($bag, [
            'motivazione' => ['required', 'string', 'min:15', 'max:1000'],
        ], [
            'motivazione.required' => 'Scrivi il motivo: resta nell\'Audit Log.',
            'motivazione.min' => 'Il motivo deve avere almeno 15 caratteri.',
        ]);
    }

    private function richiediModifica(Request $request): void
    {
        abort_unless($request->user()->puoModificare('prima_nota'), 403, 'Il tuo ruolo può solo consultare le note di rimborso.');
    }
}
