<?php

namespace App\Services;

use App\Models\Qualifica;
use App\Models\Socio;
use App\Models\Tesseramento;
use App\Models\TesseraPacchetto;
use App\Models\TesseraTipo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carico delle tessere già emesse dall'elenco esportato dal portale CSEN
 * (v0.37.0). Formato: CSV con «;», intestazione come nel file della
 * Compagnia del 07/10/2026:
 *
 *   ASSOCIAZIONE;COGNOME;NOME;SESSO;DATA DI NASCITA;LUOGO DI NASCITA;
 *   CODICE FISCALE;DATA DI INSERIMENTO;DATA DI RILASCIO;NUMERO TESSERA;
 *   DISCIPLINE;QUALIFICA;TIPO TESSERA;TELEFONO;E-MAIL;ISTRUTTORE
 *
 * Dal file si prendono solo i dati della tessera; il socio si trova per
 * codice fiscale e la sua anagrafica non cambia. Telefono ed email non si
 * leggono. Prima un'anteprima (righe nuove, già presenti, senza socio,
 * errate), poi la conferma registra solo le nuove.
 */
class CaricoTessereCsen
{
    public const COLONNE = ['CODICE FISCALE', 'DATA DI RILASCIO', 'NUMERO TESSERA', 'TIPO TESSERA'];

    public const ESITI = [
        'nuova' => ['Da caricare', 'ok'],
        'presente' => ['Già nel gestionale', 'neutral'],
        'socio' => ['Socio non trovato', 'warn'],
        'errore' => ['Riga da correggere', 'ko'],
    ];

    /**
     * Legge il file e prepara l'anteprima.
     *
     * @return array{righe: list<array>, errore: ?string}
     */
    public function leggi(string $percorso): array
    {
        $testo = (string) file_get_contents($percorso);
        $testo = preg_replace('/^\xEF\xBB\xBF/', '', $testo);
        if (! mb_check_encoding($testo, 'UTF-8')) {
            $testo = mb_convert_encoding($testo, 'UTF-8', 'Windows-1252');
        }
        $linee = preg_split('/\r\n|\n|\r/', trim($testo));
        if (count($linee) < 2) {
            return ['righe' => [], 'errore' => 'Il file è vuoto o ha solo l\'intestazione.'];
        }

        $sep = substr_count($linee[0], ';') >= substr_count($linee[0], ',') ? ';' : ',';
        $intestazione = array_map(fn ($c) => strtoupper(trim($c)), str_getcsv($linee[0], $sep, '"', ''));
        $mancanti = array_diff(self::COLONNE, $intestazione);
        if ($mancanti) {
            return ['righe' => [], 'errore' => 'Non sembra l\'elenco del CSEN: mancano le colonne ' . implode(', ', $mancanti) . '.'];
        }
        $col = array_flip($intestazione);

        $soci = Socio::whereNotNull('codice_fiscale')->get(['id', 'cognome', 'nome', 'codice_fiscale', 'stato'])
            ->keyBy(fn ($s) => strtoupper($s->codice_fiscale));
        $presenti = Tesseramento::pluck('numero_tessera_csen')->map(fn ($n) => strtoupper($n))->flip();
        $nelFile = [];

        $righe = [];
        foreach (array_slice($linee, 1) as $i => $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $v = str_getcsv($linea, $sep, '"', '');
            $c = fn (string $nome) => trim((string) ($v[$col[$nome] ?? -1] ?? ''));
            $cf = strtoupper($c('CODICE FISCALE'));
            $numero = strtoupper(preg_replace('/\s+/', '', $c('NUMERO TESSERA')));
            $rilascio = $this->data($c('DATA DI RILASCIO'));
            $inserimento = $this->data($c('DATA DI INSERIMENTO'));
            $socio = $soci->get($cf);

            $r = [
                'riga' => $i + 2,
                'cognome' => $c('COGNOME'),
                'nome' => $c('NOME'),
                'codice_fiscale' => $cf,
                'numero' => $numero,
                'tipo' => $this->tipo($c('TIPO TESSERA')),
                'data_rilascio' => $rilascio?->toDateString(),
                'data_inserimento' => $inserimento?->toDateString(),
                'scadenza' => $rilascio ? Tesseramento::scadenzaDa($rilascio)->toDateString() : null,
                'qualifica' => $c('QUALIFICA'),
                'discipline' => mb_substr($c('DISCIPLINE'), 0, 100),
                'istruttore' => $c('ISTRUTTORE'),
                'socio_id' => $socio?->id,
                'socio' => $socio ? "{$socio->cognome} {$socio->nome}" . ($socio->stato !== 'attivo' ? ' (non attivo)' : '') : null,
                'messaggio' => null,
            ];

            $letto = Tessere::leggi($numero);
            [$r['esito'], $r['messaggio']] = match (true) {
                ! $letto || mb_strlen($numero) > 30 || mb_strlen($letto['prefisso']) > 10 => ['errore', 'Numero di tessera non valido.'],
                mb_strlen($r['tipo']) > 50 => ['errore', 'Tipo di tessera troppo lungo.'],
                ! $rilascio => ['errore', 'Data di rilascio mancante o non valida (gg/mm/aaaa).'],
                $r['tipo'] === '' => ['errore', 'Tipo di tessera mancante.'],
                isset($nelFile[$numero]) => ['errore', 'Numero ripetuto nel file (riga ' . $nelFile[$numero] . ').'],
                isset($presenti[$numero]) => ['presente', null],
                ! $socio => ['socio', 'Nessun socio con questo codice fiscale: inseriscilo e ricarica il file.'],
                default => ['nuova', null],
            };
            $nelFile[$numero] ??= $r['riga'];
            $righe[] = $r;
        }

        return ['righe' => $righe, 'errore' => $righe ? null : 'Nessuna riga nel file.'];
    }

    /**
     * Registra le righe «nuova». Tipi nuovi senza prezzo; collegamento ai
     * pacchetti già registrati (stesso tipo, numero nell'intervallo) e alla
     * tessera precedente dello stesso socio.
     *
     * @return array{caricate: int, tipi_nuovi: list<string>}
     */
    public function registra(array $righe, User $utente): array
    {
        return DB::transaction(function () use ($righe, $utente) {
            $tipi = TesseraTipo::all()->keyBy(fn ($t) => mb_strtolower($t->nome));
            $qualifiche = Qualifica::all()->keyBy(fn ($q) => mb_strtolower($q->label));
            $tipiNuovi = [];
            $caricate = 0;
            $sociToccati = [];

            foreach ($righe as $r) {
                if ($r['esito'] !== 'nuova' || Tesseramento::where('numero_tessera_csen', $r['numero'])->exists()) {
                    continue;
                }
                $chiave = mb_strtolower($r['tipo']);
                if (! $tipi->has($chiave)) {
                    $tipi[$chiave] = TesseraTipo::create(['nome' => $r['tipo'], 'prezzo' => null, 'attivo' => true,
                        'ordine' => (int) TesseraTipo::max('ordine') + 10]);
                    $tipiNuovi[] = $r['tipo'];
                }
                $tipo = $tipi[$chiave];
                $n = Tessere::leggi($r['numero']);
                $pacchetto = TesseraPacchetto::where('tipo_id', $tipo->id)->where('dal', '<=', $n['progressivo'])->where('al', '>=', $n['progressivo'])->first();
                if ($pacchetto && Tesseramento::where('pacchetto_id', $pacchetto->id)->where('progressivo', $n['progressivo'])->exists()) {
                    $pacchetto = null;
                }

                $t = new Tesseramento();
                $t->forceFill([
                    'socio_id' => $r['socio_id'],
                    'stagione_sportiva' => (int) substr($r['data_rilascio'], 0, 4),
                    'ente_affiliante' => 'CSEN',
                    'numero_tessera_csen' => $r['numero'],
                    'progressivo' => $n['progressivo'],
                    'data_inserimento' => $r['data_inserimento'],
                    'data_rilascio' => $r['data_rilascio'],
                    'scadenza' => $r['scadenza'],
                    'pacchetto_tesseramento' => $tipo->nome,
                    'tipo_id' => $tipo->id,
                    'pacchetto_id' => $pacchetto?->id,
                    'origine' => 'caricata',
                    'discipline' => $r['discipline'] ?: null,
                    'portale_csen_il' => now(),   // viene dal portale
                    'stato' => 'attivo',
                    'note' => $r['istruttore'] !== '' ? 'Istruttore indicato dal CSEN: ' . mb_substr($r['istruttore'], 0, 100) : null,
                    'inserito_da' => $utente->id,
                ]);
                // Una riga di audit per tutto il carico (sotto), non una per tessera
                Tesseramento::withoutEvents(fn () => $t->save());

                if ($q = $qualifiche->get(mb_strtolower($r['qualifica']))) {
                    $t->qualifiche()->syncWithoutDetaching([$q->id]);
                }
                $sociToccati[$r['socio_id']] = true;
                $caricate++;
            }

            // Catena dei rinnovi: ogni tessera punta all'ultima scaduta prima
            foreach (array_keys($sociToccati) as $socioId) {
                $tessere = Tesseramento::where('socio_id', $socioId)->orderBy('data_rilascio')->get();
                foreach ($tessere as $t) {
                    if ($t->precedente_id) {
                        continue;
                    }
                    $prec = $tessere->filter(fn ($x) => $x->id !== $t->id && $x->scadenza && $x->scadenza->lt($t->data_rilascio))
                        ->sortBy(fn ($x) => $x->scadenza->toDateString())->last();
                    if ($prec && ! $tessere->contains(fn ($x) => $x->precedente_id === $prec->id)) {
                        $t->forceFill(['precedente_id' => $prec->id])->saveQuietly();
                        $prec->setRelation('successiva', $t);
                    }
                }
            }

            AuditLogService::scrivi([
                'evento' => 'TESSERE_CARICATE',
                'entita_tipo' => 'Tesseramento',
                'livello_rischio' => 'MEDIUM',
                'dettaglio' => "Carico dall'elenco del CSEN: {$caricate} tessere."
                    . ($tipiNuovi ? ' Tipi nuovi senza prezzo: ' . implode(', ', $tipiNuovi) . '.' : ''),
            ]);

            return ['caricate' => $caricate, 'tipi_nuovi' => $tipiNuovi];
        });
    }

    private function data(string $v): ?Carbon
    {
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $f) {
            try {
                $d = Carbon::createFromFormat('!' . $f, $v);
                if ($d && $d->format($f) === $v && $d->year >= 2000 && $d->lte(today()->addYear())) {
                    return $d;
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /** «SILVER BASE» e «Silver Base» sono lo stesso tipo: nome con le iniziali maiuscole. */
    private function tipo(string $v): string
    {
        $v = trim(preg_replace('/\s+/', ' ', $v));

        return $v === '' ? '' : mb_convert_case(mb_strtolower($v), MB_CASE_TITLE);
    }
}
