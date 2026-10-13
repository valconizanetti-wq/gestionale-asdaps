<?php

namespace App\Services;

use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Models\Tesseramento;
use App\Models\TesseraPacchetto;
use App\Models\TesseraTipo;
use App\Models\User;
use App\Support\Importo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tessere CSEN (analisi-tessere-scadenze.md, tavole TS-1…TS-5, v0.37.0).
 *
 * - La quota entra intera; il costo delle tessere esce in Prima Nota
 *   all'acquisto del pacchetto («Tesseramento Soci EPS»).
 * - Una tessera vale un anno dall'emissione e comprende l'assicurazione
 *   base: si rinnova dopo 12 mesi, il giorno dopo la scadenza.
 * - Il numero proposto è il primo libero dei pacchetti di quel tipo (dal
 *   più vecchio); se ne può scegliere un altro del pacchetto, mai uno usato.
 * - Si rinnova a chi è in regola con la quota o ancora nel termine (31/01);
 *   a chi è in ritardo o da dichiarare decaduto solo con una scelta
 *   esplicita, registrata con la tessera (senza_quota).
 */
class Tessere
{
    /** Codice della categoria «Tesseramento Soci EPS» (PrimaNotaCategoriaSeeder). */
    public const CATEGORIA = 'TESSERE_EPS';

    /** Tessere al massimo in un pacchetto: un errore di battitura non crea migliaia di numeri. */
    public const MAX_PACCHETTO = 1000;

    public function __construct(private AnniContabili $anni)
    {
    }

    // ----------------------------------------------------------------
    // Numeri
    // ----------------------------------------------------------------

    /**
     * «26B2749167» → prefisso «26B», progressivo 2749167, 7 cifre; «0951» →
     * prefisso vuoto, 951, 4 cifre. Null se non finisce con delle cifre.
     *
     * @return array{prefisso: string, progressivo: int, cifre: int}|null
     */
    public static function leggi(?string $numero): ?array
    {
        $numero = strtoupper(preg_replace('/\s+/', '', (string) $numero));
        if (! preg_match('/^([A-Z0-9]*?[A-Z])?(\d{1,9})$/', $numero, $m)) {
            return null;
        }

        return ['prefisso' => $m[1] ?? '', 'progressivo' => (int) $m[2], 'cifre' => strlen($m[2])];
    }

    public static function formatta(string $prefisso, int $progressivo, int $cifre): string
    {
        return $prefisso . str_pad((string) $progressivo, $cifre, '0', STR_PAD_LEFT);
    }

    /** Pacchetti che si sovrappongono a un intervallo di progressivi. */
    public static function sovrapposti(int $dal, int $al, ?int $escludi = null): Collection
    {
        return TesseraPacchetto::with('tipo')
            ->where('dal', '<=', $al)->where('al', '>=', $dal)
            ->when($escludi, fn ($q) => $q->whereKeyNot($escludi))
            ->get();
    }

    /**
     * Progressivi già usati di un pacchetto: le sue tessere e ogni altra
     * tessera con un numero nel suo intervallo (caricata senza pacchetto,
     * di un tipo scritto diversamente, di prima della v0.37.0). Un numero
     * stampato si usa una volta sola, qualunque sia il prefisso.
     */
    public static function usati(TesseraPacchetto $p): array
    {
        return Tesseramento::where(fn ($q) => $q->where('pacchetto_id', $p->id)->orWhereBetween('progressivo', [$p->dal, $p->al]))
            ->pluck('progressivo')->filter(fn ($n) => $n !== null)->map(fn ($n) => (int) $n)->unique()->values()->all();
    }

    public static function disponibili(TesseraPacchetto $p): int
    {
        return max(0, $p->quantita() - count(array_filter(self::usati($p), fn ($n) => $p->contiene($n))));
    }

    /**
     * Primo numero libero dei pacchetti di un tipo, dal più vecchio.
     *
     * @return array{pacchetto: TesseraPacchetto, progressivo: int, numero: string}|null
     */
    public static function primoLibero(int $tipoId): ?array
    {
        $pacchetti = TesseraPacchetto::where('tipo_id', $tipoId)->orderBy('acquistato_il')->orderBy('id')->get();
        foreach ($pacchetti as $p) {
            $usati = array_flip(self::usati($p));
            if (count($usati) >= $p->quantita()) {
                continue;
            }
            for ($n = $p->dal; $n <= $p->al; $n++) {
                if (! isset($usati[$n])) {
                    return ['pacchetto' => $p, 'progressivo' => $n, 'numero' => $p->numero($n)];
                }
            }
        }

        return null;
    }

    // ----------------------------------------------------------------
    // Situazione dei soci
    // ----------------------------------------------------------------

    /**
     * Situazione della tessera di ogni socio, oggi: la tessera che lo copre
     * (se è rinnovata in anticipo resta «valida»), altrimenti l'ultima.
     *
     * @param  iterable<int>  $socioIds
     * @return array<int, array{stato: string, tessera: ?Tesseramento, giorni: ?int, testo: string, variante: string}>
     */
    public static function perSoci(iterable $socioIds, ?Carbon $oggi = null): array
    {
        $oggi = ($oggi ?? today())->copy()->startOfDay();
        $ids = collect($socioIds)->map(fn ($i) => (int) $i)->unique()->values();
        $tessere = Tesseramento::with(['tipo', 'pacchetto'])->whereIn('socio_id', $ids)
            ->orderBy('data_rilascio')->orderBy('id')->get()->groupBy('socio_id');

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = self::situazione($tessere->get($id, collect()), $oggi);
        }

        return $out;
    }

    /** @param Collection<int, Tesseramento> $tessere tutte le tessere del socio */
    public static function situazione(Collection $tessere, ?Carbon $oggi = null): array
    {
        $oggi = ($oggi ?? today())->copy()->startOfDay();
        if ($tessere->isEmpty()) {
            return ['stato' => 'mancante', 'tessera' => null, 'giorni' => null, 'testo' => 'Senza tessera', 'variante' => 'ko'];
        }

        $copre = $tessere->filter(fn ($t) => $t->scadenza && $t->data_rilascio->lte($oggi) && $t->scadenza->gte($oggi))
            ->sortBy(fn ($t) => $t->scadenza->toDateString())->last();
        if ($copre) {
            $giorni = (int) $oggi->diffInDays($copre->scadenza, false);
            // Già rinnovata (la successiva parte dal giorno dopo): niente avviso
            $rinnovata = $tessere->contains(fn ($t) => $t->data_rilascio->gt($copre->scadenza)
                && $t->data_rilascio->lte($copre->scadenza->copy()->addDay()));
            if (! $rinnovata && $giorni <= Tesseramento::GIORNI_PREAVVISO) {
                return ['stato' => 'in_scadenza', 'tessera' => $copre, 'giorni' => $giorni, 'variante' => 'warn',
                    'testo' => $giorni === 0 ? 'Scade oggi' : ($giorni === 1 ? 'Scade domani' : "Scade tra {$giorni} giorni")];
            }

            return ['stato' => 'valida', 'tessera' => $copre, 'giorni' => $giorni, 'testo' => 'Valida', 'variante' => 'ok'];
        }

        $ultima = $tessere->sortBy(fn ($t) => ($t->scadenza ?? $t->data_rilascio)->toDateString())->last();
        if (! $ultima->scadenza) {   // riga senza date complete: da controllare
            return ['stato' => 'scaduta', 'tessera' => $ultima, 'giorni' => null, 'testo' => 'Tessera da controllare', 'variante' => 'ko'];
        }
        if ($ultima->data_rilascio->gt($oggi)) {
            return ['stato' => 'futura', 'tessera' => $ultima, 'giorni' => null, 'variante' => 'warn',
                'testo' => 'Valida dal ' . $ultima->data_rilascio->format('d/m/Y')];
        }

        return ['stato' => 'scaduta', 'tessera' => $ultima, 'giorni' => (int) $oggi->diffInDays($ultima->scadenza, false),
            'testo' => 'Tessera scaduta', 'variante' => 'ko'];
    }

    /** Data proposta per la nuova tessera: il giorno dopo la scadenza della precedente, se non è già passata; altrimenti oggi. */
    public static function dataProposta(?Tesseramento $ultima, ?Carbon $oggi = null): Carbon
    {
        $oggi = ($oggi ?? today())->copy()->startOfDay();
        if ($ultima?->scadenza && $ultima->scadenza->gte($oggi)) {
            return $ultima->scadenza->copy()->addDay();
        }

        return $oggi;
    }

    /**
     * Quota del socio per l'anno della tessera (TS-3): versata se c'è una
     * quota valida per quell'anno o dopo (promozione di fine anno).
     * «conferma»: in ritardo o da dichiarare decaduto, si rinnova solo con
     * «Rinnova lo stesso».
     *
     * @return array{versata: bool, anno: int, situazione: string, titolo: string, testo: string, variante: string, conferma: bool}
     */
    public static function quota(Socio $socio, Carbon $data, ?Carbon $oggi = null): array
    {
        $oggi ??= now();
        $anno = $data->year;
        $r = Rinnovo::valido()->where('socio_id', $socio->id)->where('anno', '>=', $anno)->orderBy('anno')->first();
        if ($r) {
            return ['versata' => true, 'anno' => $anno, 'situazione' => RinnoviQuota::IN_REGOLA, 'variante' => 'ok', 'conferma' => false,
                'titolo' => "Quota {$r->anno} versata",
                'testo' => ucfirst($r->riferimento()) . " del {$r->data_pagamento->format('d/m/Y')}."];
        }

        // Prima dell'avvio della contabilità nel gestionale (2027) le quote
        // sono nel vecchio gestionale: situazione non nota, nessun blocco.
        if ($anno < RinnoviQuota::annoAvvio()) {
            return ['versata' => false, 'anno' => $anno, 'situazione' => 'vecchio', 'variante' => 'neutral', 'conferma' => false,
                'titolo' => "Quota {$anno} nel vecchio gestionale",
                'testo' => 'Le quote prima del ' . RinnoviQuota::annoAvvio() . ' non sono nel gestionale: controlla tu che sia versata.'];
        }

        // Anno futuro (tessera che parte a gennaio, emessa a dicembre): ancora nel termine
        $sit = $anno > $oggi->year ? RinnoviQuota::IN_ATTESA : RinnoviQuota::situazione(false, $anno, $oggi);
        $nome = $socio->nome;

        return match ($sit) {
            RinnoviQuota::IN_ATTESA => ['versata' => false, 'anno' => $anno, 'situazione' => $sit, 'variante' => 'info', 'conferma' => false,
                'titolo' => "Quota {$anno} da versare entro il 31/01/{$anno}",
                'testo' => "{$nome} è ancora nel termine: la tessera si rinnova."],
            RinnoviQuota::SOSPESO => ['versata' => false, 'anno' => $anno, 'situazione' => $sit, 'variante' => 'warn', 'conferma' => true,
                'titolo' => "Quota {$anno} non ancora versata",
                'testo' => "{$nome} è in ritardo (voto sospeso, decade dopo il 30 aprile). Puoi rinnovare comunque la tessera: resta a carico della Compagnia finché non versa."],
            default => ['versata' => false, 'anno' => $anno, 'situazione' => $sit, 'variante' => 'ko', 'conferma' => true,
                'titolo' => "Quota {$anno} non versata entro il 30 aprile",
                'testo' => "{$nome} è da dichiarare decaduto (statuto art. 9). Rinnova la tessera solo se il CD ha deciso diversamente."],
        };
    }

    // ----------------------------------------------------------------
    // Pacchetti (TS-2)
    // ----------------------------------------------------------------

    /**
     * Registra il pacchetto e, se chiesto, l'uscita in Prima Nota. Le tessere
     * già caricate con un numero del pacchetto (stesso tipo) si collegano.
     * $dopo riceve il movimento (per i giustificativi), dentro la transazione.
     *
     * @param array{tipo_id: int, acquistato_il: string, prefisso: string, dal: int, al: int, cifre: int,
     *              prezzo_unitario: float, note?: ?string, movimento: bool, pagato_il?: ?string, conto?: ?string} $d
     *
     * @return array{0: TesseraPacchetto, 1: ?PrimaNota, 2: int} pacchetto, movimento, tessere collegate
     *
     * @throws \RuntimeException
     */
    public function registraPacchetto(array $d, User $utente, ?\Closure $dopo = null): array
    {
        $tipo = TesseraTipo::findOrFail($d['tipo_id']);
        $totale = round($d['prezzo_unitario'] * ($d['al'] - $d['dal'] + 1), 2);
        $categoriaId = null;
        if ($d['movimento']) {
            $anno = Carbon::parse($d['pagato_il'])->year;
            if ($anno < RinnoviQuota::annoAvvio()) {
                throw new \RuntimeException("Il pagamento del {$anno} è prima dell'avvio della contabilità nel gestionale (" . RinnoviQuota::annoAvvio() . "): togli la spunta «Registra l'uscita», il pagamento è nel vecchio gestionale.");
            }
            if ($this->anni->chiuso($anno)) {
                throw new \RuntimeException("L'anno {$anno} della Prima Nota è chiuso: l'uscita non si registra con questa data.");
            }
            $categoriaId = PrimaNotaCategoria::where('codice', self::CATEGORIA)->value('id');
            if (! $categoriaId) {
                throw new \RuntimeException('Manca la categoria «Tesseramento Soci EPS» della Prima Nota: rilanciare PrimaNotaCategoriaSeeder.');
            }
        }

        return DB::transaction(function () use ($d, $tipo, $totale, $categoriaId, $utente, $dopo) {
            // Lock sui pacchetti: due registrazioni insieme non si sovrappongono.
            TesseraPacchetto::lockForUpdate()->get(['id']);
            if (($altri = self::sovrapposti($d['dal'], $d['al']))->isNotEmpty()) {
                $p = $altri->first();
                throw new \RuntimeException("I numeri si sovrappongono al pacchetto {$p->tipo->nome} {$p->intervallo()} del {$p->acquistato_il->format('d/m/Y')}.");
            }

            $p = TesseraPacchetto::create([
                'tipo_id' => $tipo->id,
                'acquistato_il' => $d['acquistato_il'],
                'prefisso' => $d['prefisso'],
                'dal' => $d['dal'],
                'al' => $d['al'],
                'cifre' => $d['cifre'],
                'prezzo_unitario' => $d['prezzo_unitario'],
                'totale' => $totale,
                'note' => $d['note'] ?? null,
                'inserito_da' => $utente->id,
            ]);

            $m = null;
            if ($categoriaId) {
                $data = Carbon::parse($d['pagato_il']);
                $this->anni->assicura($data->year);
                $m = new PrimaNota();
                $m->forceFill([
                    'data' => $data->toDateString(),
                    'tipo' => 'uscita',
                    'conto' => $d['conto'],
                    'importo' => $totale,
                    'descrizione' => "Tessere CSEN {$tipo->nome} n. {$p->intervallo()} ({$p->quantita()} × " . Importo::it($d['prezzo_unitario'], true) . ')',
                    'categoria_id' => $categoriaId,
                    'soggetto' => 'CSEN',
                    'registrato_da' => $utente->id,
                ])->save();
                $p->forceFill(['prima_nota_id' => $m->id])->saveQuietly();
                if ($dopo) {
                    $dopo($m);
                }
            }

            // Tessere caricate prima del pacchetto, con un suo numero e lo stesso tipo
            $collegate = Tesseramento::whereNull('pacchetto_id')->where('tipo_id', $tipo->id)
                ->whereBetween('progressivo', [$p->dal, $p->al])->update(['pacchetto_id' => $p->id]);

            return [$p, $m, $collegate];
        });
    }

    /**
     * Correzione di un pacchetto (note, intervallo, prezzo), con motivazione
     * se ha tessere emesse: l'intervallo deve contenere i numeri già usati; il
     * movimento collegato segue il nuovo totale (non su un anno chiuso).
     *
     * @throws \RuntimeException
     */
    public function correggiPacchetto(TesseraPacchetto $p, array $d, string $motivo, User $utente): TesseraPacchetto
    {
        return DB::transaction(function () use ($p, $d, $motivo) {
            $p = TesseraPacchetto::whereKey($p->id)->lockForUpdate()->firstOrFail();
            $usati = self::usati($p);
            if ($usati && (min($usati) < $d['dal'] || max($usati) > $d['al'])) {
                throw new \RuntimeException('L\'intervallo deve comprendere le tessere già emesse: dal ' . $p->numero(min($usati)) . ' al ' . $p->numero(max($usati)) . '.');
            }
            if (($altri = self::sovrapposti($d['dal'], $d['al'], $p->id))->isNotEmpty()) {
                $o = $altri->first();
                throw new \RuntimeException("I numeri si sovrappongono al pacchetto {$o->tipo->nome} {$o->intervallo()}.");
            }
            $totale = round($d['prezzo_unitario'] * ($d['al'] - $d['dal'] + 1), 2);
            $m = $p->movimento;
            if ($m && abs((float) $m->importo - $totale) >= 0.005 && $this->anni->chiuso($m->annoMovimento())) {
                throw new \RuntimeException("Il movimento n. {$m->id} è dell'anno chiuso {$m->annoMovimento()}: il totale non si cambia.");
            }

            $p->fill([
                'acquistato_il' => $d['acquistato_il'], 'prefisso' => $d['prefisso'], 'dal' => $d['dal'], 'al' => $d['al'],
                'cifre' => $d['cifre'], 'prezzo_unitario' => $d['prezzo_unitario'], 'totale' => $totale, 'note' => $d['note'] ?? null,
            ])->save();

            if ($m && abs((float) $m->importo - $totale) >= 0.005) {
                $m->forceFill([
                    'importo' => $totale,
                    'descrizione' => "Tessere CSEN {$p->tipo->nome} n. {$p->intervallo()} ({$p->quantita()} × " . Importo::it($p->prezzo_unitario, true) . ')',
                ])->save();
            }

            if ($usati) {
                AuditLogService::scrivi([
                    'evento' => 'TESSERE_PACCHETTO_CORRETTO',
                    'entita_tipo' => 'TesseraPacchetto',
                    'entita_id' => $p->id,
                    'livello_rischio' => 'MEDIUM',
                    'dettaglio' => "Pacchetto n. {$p->id} con tessere emesse corretto. Motivazione: {$motivo}",
                ]);
            }

            return $p;
        });
    }

    /**
     * Elimina un pacchetto senza tessere emesse, con il suo movimento (se
     * l'anno è aperto). Un pacchetto con tessere non si elimina.
     *
     * @throws \RuntimeException
     */
    public function eliminaPacchetto(TesseraPacchetto $p): void
    {
        // I file dei giustificativi si cancellano dopo il commit: se la
        // transazione fallisce restano dove sono (come in Prima Nota).
        $file = DB::transaction(function () use ($p) {
            $p = TesseraPacchetto::whereKey($p->id)->lockForUpdate()->firstOrFail();
            if (self::usati($p)) {   // anche tessere caricate con un suo numero
                throw new \RuntimeException('Il pacchetto ha tessere emesse: non si elimina, si corregge.');
            }
            $m = $p->movimento;
            if ($m && $this->anni->chiuso($m->annoMovimento())) {
                throw new \RuntimeException("Il movimento n. {$m->id} è dell'anno chiuso {$m->annoMovimento()}: il pacchetto non si elimina.");
            }
            $p->delete();
            $file = [];
            if ($m) {
                $file = $m->documenti()->pluck('path')->all();
                $m->documenti()->delete();
                $m->delete();
            }

            return $file;
        });
        foreach ($file as $path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
        }
    }

    // ----------------------------------------------------------------
    // Emissione e rinnovo (TS-3)
    // ----------------------------------------------------------------

    /**
     * Emette la tessera (anche come rinnovo: la precedente è l'ultima del
     * socio scaduta prima della nuova).
     *
     * @param array{tipo_id: int, numero: string, data_rilascio: string, portale?: bool, conferma?: bool, note?: ?string} $d
     *
     * @throws \RuntimeException con un messaggio per l'utente
     */
    public function emetti(Socio $socio, array $d, User $utente): Tesseramento
    {
        $tipo = TesseraTipo::findOrFail($d['tipo_id']);
        $n = self::leggi($d['numero']);
        if (! $n) {
            throw new \RuntimeException('Il numero della tessera deve finire con delle cifre (es. 1217 o 26B2749167).');
        }
        $data = Carbon::parse($d['data_rilascio'])->startOfDay();
        $scadenza = Tesseramento::scadenzaDa($data);

        return DB::transaction(function () use ($socio, $d, $tipo, $n, $data, $scadenza, $utente) {
            $s = Socio::whereKey($socio->id)->lockForUpdate()->firstOrFail();

            $p = TesseraPacchetto::where('tipo_id', $tipo->id)->where('dal', '<=', $n['progressivo'])->where('al', '>=', $n['progressivo'])
                ->lockForUpdate()->first();
            if (! $p) {
                throw new \RuntimeException("Il numero {$d['numero']} non è in nessun pacchetto {$tipo->nome}: registra prima il pacchetto.");
            }
            if ($n['prefisso'] !== '' && $p->prefisso !== '' && $n['prefisso'] !== $p->prefisso) {
                throw new \RuntimeException("Il pacchetto {$p->intervallo()} ha il prefisso {$p->prefisso}: scrivi {$p->prefisso}{$n['progressivo']} o solo le cifre.");
            }
            // Solo le cifre: il prefisso è quello del pacchetto («1024419» → «27B1024419»)
            $numero = self::formatta($n['prefisso'] !== '' ? $n['prefisso'] : $p->prefisso, $n['progressivo'], max($n['cifre'], $p->cifre));
            if (Tesseramento::where('numero_tessera_csen', $numero)->orWhere('progressivo', $n['progressivo'])->exists()) {
                throw new \RuntimeException("La tessera {$numero} è già stata emessa: scegli un numero libero.");
            }

            $tessere = Tesseramento::where('socio_id', $s->id)->get();
            $sovrapposta = $tessere->first(fn ($t) => $t->scadenza && $t->data_rilascio->lte($scadenza) && $t->scadenza->gte($data));
            if ($sovrapposta) {
                throw new \RuntimeException("{$s->cognome} {$s->nome} ha già la tessera {$sovrapposta->numero_tessera_csen} valida fino al "
                    . $sovrapposta->scadenza->format('d/m/Y') . ': la nuova può partire dal ' . $sovrapposta->scadenza->copy()->addDay()->format('d/m/Y') . '.');
            }

            $quota = self::quota($s, $data);
            if ($quota['conferma'] && empty($d['conferma'])) {
                throw new TesseraSenzaQuota("{$quota['titolo']}: per emettere la tessera spunta «Rinnova lo stesso».");
            }

            $precedente = $tessere->filter(fn ($t) => $t->scadenza && $t->scadenza->lt($data))
                ->sortBy(fn ($t) => $t->scadenza->toDateString())->last();

            $t = new Tesseramento();
            $t->forceFill([
                'socio_id' => $s->id,
                'stagione_sportiva' => $data->year,
                'ente_affiliante' => ProfiloEnte::al($data)->ente_affiliazione ?: 'CSEN',
                'numero_tessera_csen' => $numero,
                'progressivo' => $n['progressivo'],
                'data_inserimento' => today()->toDateString(),
                'data_rilascio' => $data->toDateString(),
                'scadenza' => $scadenza->toDateString(),
                'pacchetto_tesseramento' => $tipo->nome,
                'tipo_id' => $tipo->id,
                'pacchetto_id' => $p->id,
                'precedente_id' => $precedente?->id,
                'origine' => 'emessa',
                'portale_csen_il' => ! empty($d['portale']) ? now() : null,
                'senza_quota' => $quota['conferma'],   // rinnovata in ritardo con «Rinnova lo stesso»
                'stato' => 'attivo',
                'note' => $d['note'] ?? null,
                'inserito_da' => $utente->id,
            ])->save();

            return $t;
        });
    }

    /**
     * Elimina una tessera emessa per errore (con motivazione nell'Audit
     * Log): il numero torna libero. Non se ne esiste una successiva.
     *
     * @throws \RuntimeException
     */
    public function elimina(Tesseramento $t, string $motivo): void
    {
        DB::transaction(function () use ($t, $motivo) {
            $t = Tesseramento::whereKey($t->id)->lockForUpdate()->firstOrFail();
            if (Tesseramento::where('precedente_id', $t->id)->exists()) {
                throw new \RuntimeException("La tessera {$t->numero_tessera_csen} è già rinnovata: elimina prima quella successiva.");
            }
            DB::table('gl_tesseramenti_qualifiche')->where('tesseramento_id', $t->id)->delete();
            Tesseramento::withoutEvents(fn () => $t->delete());
            AuditLogService::scrivi([
                'evento' => 'TESSERA_ELIMINATA',
                'operazione' => 'deleted',
                'entita_tipo' => 'Tesseramento',
                'entita_id' => $t->id,
                'livello_rischio' => 'MEDIUM',
                // Nel dettaglio (dentro l'hash) niente dati del socio: stanno nei valori
                'dettaglio' => "Tessera (riga n. {$t->id}) eliminata: il numero torna libero. Motivazione: {$motivo}",
                'old_values' => ['socio_id' => $t->socio_id, 'numero_tessera_csen' => $t->numero_tessera_csen,
                    'data_rilascio' => $t->data_rilascio->toDateString(), 'scadenza' => $t->scadenza?->toDateString(), 'origine' => $t->origine],
            ]);
        });
    }

    // ----------------------------------------------------------------
    // Riepilogo della pagina (TS-1)
    // ----------------------------------------------------------------

    /** Costo delle tessere emesse nell'anno (prezzo del pacchetto) e quota netta. */
    public static function costiAnno(int $anno): array
    {
        $emesse = Tesseramento::where('origine', 'emessa')->whereYear('data_rilascio', $anno)->with('pacchetto:id,prezzo_unitario')->get(['id', 'pacchetto_id']);
        $costo = round($emesse->sum(fn ($t) => (float) ($t->pacchetto?->prezzo_unitario ?? 0)), 2);
        $incassato = (float) Rinnovo::valido()->where('anno', $anno)->sum('importo');

        return ['emesse' => $emesse->count(), 'costo' => $costo, 'incassato' => $incassato, 'netta' => round($incassato - $costo, 2)];
    }
}
