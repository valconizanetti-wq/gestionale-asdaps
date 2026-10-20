<?php

namespace App\Services;

/**
 * Esito delle votazioni (v0.44.0, analisi-verbali-assemblee.md, «Regola di
 * tutte le votazioni», V17 e V18). Un solo punto di calcolo per la riunione
 * del CD e la consultazione telematica (e, dalla v0.45.0, l'Assemblea).
 *
 * Regola generale: la maggioranza si conta tra i votanti, favorevoli contro
 * contrari; gli astenuti restano fuori; in parità prevale il voto del
 * Presidente, se ha votato; senza nessun voto favorevole o contrario la
 * delibera non passa. Chi non partecipa per conflitto di interessi (art.
 * 16.7) non vota.
 *
 * Eccezione nel CD: l'esclusione di un socio passa con i favorevoli pari
 * almeno alla maggioranza assoluta dei componenti (art. 9.1 c).
 */
class Votazioni
{
    /**
     * @param array{favorevole?: int, contrario?: int, astenuto?: int, non_partecipa?: int} $conti
     * @param ?string $votoPresidente voto del Presidente (favorevole, contrario, astenuto, non_partecipa) o null
     * @return array{esito: string, testo: string}
     */
    public static function esito(string $tipo, array $conti, ?string $votoPresidente, int $componenti): array
    {
        $fav = (int) ($conti['favorevole'] ?? 0);
        $con = (int) ($conti['contrario'] ?? 0);
        $ast = (int) ($conti['astenuto'] ?? 0);
        $voti = self::riepilogo($fav, $con, $ast, (int) ($conti['non_partecipa'] ?? 0));

        if ($tipo === 'esclusione') {
            $servono = intdiv($componenti, 2) + 1;
            $ok = $fav >= $servono;

            return [
                'esito' => $ok ? 'approvata' : 'respinta',
                'testo' => ($ok ? 'Approvata' : 'Respinta') . ": {$voti}. Servono almeno {$servono} favorevoli su {$componenti} componenti (art. 9.1 c).",
            ];
        }

        if ($fav === 0 && $con === 0) {
            return ['esito' => 'respinta', 'testo' => "Non approvata: {$voti}; nessun voto favorevole o contrario."];
        }
        if ($fav !== $con) {
            $ok = $fav > $con;

            return ['esito' => $ok ? 'approvata' : 'respinta', 'testo' => ($ok ? 'Approvata' : 'Respinta') . ": {$voti}" . ($ast ? '; gli astenuti non entrano nel conto' : '') . '.'];
        }
        // Parità
        if (in_array($votoPresidente, ['favorevole', 'contrario'], true)) {
            $ok = $votoPresidente === 'favorevole';

            return [
                'esito' => $ok ? 'approvata' : 'respinta',
                'testo' => ($ok ? 'Approvata' : 'Respinta') . " con il voto del Presidente: {$voti}; in parità prevale il Presidente (art. 16.3).",
            ];
        }

        return ['esito' => 'respinta', 'testo' => "Non approvata: {$voti}; in parità, senza il voto del Presidente, la delibera non passa."];
    }

    /** Quorum della riunione del CD: maggioranza dei componenti (art. 16.3). */
    public static function quorumCd(int $componenti): int
    {
        return intdiv($componenti, 2) + 1;
    }

    /** «2 favorevoli, 0 contrari, 1 astenuto» (e chi non partecipa). */
    public static function riepilogo(int $fav, int $con, int $ast, int $np = 0): string
    {
        $parti = [
            $fav . ' ' . ($fav === 1 ? 'favorevole' : 'favorevoli'),
            $con . ' ' . ($con === 1 ? 'contrario' : 'contrari'),
        ];
        if ($ast) {
            $parti[] = $ast . ' ' . ($ast === 1 ? 'astenuto' : 'astenuti');
        }
        if ($np) {
            $parti[] = $np . ' non ' . ($np === 1 ? 'partecipa' : 'partecipano');
        }

        return implode(', ', $parti);
    }
}
