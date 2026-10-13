<?php

namespace App\Services;

use App\Models\GovernanceCd;
use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\Rinnovo;
use App\Models\Socio;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rinnovi quota (rinnovi-quota.md, v0.36.0; statuto 1.0 artt. 5, 8, 9).
 *
 * - Quota annuale entro il 31 gennaio; chi non ha versato non vota; dopo
 *   tre mesi (dal 1° maggio) decade, con presa d'atto del CD.
 * - Categoria dall'età al 1° gennaio dell'anno sociale: chi compie 18 anni
 *   durante l'anno paga da giovane fino al 31 dicembre (art. 5.3).
 * - Ricevuta numerata per anno di pagamento ("13/2027"), assegnata nella
 *   stessa transazione del rinnovo e del movimento di Prima Nota, con lock:
 *   niente doppioni (il Gestionale APS li calcolava senza lock).
 * - Un errore si corregge annullando (con motivazione) e registrando di
 *   nuovo: il numero annullato resta nel registro.
 */
class RinnoviQuota
{
    public const IN_REGOLA = 'ok';
    public const IN_ATTESA = 'attesa';
    public const SOSPESO = 'sospeso';
    public const DA_DICHIARARE_DECADUTO = 'decade';

    public const SITUAZIONI = [
        self::IN_REGOLA => ['In regola', 'ok'],
        self::IN_ATTESA => ['Da versare entro il 31/01', 'info'],
        self::SOSPESO => ['In ritardo · voto sospeso', 'warn'],
        self::DA_DICHIARARE_DECADUTO => ['Da dichiarare decaduto', 'ko'],
    ];

    /** Età minima per l'assenso del minore al tesseramento (D.Lgs. 36/2021, art. 16, c. 2). */
    public const ETA_ASSENSO = 14;

    public function __construct(private AnniContabili $anni)
    {
    }

    // ----------------------------------------------------------------
    // Regole
    // ----------------------------------------------------------------

    public static function termine(int $anno): Carbon
    {
        return Carbon::create($anno, 1, 31)->endOfDay();
    }

    /** Ultimo giorno prima della decadenza: tre mesi dopo il 31 gennaio. */
    public static function ultimoGiorno(int $anno): Carbon
    {
        return Carbon::create($anno, 4, 30)->endOfDay();
    }

    /** Situazione di un socio senza quota valida per l'anno, oggi. */
    public static function situazione(bool $versata, int $anno, ?Carbon $oggi = null): string
    {
        $oggi ??= now();

        return match (true) {
            $versata => self::IN_REGOLA,
            $oggi->lte(self::termine($anno)) => self::IN_ATTESA,
            $oggi->lte(self::ultimoGiorno($anno)) => self::SOSPESO,
            default => self::DA_DICHIARARE_DECADUTO,
        };
    }

    /** Giovane se minorenne al 1° gennaio dell'anno sociale (statuto art. 5.3). */
    public static function categoria(Socio $socio, int $anno): string
    {
        if ($socio->data_nascita) {
            return $socio->data_nascita->copy()->addYears(18)->gt(Carbon::create($anno, 1, 1)) ? 'giovane' : 'ordinario';
        }

        return in_array($socio->tipo, ['giovane', 'minorenne'], true) ? 'giovane' : 'ordinario';
    }

    public static function importo(string $categoria): float
    {
        return Quota::importo($categoria === 'giovane' ? 'minorenne' : 'ordinario');
    }

    /** Età compiuta in una data. */
    public static function eta(Socio $socio, \DateTimeInterface $alla): ?int
    {
        return $socio->data_nascita ? (int) $socio->data_nascita->diffInYears($alla) : null;
    }

    /** Serve l'assenso del minore: minorenne di almeno 14 anni alla data del versamento. */
    public static function serveAssenso(Socio $socio, \DateTimeInterface $data): bool
    {
        $eta = self::eta($socio, $data);

        return $eta !== null && $eta >= self::ETA_ASSENSO && $eta < 18;
    }

    /** Primo anno sociale non ancora versato, a partire da quello in corso. */
    public function annoProposto(Socio $socio, ?Carbon $oggi = null): int
    {
        $oggi ??= now();
        $versati = Rinnovo::valido()->where('socio_id', $socio->id)->pluck('anno')->map(fn ($a) => (int) $a)->all();
        $anno = $oggi->year;
        while (in_array($anno, $versati, true)) {
            $anno++;
        }

        return $anno;
    }

    /**
     * Detrazione per lo sport dei ragazzi (art. 15, c. 1, lett. i-quinquies
     * TUIR; D.M. 28/03/2007): solo se alla data del pagamento l'ente è una
     * sportiva dilettantistica iscritta al RASD, per un socio tra 5 e 18
     * anni, con pagamento tracciabile. La B.A.S. non è iscritta al RASD
     * (Paolo, 06/10/2026): fino al 2027 mai.
     */
    public static function detraibile(Rinnovo $r): bool
    {
        $socio = $r->socio;
        $eta = $socio ? self::eta($socio, Carbon::create($r->anno, 1, 1)) : null;

        return in_array($r->metodo_pagamento, ['bonifico', 'carta'], true)   // tracciabile
            && $eta !== null && $eta >= 4 && $eta < 18   // 5–18 anni nel corso dell'anno
            && ProfiloEnte::al($r->data_pagamento)->iscrittoRasd();
    }

    /** "Paolo Zanetti" dal CD in carica (carica che contiene "Tesoriere"), se c'è. */
    public static function tesoriere(): ?string
    {
        $carica = GovernanceCd::where('in_carica', true)->where('carica', 'like', '%Tesorier%')->with('socio')->first();

        return $carica?->socio ? trim($carica->socio->nome . ' ' . $carica->socio->cognome) : null;
    }

    // ----------------------------------------------------------------
    // Registrazione
    // ----------------------------------------------------------------

    /**
     * Registra la quota: rinnovo con ricevuta numerata, movimento di Prima
     * Nota (entrata, «Quote associative», cassa o banca), quota_scadenza
     * del socio. Tutto in una transazione; PDF ed email dopo (chiamante).
     *
     * @param array{anno: int, importo: float|string, data_pagamento: string, metodo: string,
     *              versato_da_nome?: ?string, versato_da_cf?: ?string, assenso?: bool, note?: ?string} $d
     *
     * @throws \RuntimeException con un messaggio per l'utente
     */
    public function registra(Socio $socio, array $d, User $utente, ?string $descrizioneMovimento = null): Rinnovo
    {
        $data = Carbon::parse($d['data_pagamento'])->startOfDay();
        // Quota incassata prima dell'avvio della contabilità nel gestionale
        // (2027 per la Compagnia): rinnovo e ricevuta sì, movimento no, perché
        // l'incasso è nel vecchio gestionale. Un movimento del 2026 farebbe del
        // 2026 il primo anno contabile e il 2027 perderebbe i saldi di apertura
        // (prima-nota.md §3; revisione v0.36.0).
        $senzaMovimento = $data->year < self::annoAvvio();
        if (! $senzaMovimento && $this->anni->chiuso($data->year)) {
            throw new \RuntimeException("L'anno {$data->year} della Prima Nota è chiuso: la quota non si registra con questa data.");
        }
        $categoriaId = PrimaNotaCategoria::where('codice', PrimaNotaCategoria::QUOTE)->value('id');
        if (! $categoriaId) {
            throw new \RuntimeException('Manca la categoria «Quote associative» della Prima Nota: rilanciare PrimaNotaCategoriaSeeder.');
        }

        return DB::transaction(function () use ($socio, $d, $utente, $data, $categoriaId, $descrizioneMovimento, $senzaMovimento) {
            // Lock sul socio: due registrazioni insieme non creano due quote valide.
            $s = Socio::whereKey($socio->id)->lockForUpdate()->firstOrFail();
            if (Rinnovo::valido()->where('socio_id', $s->id)->where('anno', $d['anno'])->exists()) {
                throw new \RuntimeException("La quota {$d['anno']} di {$s->cognome} {$s->nome} è già registrata.");
            }

            [$annoRic, $progressivo] = $this->prossimoNumero($data->year);
            $importo = round((float) $d['importo'], 2);

            $r = new Rinnovo();
            $r->forceFill([
                'socio_id' => $s->id,
                'anno' => (int) $d['anno'],
                'importo' => $importo,
                'categoria' => self::categoria($s, (int) $d['anno']),
                'data_pagamento' => $data->toDateString(),
                'metodo_pagamento' => $d['metodo'],
                'versato_da_nome' => $d['versato_da_nome'] ?? null ?: null,
                'versato_da_cf' => isset($d['versato_da_cf']) && $d['versato_da_cf'] !== '' ? strtoupper($d['versato_da_cf']) : null,
                'assenso_minore_il' => ! empty($d['assenso']) ? now() : null,
                'assenso_minore_da' => ! empty($d['assenso']) ? $utente->id : null,
                'numero_ricevuta' => "{$progressivo}/{$annoRic}",
                'anno_ricevuta' => $annoRic,
                'progressivo' => $progressivo,
                'ricevuta_stato' => 'non_generata',
                'note' => trim(($d['note'] ?? '') . ($senzaMovimento
                    ? ' Incassata prima dell\'avvio della contabilità nel gestionale: il movimento è nel vecchio gestionale.' : '')) ?: null,
                'registrato_da' => $utente->id,
            ])->save();

            if ($senzaMovimento) {
                $this->aggiornaScadenza($s);

                return $r;
            }

            $this->anni->assicura($data->year);
            $m = new PrimaNota();
            $m->forceFill([
                'data' => $data->toDateString(),
                'tipo' => 'entrata',
                'conto' => Rinnovo::contoPer($d['metodo']),
                'importo' => $importo,
                'descrizione' => $descrizioneMovimento ?? "Quota associativa {$d['anno']} – {$s->cognome} {$s->nome}",
                'categoria_id' => $categoriaId,
                'socio_id' => $s->id,
                'rinnovo_id' => $r->id,
                'soggetto' => $r->versato_da_nome ?: trim("{$s->nome} {$s->cognome}"),
                'tipo_documento' => 'ricevuta',
                'numero_documento' => $r->numero_ricevuta,
                'data_documento' => $data->toDateString(),
                'registrato_da' => $utente->id,
            ])->save();

            $this->aggiornaScadenza($s);

            return $r;
        });
    }

    /**
     * Prossimo numero di ricevuta dell'anno: dentro la transazione, con lock
     * sulle righe dell'anno; il vincolo unico (anno_ricevuta, progressivo)
     * resta come rete di sicurezza.
     *
     * @return array{0: int, 1: int}
     */
    public function prossimoNumero(int $anno): array
    {
        $max = (int) Rinnovo::where('anno_ricevuta', $anno)->lockForUpdate()->max('progressivo');

        return [$anno, $max + 1];
    }

    /**
     * Primo anno della contabilità nel gestionale: impostazione
     * contabilita_anno_avvio (2027), o un anno contabile già aperto prima
     * (es. durante il collaudo).
     */
    public static function annoAvvio(): int
    {
        $avvio = (int) \App\Models\Impostazione::get('contabilita_anno_avvio', now()->year);
        $primo = \App\Models\AnnoContabile::min('anno');

        return $primo ? min((int) $primo, $avvio) : $avvio;
    }

    /** quota_scadenza del socio: 31/12 dell'ultimo anno con una quota valida. */
    public function aggiornaScadenza(Socio $socio): void
    {
        $ultimo = Rinnovo::valido()->where('socio_id', $socio->id)->max('anno');
        $socio->forceFill(['quota_scadenza' => $ultimo ? Carbon::create((int) $ultimo, 12, 31)->toDateString() : null])->save();
    }

    // ----------------------------------------------------------------
    // Annullamento
    // ----------------------------------------------------------------

    /**
     * Annulla la ricevuta: resta nel registro come annullata, il movimento
     * esce dalla Prima Nota (su anno chiuso: correzione motivata e nuova
     * fotografia dei saldi, come in Prima Nota), la quota_scadenza del socio
     * si ricalcola.
     *
     * @throws \RuntimeException
     */
    public function annulla(Rinnovo $rinnovo, string $motivo, User $utente): Rinnovo
    {
        return DB::transaction(function () use ($rinnovo, $motivo, $utente) {
            $r = Rinnovo::whereKey($rinnovo->id)->lockForUpdate()->firstOrFail();
            if ($r->isAnnullato()) {
                throw new \RuntimeException('La ' . $r->riferimento() . ' è già annullata.');
            }

            $r->forceFill(['annullata_il' => now(), 'annullata_da' => $utente->id, 'annullamento_motivo' => $motivo])->save();

            $m = PrimaNota::where('rinnovo_id', $r->id)->first();
            if ($m) {
                $anno = $m->annoMovimento();
                $chiuso = $this->anni->chiuso($anno);
                if ($chiuso) {
                    AuditLogService::scrivi([
                        'evento' => 'PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO',
                        'entita_tipo' => 'PrimaNota',
                        'entita_id' => $m->id,
                        'anno' => $anno,
                        'livello_rischio' => 'HIGH',
                        'dettaglio' => "Movimento n. {$m->id} dell'anno chiuso {$anno}: eliminazione per annullamento della ricevuta {$r->numero_ricevuta}. Motivazione: {$motivo}",
                    ]);
                }
                $m->delete();
                if ($chiuso) {
                    $this->anni->aggiornaChiusura($anno);
                }
            }

            if ($r->socio) {
                $this->aggiornaScadenza($r->socio);
            }

            return $r;
        });
    }
}
