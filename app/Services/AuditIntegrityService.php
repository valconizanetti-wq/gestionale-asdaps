<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * AuditIntegrityService — formula dell'hash e verifica della catena di
 * gl_audit_log (audit-log.md). Ripreso dal Gestionale APS.
 *
 * FORMULA (rigaPerHash): l'ordine e i campi sono IMMODIFICABILI dopo il
 * primo record in produzione, altrimenti tutta la catena storica
 * risulterebbe alterata.
 *
 * SCELTA VOLUTA: old_values, new_values e campi_modificati NON entrano
 * nell'hash. Contengono dati personali (codice fiscale, contatti…) che,
 * alla pseudonimizzazione di un socio, devono poter essere oscurati anche
 * nel registro di audit senza rompere la catena (GDPR, art. 17 e 25).
 * La catena garantisce CHI ha fatto COSA e QUANDO (evento, entità,
 * utente, data, dettaglio); i valori sono dettaglio oscurabile.
 *
 * Verifica PER RIGA: (a) contenuto — l'hash corrisponde alla formula
 * applicata al previous_hash salvato; (b) collegamento — il previous_hash
 * corrisponde all'hash reale della riga precedente.
 */
class AuditIntegrityService
{
    /**
     * Righe con hash non riproducibile ma verificate a mano (vedi Gestionale
     * APS). Aggiungere una voce SOLO dopo aver verificato che il collegamento
     * con la riga precedente è intatto. Nel derivato: nessuna.
     *
     * @var array<int, string>
     */
    private const ECCEZIONI_FORMULA_NOTE = [];

    public static function eccezioniNote(): array
    {
        return self::ECCEZIONI_FORMULA_NOTE;
    }

    /** Formula canonica della stringa di una riga. Unica copia nel codice. */
    public static function rigaPerHash(object $r): string
    {
        return implode('|', [
            (string) $r->id,
            (string) ($r->created_at ?? ''),
            (string) ($r->evento ?? ''),
            (string) ($r->operazione ?? ''),
            (string) ($r->entita_tipo ?? ''),
            (string) ($r->modello ?? ''),
            (string) ($r->entita_id ?? ''),
            (string) ($r->utente_nome ?? ''),
            (string) ($r->ip_address ?? ''),
            (string) ($r->livello_rischio ?? ''),
            (string) ($r->dettaglio ?? ''),
        ]);
    }

    public static function hashRiga(object $r, ?string $previousHash): string
    {
        return hash('sha256', ($previousHash ?? '') . '|' . self::rigaPerHash($r));
    }

    /**
     * Verifica l'intera catena. $annoFiltro filtra solo le anomalie
     * restituite: la catena si ricalcola sempre per intero.
     */
    public static function verificaCatena(?int $annoFiltro = null): array
    {
        $vuoto = [
            'primo_anello' => null, 'records_totali' => 0, 'record_pre_catena' => 0,
            'hash_finale' => null, 'gap_count' => 0, 'reset_automatici' => [],
            'tagli_archiviazione' => [], 'anomalie_contenuto' => [],
            'anomalie_collegamento' => [], 'anomalie' => [], 'corretti' => 0,
        ];

        $primoAnello = DB::table('gl_audit_log')->whereNotNull('hash')->orderBy('id')->value('id');
        if ($primoAnello === null) {
            return $vuoto;
        }

        // Hash delle righe eliminate da archiviazioni: un collegamento
        // interrotto verso una di esse è un taglio atteso, non un'anomalia.
        $hashArchiviati = [];
        try {
            foreach (DB::table('gl_audit_archivi')->pluck('hash_righe_eliminate') as $json) {
                foreach ((json_decode($json, true) ?: []) as $h) {
                    $hashArchiviati[$h] = true;
                }
            }
        } catch (\Exception $e) {
            // Nessuna archiviazione ancora eseguita o colonna assente.
        }

        $anomalieContenuto = [];
        $anomalieCollegamento = [];
        $gapCount = 0;
        $resetAutomatici = [];
        $tagliArchiviazione = [];
        $corretti = 0;
        $lastRealHash = null;
        $primaRiga = true;
        $totali = 0;

        foreach (DB::table('gl_audit_log')->where('id', '>=', $primoAnello)->orderBy('id')->cursor() as $r) {
            $totali++;
            if ($r->hash === null) {
                $gapCount++;
                continue;
            }

            $collegamentoOk = true;
            if (! $primaRiga) {
                if ($r->previous_hash === null) {
                    $resetAutomatici[] = $r->id;
                } elseif ($r->previous_hash !== $lastRealHash) {
                    if (isset($hashArchiviati[$r->previous_hash])) {
                        $tagliArchiviazione[] = $r->id;
                    } else {
                        $collegamentoOk = false;
                        $anomalieCollegamento[] = self::anomalia($r) + [
                            'previous_hash_atteso' => $lastRealHash,
                            'previous_hash_trovato' => $r->previous_hash,
                        ];
                    }
                }
            }

            $hashAtteso = self::hashRiga($r, $r->previous_hash);
            $contenutoOk = $hashAtteso === $r->hash || isset(self::ECCEZIONI_FORMULA_NOTE[$r->id]);
            if (! $contenutoOk) {
                $anomalieContenuto[] = self::anomalia($r) + ['hash_atteso' => $hashAtteso, 'hash_trovato' => $r->hash];
            }

            if ($collegamentoOk && $contenutoOk) {
                $corretti++;
            }
            $lastRealHash = $r->hash;
            $primaRiga = false;
        }

        $filtra = fn (array $lista) => $annoFiltro === null
            ? $lista
            : array_values(array_filter($lista, fn ($a) => (int) $a['anno'] === $annoFiltro));

        return [
            'primo_anello'          => $primoAnello,
            'records_totali'        => $totali,
            'record_pre_catena'     => DB::table('gl_audit_log')->where('id', '<', $primoAnello)->count(),
            'hash_finale'           => $lastRealHash,
            'gap_count'             => $gapCount,
            'reset_automatici'      => $resetAutomatici,
            'tagli_archiviazione'   => $tagliArchiviazione,
            'anomalie_contenuto'    => $filtra($anomalieContenuto),
            'anomalie_collegamento' => $filtra($anomalieCollegamento),
            'anomalie'              => $filtra(array_merge($anomalieContenuto, $anomalieCollegamento)),
            'corretti'              => $corretti,
        ];
    }

    private static function anomalia(object $r): array
    {
        return [
            'id' => $r->id,
            'anno' => $r->anno,
            'created_at' => $r->created_at,
            'evento' => $r->evento ?? ($r->operazione ?? '--'),
            'utente_nome' => $r->utente_nome,
            'livello_rischio' => $r->livello_rischio ?? 'LOW',
        ];
    }
}
