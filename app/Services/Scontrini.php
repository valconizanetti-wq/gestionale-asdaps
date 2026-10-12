<?php

namespace App\Services;

use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\PrimaNotaScontrino;
use App\Models\PrimaNotaScontrinoFile;
use App\Models\PrimaNotaScontrinoRiga;
use App\Models\Soggetto;
use App\Models\User;
use App\Scontrini\LettoreScontrino;
use App\Support\Importo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Scontrini dal telefono (specifiche/scontrini.md, tavole SN-1…SN-8).
 *
 * - registra(): passo 2 confermato. In una transazione: fornitore (scelto,
 *   con la partita IVA letta aggiunta, o nuovo con i dati corretti),
 *   scontrino numerato per anno, foto archiviate con il nome codificato,
 *   un movimento su «Uscite da classificare» per il totale, le righe;
 * - salvaDati(): Presidente o Tesoriere correggono totale, data, fornitore
 *   e conto; data, conto e fornitore passano ai movimenti;
 * - dividi(): «Registra la divisione», un movimento per categoria;
 * - aggiungiFoto(), elimina().
 * I file si copiano nell'archivio dentro la transazione e si tolgono se
 * questa fallisce; quelli da cancellare si cancellano dopo.
 */
class Scontrini
{
    /** @var string[] file scritti nell'archivio durante la transazione in corso */
    private array $scritti = [];

    public function __construct(private AnniContabili $anni)
    {
    }

    // ----------------------------------------------------------------
    // Registrazione dal telefono (passo 2)
    // ----------------------------------------------------------------

    /**
     * @param array{path: string, ext: string, originale: string, ridotta: ?string, ocr: array, letto: ?array} $bozza
     * @param array{importo: string, data: string, conto: string, nota: string, fornitore_id: ?int,
     *              nuovo: ?array{nome: string, partita_iva: ?string, indirizzo: ?string, comune: ?string},
     *              aggiungi_piva: ?string} $dati
     */
    public function registra(array $bozza, array $dati, User $utente): PrimaNotaScontrino
    {
        $anno = (int) substr($dati['data'], 0, 4);
        $categoria = PrimaNotaCategoria::where('codice', PrimaNotaCategoria::DA_CLASSIFICARE)->firstOrFail();

        return $this->inSicurezza(function () use ($bozza, $dati, $utente, $anno, $categoria) {
            $this->anni->assicura($anno);
            $soggetto = $this->fornitore($dati);

            $numero = (int) PrimaNotaScontrino::where('anno', $anno)->lockForUpdate()->max('numero') + 1;
            $letto = $bozza['letto'] ?? [];

            $s = new PrimaNotaScontrino([
                'numero' => $numero,
                'anno' => $anno,
                'data_scontrino' => $dati['data'],
                'importo_totale' => $dati['importo'],
                'conto' => $dati['conto'],
                'nota' => $dati['nota'],
                'soggetto_id' => $soggetto->id,
                'ragione_sociale_raw' => $letto['fornitore'] ?? null,
                'partita_iva' => $letto['partita_iva'] ?? null,
                'ocr_utilizzato' => ($bozza['ocr']['esito'] ?? null) === 'letto',
                'ocr_testo_grezzo' => $bozza['ocr']['testo'] ?? null,
                'ocr_esito' => $bozza['ocr']['esito'] ?? null,
                'ocr_ms' => $bozza['ocr']['ms'] ?? null,
                'righe_lette' => $letto['righe'] ?? null,
                'allegato_originale' => mb_substr($bozza['originale'] ?? '', 0, 255) ?: null,
            ]);
            $s->forceFill(['registrato_da' => $utente->id, 'stato' => 'da_verificare'])->save();

            // Foto: originale, anteprima (copia ridotta), miniatura
            $file = $this->archivia($s, $bozza['path'], $bozza['ext'], $bozza['originale'] ?? null, $utente);
            $anteprime = $this->anteprime($s, $bozza['ridotta'] ?? null);
            $s->forceFill(['allegato_path' => $file->path] + $anteprime);
            PrimaNotaScontrino::withoutEvents(fn () => $s->save());

            $m = new PrimaNota();
            $m->fill([
                'tipo' => 'uscita',
                'conto' => $dati['conto'],
                'data' => $dati['data'],
                'importo' => $dati['importo'],
                'categoria_id' => $categoria->id,
                'descrizione' => mb_substr($dati['nota'], 0, 255),
                'soggetto' => $soggetto->nome,
                'tipo_documento' => 'scontrino',
                'numero_documento' => $s->codice(),
                'data_documento' => $dati['data'],
                'scontrino_id' => $s->id,
            ])->forceFill(['registrato_da' => $utente->id])->save();

            // Righe: quelle lette se tornano col totale, altrimenti una sola
            // con il totale (analisi §12); il Tesoriere divide dalla scheda.
            $righe = $letto['righe'] ?? [];
            $somma = $letto['righe_somma'] ?? null;
            if (! $righe || $somma === null || Importo::centesimi($somma) !== Importo::centesimi($dati['importo'])) {
                $righe = [['descrizione' => $dati['nota'], 'importo' => $dati['importo']]];
            }
            foreach (array_values($righe) as $i => $r) {
                PrimaNotaScontrinoRiga::create([
                    'scontrino_id' => $s->id,
                    'ordine' => $i + 1,
                    'descrizione' => mb_substr(trim($r['descrizione']) ?: 'Riga', 0, 200),
                    'importo' => $r['importo'],
                    'prima_nota_id' => $m->id,
                ]);
            }

            $s->ricalcolaStato();

            return $s;
        });
    }

    /**
     * Fornitore del passo 2: quello scelto (con la partita IVA letta, se
     * non l'aveva) o uno nuovo con i dati corretti. Una partita IVA già di
     * un altro fornitore si segnala invece di creare un doppione.
     */
    private function fornitore(array $dati): Soggetto
    {
        if (! empty($dati['fornitore_id'])) {
            $s = Soggetto::lockForUpdate()->findOrFail($dati['fornitore_id']);
            $piva = $dati['aggiungi_piva'] ?? null;
            if ($piva && ! $s->partita_iva && LettoreScontrino::partitaIvaValida($piva)
                && ! Soggetto::where('partita_iva', $piva)->exists()) {
                $s->update(['partita_iva' => $piva]);
            }
            if (! $s->attivo) {
                $s->update(['attivo' => true]);
            }

            return $s;
        }

        $n = $dati['nuovo'];
        $nome = trim(preg_replace('/\s+/u', ' ', $n['nome']));
        $piva = $n['partita_iva'] ?: null;
        if ($piva && ($altro = Soggetto::where('partita_iva', $piva)->first())) {
            throw ValidationException::withMessages([
                'nuovo_partita_iva' => "La partita IVA {$piva} è già di «{$altro->nome}»: sceglilo dalla lista.",
            ]);
        }
        $stesso = Soggetto::whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])->lockForUpdate()->first();
        if ($stesso) {
            if ($piva && $stesso->partita_iva && $stesso->partita_iva !== $piva) {
                throw ValidationException::withMessages([
                    'nuovo_nome' => "C'è già «{$stesso->nome}» con un'altra partita IVA: sceglilo dalla lista o cambia il nome.",
                ]);
            }
            $stesso->fill(array_filter([
                'partita_iva' => $stesso->partita_iva ?: $piva,
                'indirizzo' => $stesso->indirizzo ?: ($n['indirizzo'] ?? null),
                'comune' => $stesso->comune ?: ($n['comune'] ?? null),
            ]) + ['attivo' => true])->save();

            return $stesso;
        }

        return Soggetto::create([
            'nome' => mb_substr($nome, 0, 200),
            'partita_iva' => $piva,
            'indirizzo' => ($n['indirizzo'] ?? null) ?: null,
            'comune' => ($n['comune'] ?? null) ?: null,
            'attivo' => true,
        ]);
    }

    // ----------------------------------------------------------------
    // Scheda (Presidente e Tesoriere)
    // ----------------------------------------------------------------

    /**
     * Totale, data, fornitore e conto. Data, conto e fornitore passano a
     * tutti i movimenti; il totale al movimento solo se è uno (altrimenti lo
     * scontrino diventa «da quadrare» finché non si rifà la divisione).
     *
     * @param array{importo: string, data: string, conto: string, soggetto_id: int, motivazione: ?string} $dati
     */
    public function salvaDati(PrimaNotaScontrino $s, array $dati): void
    {
        $chiuso = $this->anni->chiuso($s->anno);
        DB::transaction(function () use ($s, $dati, $chiuso) {
            $s = PrimaNotaScontrino::lockForUpdate()->findOrFail($s->id);
            $soggetto = Soggetto::findOrFail($dati['soggetto_id']);
            $s->fill([
                'importo_totale' => $dati['importo'],
                'data_scontrino' => $dati['data'],
                'conto' => $dati['conto'],
                'soggetto_id' => $soggetto->id,
            ])->save();

            $movimenti = $s->movimenti()->get();
            foreach ($movimenti as $m) {
                $m->fill(['data' => $dati['data'], 'data_documento' => $dati['data'], 'conto' => $dati['conto'], 'soggetto' => $soggetto->nome]);
                if ($movimenti->count() === 1) {
                    $m->importo = $dati['importo'];
                }
                $m->save();
            }
            if ($movimenti->count() === 1 && $s->righe()->count() === 1) {
                $s->righe()->update(['importo' => $dati['importo']]);
            }
            $s->ricalcolaStato();
            if ($chiuso) {
                $this->correzioneAnnoChiuso($s, 'correzione dei dati', $dati['motivazione'] ?? '');
                $this->anni->aggiornaChiusura($s->anno);
            }
        });
    }

    /**
     * «Registra la divisione» (tavola SN-6): un movimento per categoria con
     * la somma delle sue righe. I movimenti che ci sono già si riusano (prima
     * quello della stessa categoria, poi gli altri, come quello su «Uscite
     * da classificare»): restano numero e storia; quelli in più si tolgono.
     * La somma delle righe deve essere il totale (controllo del controller).
     *
     * @param list<array{descrizione: string, importo: string, categoria_id: int}> $righe
     * @return array{creati: int, aggiornati: int, tolti: int}
     */
    public function dividi(PrimaNotaScontrino $s, array $righe, ?string $motivazione = null): array
    {
        $chiuso = $this->anni->chiuso($s->anno);

        return DB::transaction(function () use ($s, $righe, $motivazione, $chiuso) {
            $s = PrimaNotaScontrino::with('soggetto')->lockForUpdate()->findOrFail($s->id);
            $esistenti = $s->movimenti()->orderBy('id')->lockForUpdate()->get();
            $piano = self::piano($righe, $esistenti->map(fn ($m) => ['id' => $m->id, 'categoria_id' => $m->categoria_id])->all());

            $conti = ['creati' => 0, 'aggiornati' => 0, 'tolti' => 0];
            $perCategoria = [];
            foreach ($piano['gruppi'] as $g) {
                $m = $g['movimento_id'] ? $esistenti->firstWhere('id', $g['movimento_id']) : new PrimaNota();
                $m->fill([
                    'tipo' => 'uscita',
                    'conto' => $s->conto,
                    'data' => $s->data_scontrino->toDateString(),
                    'importo' => $g['importo'],
                    'categoria_id' => $g['categoria_id'],
                    'descrizione' => $g['descrizione'],
                    'soggetto' => $s->fornitoreNome(),
                    'tipo_documento' => 'scontrino',
                    'numero_documento' => $s->codice(),
                    'data_documento' => $s->data_scontrino->toDateString(),
                    'scontrino_id' => $s->id,
                ]);
                if (! $m->exists) {
                    $m->forceFill(['registrato_da' => auth()->id()]);
                    $conti['creati']++;
                } elseif ($m->isDirty()) {
                    $conti['aggiornati']++;
                }
                $m->save();
                $perCategoria[$g['categoria_id']] = $m->id;
            }
            foreach ($piano['da_togliere'] as $id) {
                // I giustificativi aggiunti dalla Prima Nota passano al primo
                // movimento che resta (altrimenti la cancellazione a cascata li
                // toglierebbe lasciando i file)
                \App\Models\PrimaNotaDocumento::where('prima_nota_id', $id)->update(['prima_nota_id' => reset($perCategoria)]);
                // Senza eventi: l'operazione ha la sua riga SCONTRINO_DIVISO
                PrimaNota::withoutEvents(fn () => PrimaNota::whereKey($id)->delete());
                $conti['tolti']++;
            }

            $s->righe()->delete();
            foreach (array_values($righe) as $i => $r) {
                PrimaNotaScontrinoRiga::create([
                    'scontrino_id' => $s->id,
                    'ordine' => $i + 1,
                    'descrizione' => mb_substr(trim($r['descrizione']), 0, 200),
                    'importo' => $r['importo'],
                    'categoria_id' => (int) $r['categoria_id'],
                    'prima_nota_id' => $perCategoria[(int) $r['categoria_id']],
                ]);
            }
            $s->ricalcolaStato();

            AuditLogService::scrivi([
                'evento' => 'SCONTRINO_DIVISO',
                'entita_tipo' => 'PrimaNotaScontrino',
                'entita_id' => $s->id,
                'anno' => $s->anno,
                'livello_rischio' => 'MEDIUM',
                'dettaglio' => "Scontrino {$s->codice()} diviso in " . count($piano['gruppi']) . ' '
                    . (count($piano['gruppi']) === 1 ? 'movimento' : 'movimenti') . ' (' . count($righe) . ' righe).',
                'new_values' => ['movimenti' => array_values($perCategoria), 'tolti' => $piano['da_togliere']],
            ]);
            if ($chiuso) {
                $this->correzioneAnnoChiuso($s, 'nuova divisione', (string) $motivazione);
                $this->anni->aggiornaChiusura($s->anno);
            }

            return $conti;
        });
    }

    /**
     * Piano della divisione, uguale a quello mostrato nella scheda: righe
     * raggruppate per categoria nell'ordine in cui compaiono; ogni gruppo
     * riusa il movimento della stessa categoria o, se non c'è, il primo
     * movimento rimasto libero; gli altri si tolgono.
     *
     * @param list<array{descrizione: string, importo: string, categoria_id: int}> $righe
     * @param list<array{id: int, categoria_id: ?int}> $movimenti
     * @return array{gruppi: list<array{categoria_id: int, importo: string, descrizione: string, movimento_id: ?int}>, da_togliere: list<int>}
     */
    public static function piano(array $righe, array $movimenti): array
    {
        $gruppi = [];
        foreach ($righe as $r) {
            $c = (int) $r['categoria_id'];
            $gruppi[$c] ??= ['categoria_id' => $c, 'importo' => 0, 'descrizioni' => []];
            $gruppi[$c]['importo'] += Importo::centesimi($r['importo']);
            $gruppi[$c]['descrizioni'][] = trim($r['descrizione']);
        }
        $liberi = collect($movimenti);
        foreach ($gruppi as $c => $g) {
            $stesso = $liberi->firstWhere('categoria_id', $c);
            if ($stesso) {
                $gruppi[$c]['movimento_id'] = $stesso['id'];
                $liberi = $liberi->reject(fn ($m) => $m['id'] === $stesso['id']);
            }
        }
        foreach ($gruppi as $c => $g) {
            if (! array_key_exists('movimento_id', $g)) {
                $primo = $liberi->first();
                $gruppi[$c]['movimento_id'] = $primo['id'] ?? null;
                if ($primo) {
                    $liberi = $liberi->reject(fn ($m) => $m['id'] === $primo['id']);
                }
            }
        }

        return [
            'gruppi' => array_values(array_map(fn ($g) => [
                'categoria_id' => $g['categoria_id'],
                'importo' => Importo::daCentesimi($g['importo']),
                'descrizione' => mb_substr(implode(', ', array_unique(array_filter($g['descrizioni']))) ?: 'Scontrino', 0, 255),
                'movimento_id' => $g['movimento_id'],
            ], $gruppi)),
            'da_togliere' => $liberi->pluck('id')->values()->all(),
        ];
    }

    public function aggiungiFoto(PrimaNotaScontrino $s, UploadedFile $file, User $utente, ?string $motivazione = null): PrimaNotaScontrinoFile
    {
        $chiuso = $this->anni->chiuso($s->anno);
        $ext = self::estensione($file);

        return $this->inSicurezza(function () use ($s, $file, $utente, $ext, $chiuso, $motivazione) {
            $f = $this->archivia($s, $file->getRealPath(), $ext, $file->getClientOriginalName(), $utente);
            AuditLogService::scrivi([
                'evento' => 'SCONTRINO_FOTO_AGGIUNTA',
                'entita_tipo' => 'PrimaNotaScontrino',
                'entita_id' => $s->id,
                'anno' => $s->anno,
                'dettaglio' => "Scontrino {$s->codice()}: aggiunto il file {$f->nome}.",
            ]);
            if ($chiuso) {
                $this->correzioneAnnoChiuso($s, 'foto aggiunta', (string) $motivazione);
            }

            return $f;
        });
    }

    /**
     * Elimina lo scontrino con i suoi movimenti, le righe e le foto. Una
     * riga HIGH nell'Audit Log (quella dello scontrino, con i movimenti
     * tolti); i movimenti si tolgono senza eventi.
     */
    public function elimina(PrimaNotaScontrino $s, ?string $motivazione = null): void
    {
        $chiuso = $this->anni->chiuso($s->anno);
        $file = $s->file()->pluck('path')->merge([$s->anteprima_path, $s->miniatura_path, $s->allegato_path])
            // e i giustificativi aggiunti dalla Prima Nota ai suoi movimenti (si tolgono a cascata)
            ->merge(\App\Models\PrimaNotaDocumento::whereIn('prima_nota_id', $s->movimenti()->select('id'))->pluck('path'))
            ->filter()->unique()->all();

        DB::transaction(function () use ($s, $chiuso, $motivazione) {
            $s = PrimaNotaScontrino::lockForUpdate()->findOrFail($s->id);
            $movimenti = $s->movimenti()->pluck('id')->all();
            if ($chiuso) {
                $this->correzioneAnnoChiuso($s, 'eliminazione', (string) $motivazione);
            }
            PrimaNota::withoutEvents(fn () => PrimaNota::whereIn('id', $movimenti)->delete());
            $s->righe()->delete();
            $s->file()->delete();
            AuditLogService::scrivi([
                'evento' => 'SCONTRINO_ELIMINATO',
                'entita_tipo' => 'PrimaNotaScontrino',
                'entita_id' => $s->id,
                'anno' => $s->anno,
                'livello_rischio' => 'HIGH',
                'dettaglio' => "Scontrino {$s->codice()} eliminato con " . count($movimenti) . ' '
                    . (count($movimenti) === 1 ? 'movimento' : 'movimenti') . ' di Prima Nota.',
                'old_values' => ['totale' => (string) $s->importo_totale, 'data' => $s->data_scontrino->toDateString(), 'movimenti' => $movimenti],
            ]);
            PrimaNotaScontrino::withoutEvents(fn () => $s->delete());
            if ($chiuso) {
                $this->anni->aggiornaChiusura($s->anno);
            }
        });
        foreach ($file as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    // ----------------------------------------------------------------
    // File
    // ----------------------------------------------------------------

    public static function estensione(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->guessExtension());

        return $ext === 'jpeg' ? 'jpg' : $ext;
    }

    /** Copia un file nell'archivio con il nome codificato. */
    private function archivia(PrimaNotaScontrino $s, string $sorgente, string $ext, ?string $originale, User $utente): PrimaNotaScontrinoFile
    {
        $progressivo = $s->file()->count();
        do {
            $progressivo++;
            $nome = $s->codice() . ($progressivo > 1 ? sprintf('-%02d', $progressivo) : '') . '.' . $ext;
            $path = "scontrini/{$s->anno}/{$nome}";
        } while (Storage::disk('local')->exists($path));

        $sorgenteDisco = is_file($sorgente) ? $sorgente : Storage::disk('local')->path($sorgente);
        Storage::disk('local')->put($path, file_get_contents($sorgenteDisco));
        $this->scritti[] = $path;

        return PrimaNotaScontrinoFile::create([
            'scontrino_id' => $s->id,
            'path' => $path,
            'nome' => $nome,
            'originale' => $originale ? mb_substr($originale, 0, 255) : null,
            'caricato_da' => $utente->id,
        ]);
    }

    /** Anteprima (la copia ridotta mandata alla lettura) e miniatura per l'elenco. */
    private function anteprime(PrimaNotaScontrino $s, ?string $ridotta): array
    {
        if (! $ridotta || ! Storage::disk('local')->exists($ridotta)) {
            return [];
        }
        $anteprima = "scontrini/{$s->anno}/anteprime/{$s->codice()}.jpg";
        Storage::disk('local')->copy($ridotta, $anteprima);
        $this->scritti[] = $anteprima;
        $out = ['anteprima_path' => $anteprima];

        $mini = \App\Scontrini\Foto::riduci(Storage::disk('local')->path($ridotta), 240, 70);
        if ($mini) {
            $path = "scontrini/{$s->anno}/anteprime/{$s->codice()}-mini.jpg";
            Storage::disk('local')->put($path, $mini);
            $this->scritti[] = $path;
            $out['miniatura_path'] = $path;
        }

        return $out;
    }

    private function inSicurezza(\Closure $operazione): mixed
    {
        $this->scritti = [];
        try {
            $risultato = DB::transaction($operazione);
            $this->scritti = [];

            return $risultato;
        } catch (\Throwable $e) {
            foreach ($this->scritti as $path) {
                Storage::disk('local')->delete($path);
            }
            $this->scritti = [];
            throw $e;
        }
    }

    private function correzioneAnnoChiuso(PrimaNotaScontrino $s, string $azione, string $motivazione): void
    {
        AuditLogService::scrivi([
            'evento' => 'SCONTRINO_CORREZIONE_ANNO_CHIUSO',
            'entita_tipo' => 'PrimaNotaScontrino',
            'entita_id' => $s->id,
            'anno' => $s->anno,
            'livello_rischio' => 'HIGH',
            'dettaglio' => "Scontrino {$s->codice()} dell'anno chiuso {$s->anno}: {$azione}.",
            'new_values' => ['motivazione' => $motivazione],
        ]);
    }
}
