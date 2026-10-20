<?php

namespace App\Services;

use App\Models\Verbale;
use App\Models\VerbaleRettifica;
use App\Models\User;
use App\Support\FirmaDigitale;
use App\Support\HtmlSanitizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Libri dei verbali (v0.44.0, specifiche/verbali.md, tavole VB-1, VB-3,
 * VB-4, VB-5). Ripreso dal Gestionale APS del Dante (VerbaliController:
 * approva, archivia, download) con i difetti corretti
 * (analisi-verbali-assemblee.md §3):
 *
 * - numero nel libro e nell'anno, con lock e vincolo unico (difetto 1, V1);
 * - dal definitivo il verbale non cambia più: il PDF si salva una volta,
 *   con l'impronta; gli errori si correggono con la rettifica (difetto 2);
 * - la copia firmata richiede la firma digitale (V2) e le regole delle
 *   firme della V14;
 * - un evento dell'Audit Log per passo, senza nomi né motivi nel
 *   dettaglio (difetto 12);
 * - il blocco della contabilità non sta qui: lo fa la chiusura dell'anno
 *   contabile, che sceglie il verbale (difetto 13);
 * - niente fusione dei PDF: i documenti si scaricano a parte (difetto 14).
 */
class Verbali
{
    public const MAX_KB_FIRMATO = 10240;

    public function __construct(private DocumentiOrgani $documenti)
    {
    }

    // ----------------------------------------------------------------
    // Numero
    // ----------------------------------------------------------------

    public static function prossimoNumero(string $libro, int $anno): int
    {
        return (int) Verbale::where('libro', $libro)->where('anno_libro', $anno)->max('numero_libro') + 1;
    }

    /** Numero con il lock sulle righe del libro dell'anno (dentro una transazione). */
    private static function numeroBloccato(string $libro, int $anno): int
    {
        return (int) Verbale::where('libro', $libro)->where('anno_libro', $anno)->lockForUpdate()->max('numero_libro') + 1;
    }

    // ----------------------------------------------------------------
    // Definitivo
    // ----------------------------------------------------------------

    /**
     * Controlli prima del definitivo: messaggi che bloccano (vuoto = si può).
     * @return list<string>
     */
    public static function mancanze(Verbale $v): array
    {
        $m = [];
        if (! $v->data_seduta) {
            $m[] = 'Manca la data della seduta.';
        }
        if ($v->tipo === 'cd_ammissione' && $v->domanda?->is_test) {
            $m[] = 'È il verbale di una domanda di prova: non entra nel libro.';
        } elseif ($v->tipo === 'cd_ammissione' && ! $v->ammissioneChiusa()) {
            $m[] = 'La domanda non è ancora decisa: il verbale si chiude con la decisione.';
        }
        if ($v->tipo === 'cd_riunione') {
            if (! $v->presidente_assemblea || ! $v->segretario_verbalizzante) {
                $m[] = 'Mancano il presidente e il segretario della seduta (passo «Presenze»).';
            }
            $presenti = $v->seduta?->presenze->filter->presente()->count() ?? 0;
            foreach ($v->punti as $p) {
                if (! $p->ha_delibera) {
                    if (HtmlSanitizer::vuoto($p->discussione_html)) {
                        $m[] = "Punto {$p->ordine}: scrivi la discussione (anche solo «nessun intervento») o la delibera.";
                    }
                    continue;
                }
                if (HtmlSanitizer::vuoto($p->delibera_html)) {
                    $m[] = "Punto {$p->ordine}: manca il testo della delibera.";
                }
                if ($p->voti->whereNotNull('voto')->count() < $presenti || ! $p->esito) {
                    $m[] = "Punto {$p->ordine}: mancano dei voti.";
                }
            }
        }

        return $m;
    }

    public function rendiDefinitivo(Verbale $verbale, User $utente): Verbale
    {
        $verbale->loadMissing(['punti.voti', 'seduta.presenze', 'domanda']);
        if (! $verbale->isBozza()) {
            throw ValidationException::withMessages(['verbale' => 'Il verbale è già definitivo.']);
        }
        if ($m = self::mancanze($verbale)) {
            throw ValidationException::withMessages(['verbale' => $m[0]]);
        }

        $path = null;
        try {
            $verbale = DB::transaction(function () use ($verbale, $utente, &$path) {
                $v = Verbale::lockForUpdate()->findOrFail($verbale->id);
                if (! $v->isBozza()) {
                    throw ValidationException::withMessages(['verbale' => 'Il verbale è già definitivo.']);
                }
                // Verbali delle domande e delle consultazioni (anche quelli nati
                // prima della v0.44.0): chi firma è il Presidente e il
                // Segretario in carica, se il nome manca.
                if ($v->libro === 'cd' && in_array($v->tipo, ['cd_ammissione', 'cd_consultazione'], true)) {
                    $occupati = ConsiglioDirettivo::occupati();
                    $nome = fn (string $r) => isset($occupati[$r]) && $occupati[$r]->socio ? trim($occupati[$r]->socio->nome . ' ' . $occupati[$r]->socio->cognome) : null;
                    $v->presidente_assemblea = $v->presidente_assemblea ?: $nome('presidente');
                    $v->segretario_verbalizzante = $v->segretario_verbalizzante ?: $nome('segretario');
                }
                $anno = (int) $v->data_seduta->year;
                $numero = self::numeroBloccato($v->libro, $anno);
                $v->forceFill([
                    'anno_libro' => $anno,
                    'numero_libro' => $numero,
                    'stato' => 'definitivo',
                    'definitivo_il' => now(),
                    'definitivo_da' => $utente->id,
                ])->save();

                $pdf = $this->pdf($v->fresh());
                $path = "verbali/{$anno}/" . self::nomeFile($v) . '.pdf';
                Storage::disk('local')->put($path, $pdf);
                $v->forceFill(['pdf_path' => $path, 'pdf_impronta' => hash('sha256', $pdf)])->save();

                $this->audit($v, 'VERBALE_DEFINITIVO', 'HIGH',
                    "Verbale {$v->codice()} reso definitivo: numero assegnato, PDF salvato con l'impronta.",
                    ['stato' => 'bozza'], ['stato' => 'definitivo', 'numero' => $v->numeroTesto(), 'impronta' => $v->pdf_impronta]);

                return $v;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }

        return $verbale;
    }

    /** Verbali delle domande chiusi, resi definitivi insieme nell'ordine di chiusura (V11). @return int quanti */
    public function rendiDefinitiveAmmissioni(User $utente): int
    {
        $verbali = Verbale::with('domanda')->nelLibro()->where('tipo', 'cd_ammissione')->where('stato', 'bozza')->get()
            ->filter->ammissioneChiusa()
            ->sortBy(fn (Verbale $v) => [$v->domanda->votazione_chiusa_il?->timestamp, $v->id]);
        foreach ($verbali as $v) {
            $this->rendiDefinitivo($v, $utente);
        }

        return $verbali->count();
    }

    // ----------------------------------------------------------------
    // Copia firmata
    // ----------------------------------------------------------------

    /**
     * Controlla la firma digitale del file (V2, V14). $dichiarazione:
     * «entrambi» o «segretario» (con una sola firma, solo il verbalizzante).
     *
     * @return array{tipo: string, numero: ?int, dichiarata: string, firmatari: list<string>}
     */
    public static function controllaFirma(UploadedFile $file, ?string $dichiarazione, string $campo = 'firmato'): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['pdf', 'p7m'], true)) {
            throw ValidationException::withMessages([$campo => 'Il file firmato deve essere un PDF con la firma digitale (PAdES) o un .p7m (CAdES).']);
        }
        $tipo = FirmaDigitale::rileva($file->getRealPath(), $ext);
        if (! $tipo) {
            throw ValidationException::withMessages([$campo => 'Il file non contiene una firma digitale: carica il PDF firmato con la firma digitale (CIE o kit di firma) o il file .p7m.']);
        }
        $numero = FirmaDigitale::contaFirme($file->getRealPath(), $ext);
        $firmatari = FirmaDigitale::firmatari($file->getRealPath(), $ext);

        if ($tipo === 'PAdES' && $numero >= 2) {
            $dichiarata = 'entrambi';
        } elseif (in_array($dichiarazione, ['entrambi', 'segretario'], true) && ! ($tipo === 'PAdES' && $dichiarazione === 'entrambi')) {
            $dichiarata = $dichiarazione;
        } else {
            throw ValidationException::withMessages(['dichiarazione' => $tipo === 'PAdES'
                ? 'Nel file c\'è una sola firma digitale: si accetta solo se è del segretario verbalizzante. Confermalo, oppure fai firmare anche il presidente della seduta.'
                : 'In un file .p7m le firme non si contano: dichiara chi ha firmato.']);
        }

        return ['tipo' => $tipo, 'numero' => $numero, 'dichiarata' => $dichiarata, 'firmatari' => $firmatari];
    }

    public function caricaFirmato(Verbale $verbale, UploadedFile $file, ?string $dichiarazione, ?string $motivo, User $utente): void
    {
        $firma = self::controllaFirma($file, $dichiarazione);
        $ext = strtolower($file->getClientOriginalExtension());
        $anno = (int) ($verbale->anno_libro ?: $verbale->data_seduta->year);
        $path = "verbali/{$anno}/" . self::nomeFile($verbale) . '-firmato-' . now()->format('YmdHis') . '.' . $ext;
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

        try {
            $precedente = DB::transaction(function () use ($verbale, $firma, $path, $file, $motivo, $utente) {
                $v = Verbale::lockForUpdate()->findOrFail($verbale->id);
                if ($v->isBozza()) {
                    throw ValidationException::withMessages(['firmato' => 'Prima il verbale va reso definitivo.']);
                }
                $sostituzione = $v->isFirmato();
                if ($sostituzione && mb_strlen(trim((string) $motivo)) < 15) {
                    throw ValidationException::withMessages(['motivo' => 'Per sostituire la copia firmata scrivi il motivo (almeno 15 caratteri).']);
                }
                $precedente = $v->verbale_firmato_path;
                $prima = $sostituzione ? ['firmato' => basename((string) $precedente), 'impronta' => $v->hash_file_verbale] : ['stato' => $v->stato];
                $v->forceFill([
                    'stato' => 'firmato',
                    'verbale_firmato_path' => $path,
                    'firmato_nome' => basename($path),
                    'hash_file_verbale' => hash_file('sha256', Storage::disk('local')->path($path)),
                    'firma_tipo' => $firma['tipo'],
                    'firme_numero' => $firma['numero'],
                    'firma_dichiarata' => $firma['dichiarata'],
                    'firmatari' => $firma['firmatari'] ? mb_substr(implode(', ', $firma['firmatari']), 0, 500) : null,
                    'firmato_il' => now(),
                    'firmato_da' => $utente->id,
                ])->save();

                $this->audit($v, $sostituzione ? 'VERBALE_FIRMATO_SOSTITUITO' : 'VERBALE_FIRMATO', $sostituzione ? 'HIGH' : 'MEDIUM',
                    "Verbale {$v->codice()}: " . ($sostituzione ? 'sostituita la copia firmata' : 'caricata la copia firmata') . " ({$firma['tipo']}).",
                    $prima, ['impronta' => $v->hash_file_verbale, 'firma' => $firma['tipo'], 'firme' => $firma['numero'], 'dichiarata' => $firma['dichiarata']]
                        + ($sostituzione ? ['motivazione' => trim((string) $motivo)] : []));

                return $precedente;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        // La copia precedente si tiene: resta la storia di quello che c'era (Audit Log con l'impronta).
        unset($precedente);
    }

    // ----------------------------------------------------------------
    // Rettifica
    // ----------------------------------------------------------------

    public function rettifica(Verbale $verbale, string $testoHtml, string $motivo, User $utente): VerbaleRettifica
    {
        $testo = HtmlSanitizer::pulisci($testoHtml);
        if (HtmlSanitizer::vuoto($testo)) {
            throw ValidationException::withMessages(['testo_html' => 'Scrivi il testo della rettifica.']);
        }
        $path = null;
        try {
            return DB::transaction(function () use ($verbale, $testo, $motivo, $utente, &$path) {
                $v = Verbale::lockForUpdate()->findOrFail($verbale->id);
                if (! $v->isImmutabile()) {
                    throw ValidationException::withMessages(['testo_html' => 'Un verbale in bozza si corregge direttamente: la rettifica è per i verbali definitivi.']);
                }
                $r = new VerbaleRettifica([
                    'verbale_id' => $v->id,
                    'numero' => (int) $v->rettifiche()->max('numero') + 1,
                    'testo_html' => $testo,
                    'motivo' => trim($motivo),
                ]);
                $r->forceFill(['creata_da' => $utente->id])->save();

                $pdf = Pdf::loadView('pdf.rettifica', ['r' => $r->load('verbale', 'creataDa'), 'dataDocumento' => $r->created_at])->setPaper('a4')->output();
                $path = "verbali/{$v->anno_libro}/" . self::nomeFile($v) . "-rettifica-{$r->numero}.pdf";
                Storage::disk('local')->put($path, $pdf);
                $r->forceFill(['pdf_path' => $path, 'pdf_impronta' => hash('sha256', $pdf)])->save();

                $this->audit($v, 'VERBALE_RETTIFICATO', 'HIGH', "Verbale {$v->codice()}: aggiunta la rettifica n. {$r->numero}.",
                    null, ['rettifica' => $r->numero, 'motivazione' => $r->motivo, 'impronta' => $r->pdf_impronta]);

                return $r;
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    public function caricaFirmataRettifica(VerbaleRettifica $r, UploadedFile $file, User $utente): void
    {
        // Per la rettifica basta che ci sia la firma digitale (una o più).
        $ext = strtolower($file->getClientOriginalExtension());
        $tipo = in_array($ext, ['pdf', 'p7m'], true) ? FirmaDigitale::rileva($file->getRealPath(), $ext) : null;
        if (! $tipo) {
            throw ValidationException::withMessages(['firmato_rettifica' => 'Il file non contiene una firma digitale: carica il PDF firmato (PAdES) o il file .p7m.']);
        }
        $firma = ['tipo' => $tipo, 'numero' => FirmaDigitale::contaFirme($file->getRealPath(), $ext)];
        $v = $r->verbale;
        $path = "verbali/{$v->anno_libro}/" . self::nomeFile($v) . "-rettifica-{$r->numero}-firmata-" . now()->format('YmdHis') . '.' . $ext;
        Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
        try {
            DB::transaction(function () use ($r, $v, $firma, $path) {
                $r->forceFill([
                    'firmato_path' => $path,
                    'firmato_nome' => basename($path),
                    'firmato_impronta' => hash_file('sha256', Storage::disk('local')->path($path)),
                    'firma_tipo' => $firma['tipo'],
                    'firme_numero' => $firma['numero'],
                    'firmato_il' => now(),
                ])->save();
                $this->audit($v, 'VERBALE_RETTIFICA_FIRMATA', 'MEDIUM', "Verbale {$v->codice()}: caricata la copia firmata della rettifica n. {$r->numero}.",
                    null, ['impronta' => $r->firmato_impronta, 'firma' => $firma['tipo']]);
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // Registrato da fuori (VB-5, V13, V15)
    // ----------------------------------------------------------------

    /**
     * @param array{libro: string, organo: string, data: string, presidente: string, segretario: string, presenti: ?int,
     *              titolo: string, delibere_html: string, numero: ?int, approva_rendiconto_anno: ?int, dichiarazione: ?string} $d
     * @param array<int, UploadedFile>|null $documenti
     */
    public function registraDaFuori(array $d, UploadedFile $file, ?array $documenti, User $utente): Verbale
    {
        $firma = self::controllaFirma($file, $d['dichiarazione'] ?? null);
        $ext = strtolower($file->getClientOriginalExtension());
        $data = Carbon::parse($d['data']);
        $anno = (int) $data->year;
        $testo = HtmlSanitizer::pulisci($d['delibere_html']);
        if (HtmlSanitizer::vuoto($testo)) {
            throw ValidationException::withMessages(['delibere_html' => 'Scrivi in breve le delibere.']);
        }

        $scritti = [];
        try {
            return DB::transaction(function () use ($d, $file, $documenti, $utente, $firma, $ext, $data, $anno, $testo, &$scritti) {
                $libero = self::numeroBloccato($d['libro'], $anno);
                $numero = $d['numero'] ?: $libero;
                if (Verbale::where('libro', $d['libro'])->where('anno_libro', $anno)->where('numero_libro', $numero)->exists()) {
                    throw ValidationException::withMessages(['numero' => "Il numero {$anno}/{$numero} è già usato nel libro: il primo libero è {$libero}."]);
                }

                $v = new Verbale([
                    'tipo' => $d['libro'] === 'cd' ? 'cd_riunione' : 'assemblea',
                    'libro' => $d['libro'],
                    'organo' => $d['libro'] === 'cd' ? 'cd' : $d['organo'],
                    'titolo' => trim($d['titolo']),
                    'presidente_assemblea' => trim($d['presidente']),
                    'segretario_verbalizzante' => trim($d['segretario']),
                    'soci_presenti' => (int) ($d['presenti'] ?? 0),
                    'data_seduta' => $data->toDateString(),
                    'corpo_html' => $testo,
                    'approva_rendiconto_anno' => $d['libro'] === 'assemblee' ? ($d['approva_rendiconto_anno'] ?? null) : null,
                ]);
                $v->forceFill([
                    'da_fuori' => true,
                    'anno_libro' => $anno,
                    'numero_libro' => $numero,
                    'stato' => 'firmato',
                    'redatto_da' => $utente->id,
                    'definitivo_il' => now(),
                    'definitivo_da' => $utente->id,
                ])->save();

                $path = "verbali/{$anno}/" . self::nomeFile($v) . '-firmato.' . $ext;
                Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));
                $scritti[] = $path;
                $v->forceFill([
                    'verbale_firmato_path' => $path,
                    'firmato_nome' => basename($path),
                    'hash_file_verbale' => hash_file('sha256', Storage::disk('local')->path($path)),
                    'firma_tipo' => $firma['tipo'],
                    'firme_numero' => $firma['numero'],
                    'firma_dichiarata' => $firma['dichiarata'],
                    'firmatari' => $firma['firmatari'] ? mb_substr(implode(', ', $firma['firmatari']), 0, 500) : null,
                    'firmato_il' => now(),
                    'firmato_da' => $utente->id,
                ])->save();
                $scritti = array_merge($scritti, $this->documenti->salva('verbale', $v->id, $documenti, $utente));

                $this->audit($v, 'VERBALE_REGISTRATO_DA_FUORI', 'HIGH',
                    "Verbale {$v->codice()} registrato da fuori, con la copia firmata ({$firma['tipo']}).",
                    null, ['numero' => $v->numeroTesto(), 'impronta' => $v->hash_file_verbale, 'rendiconto' => $v->approva_rendiconto_anno]);

                return $v;
            });
        } catch (\Throwable $e) {
            DocumentiOrgani::cancellaFile($scritti);
            throw $e;
        }
    }

    // ----------------------------------------------------------------
    // PDF
    // ----------------------------------------------------------------

    public function pdf(Verbale $v): string
    {
        $v->loadMissing(['punti.voti', 'seduta.presenze', 'consultazione.voti', 'domanda']);

        return Pdf::loadView('pdf.verbale', [
            'v' => $v,
            'documenti' => $v->documenti(),
            'dataDocumento' => $v->data_seduta,
        ])->setPaper('a4')->output();
    }

    /** «CD-2027-5», o «CD-bozza-12» senza numero. */
    public static function nomeFile(Verbale $v): string
    {
        return ($v->libro === 'cd' ? 'CD' : 'ASS') . '-' . ($v->numero_libro ? $v->anno_libro . '-' . $v->numero_libro : 'bozza-' . $v->id);
    }

    // ----------------------------------------------------------------
    // Audit
    // ----------------------------------------------------------------

    public function audit(Verbale $v, string $evento, string $rischio, string $dettaglio, ?array $prima, ?array $dopo): void
    {
        AuditLogService::scrivi([
            'evento' => $evento,
            'entita_tipo' => 'Verbale',
            'entita_id' => $v->id,
            'anno' => $v->anno_libro ?: $v->data_seduta?->year,
            'livello_rischio' => $rischio,
            'dettaglio' => $dettaglio,
            'old_values' => $prima,
            'new_values' => $dopo,
        ]);
    }
}
