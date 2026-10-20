<?php

namespace App\Services;

use App\Models\GovernanceCd;
use App\Models\NotaRimborso;
use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\Socio;
use App\Models\User;
use App\Support\Importo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Anticipi e note di rimborso (v0.43.0, specifiche/anticipi.md). Ripreso dal
 * Gestionale APS del Dante (PrimaNotaController: generaNota,
 * confermaPagamento, annullaNota, notaRimborsoPdf) con i difetti corretti
 * (analisi-moduli-anticipi.md §2.2-bis):
 *
 * 1. numero RIMB-ANNO-NNN con lock e vincolo unico; la nota annullata resta
 *    con il suo numero, che non torna;
 * 2. chi ha anticipato è un socio, non un testo;
 * 3. ogni passo scrive la sua riga nell'Audit Log (pagamento, annullamento
 *    e rinuncia a rischio alto, con l'avviso agli altri);
 * 4. pagamento in una transazione, con i controlli sulle date e sull'anno
 *    chiuso; il movimento prende la data del pagamento (A1);
 * 5. la copia firmata si carica (passo 2);
 * 6. il PDF si salva alla generazione, da una fotografia dei dati;
 * 7. un pagamento sbagliato si annulla con il motivo;
 * 8. note e IBAN stanno sulla nota, non su ogni spesa.
 *
 * In più (risposte di Paolo del 20/10/2026): rinuncia al rimborso come
 * donazione (A6), quietanza dei contanti (E1), visto del Presidente sulla
 * nota del Tesoriere (E2), conto fissato dalla modalità (E3).
 */
class NoteRimborso
{
    public const CARTELLA = 'prima-nota/rimborsi';

    public const ESTENSIONI = ['jpg', 'jpeg', 'png', 'pdf'];

    public const MAX_KB = 8192;

    public function __construct(private AnniContabili $anni)
    {
    }

    // ----------------------------------------------------------------
    // Interrogazioni
    // ----------------------------------------------------------------

    /** Spese anticipate aperte e non ancora in una nota, di qualunque anno. */
    public static function speseSenzaNota(): Builder
    {
        return PrimaNota::query()->where('sospeso', true)->whereNull('rimborsato_il')->whereNull('nota_rimborso_id');
    }

    /** Spese anticipate non ancora rimborsate (anche quelle già in una nota). */
    public static function daRimborsare(): Builder
    {
        return PrimaNota::query()->where('sospeso', true)->whereNull('rimborsato_il');
    }

    /** Socio che può anticipare (E5): attivo e maggiorenne. */
    public static function puoAnticipare(?Socio $s): bool
    {
        return $s && ! $s->trashed() && $s->stato === 'attivo' && ! $s->isMinorenne();
    }

    /**
     * Soci tra cui scegliere chi ha anticipato (E5): attivi e maggiorenni,
     * prima i membri del CD nell'ordine delle cariche, poi gli altri in
     * ordine alfabetico. $includi: il socio già scelto, anche se non più
     * attivo. Ogni socio ha l'attributo `carica_cd` (o null).
     *
     * @return Collection<int, Socio>
     */
    public static function sociAnticipanti(?int $includi = null): Collection
    {
        $cariche = [];
        foreach (ConsiglioDirettivo::membri() as $i => $m) {
            $cariche[$m->socio_id] ??= [$i, ConsiglioDirettivo::etichetta($m->carica_codice)];
        }

        $soci = Socio::query()
            ->where(fn ($q) => $q->where('stato', 'attivo')
                ->where(fn ($w) => $w->whereNull('data_nascita')->orWhere('data_nascita', '<=', today()->subYears(18)->toDateString())))
            ->when($includi, fn ($q) => $q->orWhere('id', $includi))
            ->orderBy('cognome')->orderBy('nome')
            ->get(['id', 'nome', 'cognome', 'stato', 'data_nascita']);

        return $soci->each(fn (Socio $s) => $s->setAttribute('carica_cd', $cariche[$s->id][1] ?? null))
            ->sortBy(fn (Socio $s) => isset($cariche[$s->id]) ? sprintf('0-%03d', $cariche[$s->id][0]) : '1-' . mb_strtolower($s->cognome . ' ' . $s->nome))
            ->values();
    }

    /** Carica nel CD in carica del socio, o null. */
    public static function caricaDi(Socio $s): ?string
    {
        $m = GovernanceCd::where('in_carica', true)->where('socio_id', $s->id)->first();

        return $m ? ConsiglioDirettivo::etichetta($m->carica_codice) : null;
    }

    /**
     * Chi mette il visto (E2): il Tesoriere; se ha anticipato proprio il
     * Tesoriere, il Presidente.
     *
     * @return array{carica: string, nome: ?string}
     */
    public static function visto(Socio $socio): array
    {
        $tesoriere = null;
        $presidente = null;
        foreach (ConsiglioDirettivo::membri() as $m) {
            $ruoli = ConsiglioDirettivo::ruoliCarica($m->carica_codice);
            if (in_array('tesoriere', $ruoli, true)) {
                $tesoriere ??= $m;
            }
            if (in_array('presidente', $ruoli, true)) {
                $presidente ??= $m;
            }
        }
        $nome = fn (?GovernanceCd $m) => $m?->socio ? trim($m->socio->nome . ' ' . $m->socio->cognome) : null;

        if ($tesoriere && (int) $tesoriere->socio_id === (int) $socio->id) {
            return ['carica' => 'il Presidente', 'nome' => $nome($presidente)];
        }

        return ['carica' => 'il Tesoriere', 'nome' => $nome($tesoriere)];
    }

    /**
     * Controlla le spese scelte per una nota: tutte aperte, senza nota, dello
     * stesso socio e già classificate. Restituisce le spese (lette con il
     * lock se $lock) o lancia una ValidationException sul campo «spese».
     *
     * @param list<int> $ids
     * @return Collection<int, PrimaNota>
     */
    public static function speseScelte(Socio $socio, array $ids, bool $lock = false): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (! $ids) {
            throw ValidationException::withMessages(['spese' => 'Scegli almeno una spesa.']);
        }
        $q = PrimaNota::with(['categoria:id,nome,codice,codice_dm39', 'documenti:id,prima_nota_id,nome', 'scontrino.file'])
            ->whereIn('id', $ids)->orderBy('data')->orderBy('id');
        $spese = $lock ? $q->lockForUpdate()->get() : $q->get();

        $errore = match (true) {
            $spese->count() !== count($ids) => 'Una delle spese scelte non c\'è più: ricarica la pagina.',
            $spese->contains(fn ($m) => ! $m->isAnticipoAperto()) => 'Una delle spese scelte non è più da rimborsare: ricarica la pagina.',
            $spese->contains(fn ($m) => $m->nota_rimborso_id !== null) => 'Una delle spese scelte è già in una nota: ricarica la pagina.',
            $spese->contains(fn ($m) => (int) $m->anticipante_socio_id !== (int) $socio->id) => 'Le spese di una nota devono essere tutte dello stesso socio.',
            $spese->contains(fn ($m) => $m->categoria?->codice === PrimaNotaCategoria::DA_CLASSIFICARE || ! $m->categoria_id)
                => 'Prima va scelta la categoria: una spesa «da classificare» non va in una nota.',
            default => null,
        };
        if ($errore) {
            throw ValidationException::withMessages(['spese' => $errore]);
        }

        return $spese;
    }

    // ----------------------------------------------------------------
    // Passo 1: la nota (o la dichiarazione di rinuncia)
    // ----------------------------------------------------------------

    /**
     * @param list<int> $ids spese della nota
     * @param string $tipo rimborso | rinuncia
     */
    public function prepara(Socio $socio, array $ids, string $tipo, string $dataNota, ?string $modalita, ?string $iban, ?string $note, User $utente): NotaRimborso
    {
        $data = Carbon::parse($dataNota)->startOfDay();
        $anno = (int) $data->year;

        $nota = DB::transaction(function () use ($socio, $ids, $tipo, $data, $anno, $modalita, $iban, $note, $utente) {
            $spese = self::speseScelte($socio, $ids, true);
            $ultima = $spese->max(fn ($m) => $m->dataSpesa()->toDateString());
            if ($data->toDateString() < $ultima) {
                throw ValidationException::withMessages(['data_nota' => 'La data della nota non può venire prima della spesa più recente (' . Carbon::parse($ultima)->format('d/m/Y') . ').']);
            }

            // Numero: lock sulle note dell'anno, vincolo unico come rete (difetto 1)
            $progressivo = (int) NotaRimborso::where('anno', $anno)->lockForUpdate()->max('progressivo') + 1;
            $totale = $spese->sum(fn ($m) => Importo::centesimi($m->importo));

            $nota = new NotaRimborso([
                'numero' => sprintf('RIMB-%d-%03d', $anno, $progressivo),
                'anno' => $anno,
                'progressivo' => $progressivo,
                'tipo' => $tipo,
                'socio_id' => $socio->id,
                'data_nota' => $data->toDateString(),
                'modalita' => $tipo === 'rinuncia' ? null : $modalita,
                'iban' => $tipo === 'rimborso' && $modalita === 'banca' ? $iban : null,
                'note' => $note ?: null,
                'totale' => Importo::daCentesimi($totale),
                'numero_spese' => $spese->count(),
                'dati' => self::fotografia($socio, $spese),
                'stato' => 'emessa',
            ]);
            $nota->forceFill(['preparata_da' => $utente->id])->save();

            PrimaNota::withoutEvents(fn () => PrimaNota::whereIn('id', $spese->pluck('id'))->update(['nota_rimborso_id' => $nota->id]));

            $this->audit($nota, $tipo === 'rinuncia' ? 'RINUNCIA_RIMBORSO_EMESSA' : 'NOTA_RIMBORSO_EMESSA', 'MEDIUM',
                ($tipo === 'rinuncia' ? 'Rinuncia al rimborso ' : 'Nota di rimborso ') . "{$nota->numero} del {$data->format('d/m/Y')}: "
                . self::spese($spese->count()) . ', ' . Importo::it($nota->totale, true)
                . ($tipo === 'rimborso' ? ', ' . mb_strtolower(NotaRimborso::MODALITA[$modalita]) : ', donazione alla Compagnia') . '.',
                null, ['socio_id' => $socio->id, 'movimenti' => $spese->pluck('id')->all(), 'totale' => $nota->totale]);

            return $nota;
        });

        // PDF salvato una volta (difetto 6): se qui qualcosa va storto si
        // rifà dalla fotografia alla prima apertura.
        try {
            $this->salvaPdf($nota);
        } catch (\Throwable $e) {
            report($e);
        }

        return $nota;
    }

    /** Fotografia dei dati della nota, da cui si fa sempre lo stesso PDF. */
    public static function fotografia(Socio $socio, Collection $spese): array
    {
        return [
            'socio' => [
                'nome' => $socio->nome,
                'cognome' => $socio->cognome,
                'codice_fiscale' => $socio->codice_fiscale,
                'carica' => self::caricaDi($socio) ?? 'Socio',
            ],
            'visto' => self::visto($socio),
            'righe' => $spese->map(fn (PrimaNota $m) => [
                'movimento_id' => $m->id,
                'data' => $m->dataSpesa()->toDateString(),
                'descrizione' => $m->descrizione,
                'categoria' => $m->categoria?->nome,
                'codice_riga' => $m->categoria?->codice_dm39,
                'fornitore' => $m->soggetto,
                'documento' => self::documento($m),
                'importo' => (string) $m->importo,
                'giustificativi' => $m->documenti->count() + ($m->scontrino?->file->count() ?? 0),
            ])->values()->all(),
            'generata_il' => now()->format('Y-m-d H:i'),
        ];
    }

    /** «scontrino SC2027-012», «fattura 15 del 03/02/2027», «scontrino». */
    public static function documento(PrimaNota $m): string
    {
        $tipo = mb_strtolower(PrimaNota::TIPI_DOCUMENTO[$m->tipo_documento] ?? '');
        $testo = trim(implode(' ', array_filter([$tipo ?: null, $m->numero_documento,
            $m->data_documento && $m->data_documento->toDateString() !== $m->dataSpesa()->toDateString() ? 'del ' . $m->data_documento->format('d/m/Y') : null])));

        return $testo !== '' ? $testo : (($m->documenti->count() + ($m->scontrino?->file->count() ?? 0)) > 0 ? 'giustificativo' : '—');
    }

    // ----------------------------------------------------------------
    // Passo 2: la copia firmata
    // ----------------------------------------------------------------

    public function caricaFirmata(NotaRimborso $nota, UploadedFile $file, User $utente): void
    {
        $ext = strtolower((string) $file->guessExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $cartella = self::CARTELLA . '/' . $nota->anno;
        $n = 0;
        do {
            $n++;
            $path = "{$cartella}/{$nota->numero}-firmata" . ($n > 1 ? sprintf('-%02d', $n) : '') . ".{$ext}";
        } while (Storage::disk('local')->exists($path));
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        try {
            $precedente = DB::transaction(function () use ($nota, $path, $utente) {
                $n = NotaRimborso::lockForUpdate()->findOrFail($nota->id);
                if (! in_array($n->stato, ['emessa', 'firmata'], true)) {
                    throw ValidationException::withMessages(['firmata' => 'La nota è ' . mb_strtolower($n->statoTesto()) . ': la copia firmata non si cambia più.']);
                }
                $precedente = $n->firmata_path;
                $n->forceFill([
                    'firmata_path' => $path,
                    'firmata_nome' => basename($path),
                    'firmata_il' => now(),
                    'firmata_da' => $utente->id,
                    'stato' => 'firmata',
                ])->save();
                $this->audit($n, $n->isRinuncia() ? 'RINUNCIA_RIMBORSO_FIRMATA' : 'NOTA_RIMBORSO_FIRMATA', $precedente ? 'MEDIUM' : 'LOW',
                    ($precedente ? 'Sostituita la copia firmata della ' : 'Caricata la copia firmata della ') . ($n->isRinuncia() ? 'rinuncia ' : 'nota ') . "{$n->numero}.",
                    $precedente ? ['file' => basename($precedente)] : null, ['file' => basename($path)]);

                return $precedente;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        if ($precedente && $precedente !== $path) {
            Storage::disk('local')->delete($precedente);
        }
        $nota->refresh();
    }

    // ----------------------------------------------------------------
    // Passo 3: il pagamento (o la registrazione della donazione)
    // ----------------------------------------------------------------

    public function registraPagamento(NotaRimborso $nota, string $dataPagamento, User $utente): void
    {
        $data = Carbon::parse($dataPagamento)->startOfDay();
        $anno = (int) $data->year;

        DB::transaction(function () use ($nota, $data, $anno, $utente) {
            $n = NotaRimborso::lockForUpdate()->findOrFail($nota->id);
            $campo = $n->isRinuncia() ? 'data_rinuncia' : 'data_pagamento';
            if ($n->stato !== 'firmata') {
                throw ValidationException::withMessages([$campo => $n->stato === 'emessa'
                    ? 'Prima va caricata la copia firmata (passo 2).'
                    : 'La nota è ' . mb_strtolower($n->statoTesto()) . ': il pagamento non si registra.']);
            }
            if ($data->lt($n->data_nota)) {
                throw ValidationException::withMessages([$campo => 'La data non può venire prima della nota (' . $n->data_nota->format('d/m/Y') . ').']);
            }
            if ($data->gt(today())) {
                throw ValidationException::withMessages([$campo => 'La data non può essere nel futuro.']);
            }
            $avvio = RinnoviQuota::annoAvvio();
            if ($anno < $avvio) {
                throw ValidationException::withMessages([$campo => "La contabilità nel gestionale parte dal {$avvio}: il pagamento del {$anno} va registrato nel vecchio gestionale."]);
            }
            if ($this->anni->chiuso($anno)) {
                throw ValidationException::withMessages([$campo => "L'anno {$anno} è chiuso: il pagamento non può cadere lì."]);
            }

            $conto = $n->contoPrevisto();
            $spese = $n->spese()->lockForUpdate()->get();
            if ($spese->isEmpty() || $spese->contains(fn ($m) => $m->rimborsato_il !== null)) {
                throw ValidationException::withMessages([$campo => 'Le spese della nota non tornano: ricarica la pagina.']);
            }

            $this->anni->assicura($anno);
            PrimaNota::withoutEvents(function () use ($spese, $data, $conto, $n, $utente) {
                foreach ($spese as $m) {
                    $m->forceFill([
                        'data_spesa' => $m->dataSpesa()->toDateString(),
                        'data' => $data->toDateString(),
                        'conto' => $conto,
                        'rimborsato_il' => $data->toDateString(),
                    ])->save();
                }
                if ($n->isRinuncia()) {
                    $categoria = PrimaNotaCategoria::where('codice', PrimaNotaCategoria::DONAZIONI)->first()
                        ?? throw ValidationException::withMessages(['data_rinuncia' => 'Manca la categoria «Contributi e donazioni da terzi» (codice DONAZIONI): rilanciare PrimaNotaCategoriaSeeder.']);
                    $entrata = new PrimaNota();
                    $entrata->forceFill([
                        'tipo' => 'entrata',
                        'conto' => 'cassa',
                        'data' => $data->toDateString(),
                        'importo' => $n->totale,
                        'categoria_id' => $categoria->id,
                        'descrizione' => mb_substr('Donazione di ' . $n->socioNome() . " (rinuncia {$n->numero})", 0, 255),
                        'socio_id' => $n->socio_id,
                        'tipo_documento' => 'altro',
                        'numero_documento' => $n->numero,
                        'data_documento' => $n->data_nota->toDateString(),
                        'nota_rimborso_id' => $n->id,
                        'registrato_da' => $utente->id,
                    ])->save();
                }
            });

            $n->forceFill([
                'stato' => 'pagata',
                'pagata_il' => $data->toDateString(),
                'conto' => $conto,
                'pagamento_registrato_il' => now(),
                'pagata_da' => $utente->id,
            ])->save();

            $this->audit($n, $n->isRinuncia() ? 'RINUNCIA_RIMBORSO_REGISTRATA' : 'NOTA_RIMBORSO_PAGATA', 'HIGH',
                $n->isRinuncia()
                    ? "Rinuncia {$n->numero} registrata il {$data->format('d/m/Y')}: " . self::spese($spese->count()) . ' e la donazione di ' . Importo::it($n->totale, true) . ' in cassa.'
                    : "Nota {$n->numero} pagata il {$data->format('d/m/Y')} dalla " . mb_strtolower(PrimaNota::CONTI[$conto]) . ': ' . self::spese($spese->count()) . ', ' . Importo::it($n->totale, true) . '.',
                null, ['pagata_il' => $data->toDateString(), 'conto' => $conto, 'movimenti' => $spese->pluck('id')->all()]);
        });
        $nota->refresh();
    }

    /** Pagamento registrato per errore: le spese escono dai saldi, la nota torna «firmata». */
    public function annullaPagamento(NotaRimborso $nota, string $motivo, User $utente): void
    {
        DB::transaction(function () use ($nota, $motivo) {
            $n = NotaRimborso::lockForUpdate()->findOrFail($nota->id);
            if ($n->stato !== 'pagata') {
                throw ValidationException::withMessages(['motivazione' => 'Il pagamento di questa nota non è registrato.']);
            }
            $anno = (int) $n->pagata_il->year;
            $chiuso = $this->anni->chiuso($anno);
            $prima = ['pagata_il' => $n->pagata_il->toDateString(), 'conto' => $n->conto];

            $spese = $n->spese()->lockForUpdate()->get();
            $donazione = $n->movimenti()->where('sospeso', false)->lockForUpdate()->get();
            PrimaNota::withoutEvents(function () use ($spese, $donazione) {
                foreach ($spese as $m) {
                    $m->forceFill([
                        'data' => $m->dataSpesa()->toDateString(),
                        'conto' => null,
                        'rimborsato_il' => null,
                    ])->save();
                }
                foreach ($donazione as $d) {
                    $d->delete();
                }
            });

            $n->forceFill(['stato' => 'firmata', 'pagata_il' => null, 'conto' => null, 'pagamento_registrato_il' => null, 'pagata_da' => null])->save();

            $this->audit($n, $n->isRinuncia() ? 'RINUNCIA_RIMBORSO_REGISTRAZIONE_ANNULLATA' : 'NOTA_RIMBORSO_PAGAMENTO_ANNULLATO', 'HIGH',
                ($n->isRinuncia() ? "Annullata la registrazione della rinuncia {$n->numero}" : "Annullato il pagamento della nota {$n->numero}")
                . " del {$prima['pagata_il']}: le spese tornano da rimborsare"
                . ($donazione->isNotEmpty() ? ' e l\'entrata della donazione si toglie' : '')
                . ($chiuso ? "; anno {$anno} chiuso, saldi finali aggiornati" : '') . '.',
                $prima, ['motivazione' => $motivo, 'movimenti' => $spese->pluck('id')->all(), 'tolti' => $donazione->pluck('id')->all()]);

            if ($chiuso) {
                $this->anni->aggiornaChiusura($anno);
            }
        });
        $nota->refresh();
    }

    // ----------------------------------------------------------------
    // Annullamento della nota
    // ----------------------------------------------------------------

    /** Nota emessa o firmata: le spese tornano «senza nota», il numero resta (annullata). */
    public function annulla(NotaRimborso $nota, string $motivo, User $utente): void
    {
        DB::transaction(function () use ($nota, $motivo, $utente) {
            $n = NotaRimborso::lockForUpdate()->findOrFail($nota->id);
            if (! $n->annullabile()) {
                throw ValidationException::withMessages(['motivazione' => $n->isPagata()
                    ? 'La nota è pagata: prima va annullato il pagamento.'
                    : 'La nota è già annullata.']);
            }
            $ids = $n->spese()->lockForUpdate()->pluck('id')->all();
            PrimaNota::withoutEvents(fn () => PrimaNota::whereIn('id', $ids)->update(['nota_rimborso_id' => null]));
            $statoPrima = $n->stato;
            $n->forceFill([
                'stato' => 'annullata',
                'annullata_il' => now(),
                'annullata_da' => $utente->id,
                'motivo_annullamento' => $motivo,
            ])->save();

            $this->audit($n, $n->isRinuncia() ? 'RINUNCIA_RIMBORSO_ANNULLATA' : 'NOTA_RIMBORSO_ANNULLATA', 'HIGH',
                ($n->isRinuncia() ? 'Rinuncia ' : 'Nota di rimborso ') . "{$n->numero} annullata: " . self::spese(count($ids))
                . ' di nuovo da rimborsare; il numero non si riusa.',
                ['stato' => $statoPrima], ['stato' => 'annullata', 'motivazione' => $motivo, 'movimenti' => $ids]);
        });
        $nota->refresh();

        // Il PDF resta consultabile, con la scritta ANNULLATA
        try {
            $this->salvaPdf($nota);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ----------------------------------------------------------------
    // PDF
    // ----------------------------------------------------------------

    public function pdf(NotaRimborso $nota): string
    {
        return Pdf::loadView('pdf.nota-rimborso', [
            'n' => $nota,
            'dataDocumento' => $nota->data_nota,
        ])->setPaper('a4')->output();
    }

    public function salvaPdf(NotaRimborso $nota): string
    {
        $pdf = $this->pdf($nota);
        $path = self::CARTELLA . "/{$nota->anno}/{$nota->numero}.pdf";
        Storage::disk('local')->put($path, $pdf);
        if ($nota->pdf_path !== $path) {
            $nota->forceFill(['pdf_path' => $path])->save();
        }

        return $pdf;
    }

    /** Il PDF salvato; se manca si rifà dalla fotografia. */
    public function contenutoPdf(NotaRimborso $nota): string
    {
        return $nota->pdf_path && Storage::disk('local')->exists($nota->pdf_path)
            ? Storage::disk('local')->get($nota->pdf_path)
            : $this->salvaPdf($nota);
    }

    // ----------------------------------------------------------------
    // Supporto
    // ----------------------------------------------------------------

    public static function spese(int $n): string
    {
        return $n . ' ' . ($n === 1 ? 'spesa' : 'spese');
    }

    /** IBAN valido (lunghezza, paese, cifre di controllo mod 97). */
    public static function ibanValido(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));
        if (! preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban) || (str_starts_with($iban, 'IT') && strlen($iban) !== 27)) {
            return false;
        }
        $numero = '';
        foreach (str_split(substr($iban, 4) . substr($iban, 0, 4)) as $c) {
            $numero .= ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
        }
        $resto = 0;
        foreach (str_split($numero, 7) as $pezzo) {
            $resto = (int) ($resto . $pezzo) % 97;
        }

        return $resto === 1;
    }

    /**
     * Una riga dell'Audit Log per operazione. Il dettaglio (che entra
     * nell'hash) non ha dati personali: il socio sta nei valori.
     */
    private function audit(NotaRimborso $n, string $evento, string $rischio, string $dettaglio, ?array $prima, ?array $dopo): void
    {
        AuditLogService::scrivi([
            'evento' => $evento,
            'entita_tipo' => 'NotaRimborso',
            'entita_id' => $n->id,
            'anno' => $n->anno,
            'livello_rischio' => $rischio,
            'dettaglio' => $dettaglio,
            'old_values' => $prima,
            'new_values' => $dopo,
        ]);
    }
}
