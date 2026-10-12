<?php

namespace App\Scontrini;

/**
 * Lettura del testo di uno scontrino (OCR) in dati proposti: totale, data,
 * partita IVA, fornitore (ragione sociale, indirizzo, comune), come è stato
 * pagato e righe degli articoli. Tutto è una PROPOSTA da confermare
 * (analisi-scontrini.md §3.3, §11): niente si applica da solo.
 *
 * Regole messe a punto sui 14 scontrini veri mandati da Paolo il
 * 12/10/2026 (documenti commerciali italiani, analisi §11).
 */
class LettoreScontrino
{
    /** Righe che chiudono l'elenco degli articoli o non sono articoli. */
    private const NON_ARTICOLI = '/SUBTOT|TOTALE|COMPLESS|DI CUI|IVA\s*$|PAGAMENTO|PAGATO|CONTANTE|RESTO|NUM\s*ARTICOLI|NUM\s*CASSA|^SCONTO TOT/u';

    private const FORME = '/\b(S\.?\s?R\.?\s?L\.?\s?S?\.?|S\.?\s?P\.?\s?A\.?|S\.?\s?N\.?\s?C\.?|S\.?\s?A\.?\s?S\.?|SRLS|SRL|SPA|SNC|SAS)\b/iu';

    /**
     * @return array{totale: ?string, totale_fonte: ?string, totale_sicuro: bool, data: ?string, data_avviso: ?string,
     *               partita_iva: ?string, partita_iva_valida: bool, fornitore: ?string, indirizzo: ?string, comune: ?string,
     *               conto: ?string, righe: list<array{descrizione: string, importo: string, dettaglio: ?string}>,
     *               righe_somma: ?string, avvisi: list<string>}
     */
    public function leggi(string $testo, ?\DateTimeInterface $oggi = null): array
    {
        $oggi ??= new \DateTimeImmutable('today');
        $righe = array_values(array_filter(array_map(fn ($r) => trim(preg_replace('/[ \x{00A0}]+/u', ' ', $r)), self::normalizza($testo)), fn ($r) => $r !== ''));
        $maiuscole = array_map(fn ($r) => mb_strtoupper($r), $righe);
        $avvisi = [];

        [$totale, $fonte, $sicuro] = $this->totale($maiuscole);
        [$data, $avvisoData] = $this->data($righe, $oggi);
        [$piva, $pivaValida] = $this->partitaIva($maiuscole);
        $fornitore = $this->fornitore($righe, $maiuscole);
        $articoli = $this->articoli($righe, $maiuscole);
        $somma = $articoli ? number_format(array_sum(array_map(fn ($a) => (float) $a['importo'], $articoli)), 2, '.', '') : null;

        if ($totale !== null && $somma !== null && $somma !== $totale) {
            $avvisi[] = 'Le righe lette sommano ' . self::it($somma) . ' €, il totale è ' . self::it($totale) . ' €: alcune righe vanno corrette.';
        }
        if (! $sicuro && $totale !== null) {
            $avvisi[] = 'Totale incerto: controllalo.';
        }
        if ($avvisoData) {
            $avvisi[] = $avvisoData;
        }
        if ($piva && ! $pivaValida) {
            $avvisi[] = 'Partita IVA letta male (la cifra di controllo non torna): controllala.';
        }

        return [
            'totale' => $totale, 'totale_fonte' => $fonte, 'totale_sicuro' => $sicuro,
            'data' => $data, 'data_avviso' => $avvisoData,
            'partita_iva' => $piva, 'partita_iva_valida' => $pivaValida,
            'fornitore' => $fornitore['nome'], 'indirizzo' => $fornitore['indirizzo'], 'comune' => $fornitore['comune'],
            'conto' => $this->conto($maiuscole),
            'righe' => $articoli, 'righe_somma' => $somma,
            'avvisi' => $avvisi,
        ];
    }

    // ── Testo di OCR.space ───────────────────────────────────────────────

    /**
     * Testo di OCR.space con isTable=true (analisi-scontrini.md §11): le
     * celle di una riga sono separate da tabulazioni. Nell'intestazione
     * (prima di «DOCUMENTO COMMERCIALE» / «DESCRIZIONE») le celle sono pezzi
     * diversi (ragione sociale, via, telefono) e diventano righe; negli
     * articoli e nei totali restano sulla stessa riga (descrizione, IVA,
     * importo). Più due errori tipici: «4, 41» → «4,41» e «4-00» → «4,00».
     *
     * @return list<string>
     */
    private static function normalizza(string $testo): array
    {
        $fuori = [];
        $testa = true;
        foreach (preg_split('/\R/u', $testo) as $r) {
            $r = preg_replace('/(\d),\s(\d{2})(?!\d)/u', '$1,$2', $r);
            $r = preg_replace('/(^|\t)(\d{1,4})-(\d{2})(?=\t|\s*$)/u', '$1$2,$3', $r);
            if ($testa && preg_match('/DOCUMENTO|COMMERCIAL|DESCRIZ/iu', $r)) {
                $testa = false;
            }
            if ($testa) {
                array_push($fuori, ...array_map('trim', explode("\t", $r)));
            } else {
                $fuori[] = trim(str_replace("\t", ' ', $r));
            }
        }

        return $fuori;
    }

    // ── Importi ──────────────────────────────────────────────────────────

    /** Importo in fondo alla riga («9,00», «9.0», «-0,99», «1.234,50»), normalizzato «9.00». */
    public static function importoFinale(string $riga): ?string
    {
        if (! preg_match('/(-?\s?\d{1,3}(?:\.\d{3})*[.,]\d{1,2}|-?\d{1,6}[.,]\d{1,2})\s*[€E]?\s*$/u', $riga, $m)) {
            return null;
        }
        $s = str_replace(' ', '', $m[1]);
        if (preg_match('/^-?\d{1,3}(\.\d{3})+,\d{1,2}$/', $s)) {
            $s = str_replace('.', '', $s);
        }
        $s = str_replace(',', '.', $s);

        return number_format((float) $s, 2, '.', '');
    }

    /**
     * Totale per «voto»: le righe che lo ripetono (totale complessivo, importo
     * pagato, pagamento elettronico, carta, contante senza resto) devono
     * concordare. Sicuro se almeno due fonti danno lo stesso importo.
     *
     * @return array{0: ?string, 1: ?string, 2: bool}
     */
    private function totale(array $maiuscole): array
    {
        $resto = (bool) preg_grep('/\bRESTO\b.*[1-9]/u', $maiuscole);
        $fonti = [
            'totale complessivo' => '/(T\s?O\s?T\s?A?\s?L?\s?E?\.?\s*)?C\s?O\s?M\s?P?\s?L\s?E\s?S\s?S\s?I\s?V|OTALE\s+COMPL|^TOTALE(\s+EURO)?\s+-?\d/u',
            'importo pagato' => '/M\s?P\s?O\s?R\s?T\s?O\s+P\s?A\s?[GS]\s?A\s?T\s?O/u',
            'pagamento elettronico' => '/ELETTRONIC|CARTA\s+DI\s+CREDITO|BANCOMAT|\bPOS\b/u',
            'contante' => '/CONTANTE/u',
        ];
        $voti = [];
        foreach ($maiuscole as $r) {
            if (preg_match('/SUBTOT|DI\s+CUI|IVA\s+\d|RESTO|NON\s+RISCOSS/u', $r)) {
                continue;
            }
            foreach ($fonti as $nome => $re) {
                if (preg_match($re, $r) && ($imp = self::importoFinale($r)) !== null && (float) $imp > 0) {
                    if ($nome === 'contante' && $resto) {
                        continue;   // con il resto il contante dato è più del totale
                    }
                    $voti[$imp][] = $nome;
                    break;
                }
            }
        }
        if (! $voti) {
            return [null, null, false];
        }
        uksort($voti, function ($a, $b) use ($voti) {
            $pa = count($voti[$a]) * 10 + (in_array('totale complessivo', $voti[$a], true) ? 5 : 0);
            $pb = count($voti[$b]) * 10 + (in_array('totale complessivo', $voti[$b], true) ? 5 : 0);

            return $pb <=> $pa;
        });
        $imp = array_key_first($voti);

        return [$imp, $voti[$imp][0], count($voti[$imp]) >= 2];
    }

    // ── Data ─────────────────────────────────────────────────────────────

    /** @return array{0: ?string, 1: ?string} data Y-m-d e avviso */
    private function data(array $righe, \DateTimeInterface $oggi): array
    {
        foreach ($righe as $r) {
            if (! preg_match('/(?<![\d.])(\d{1,2})\s?[\/\-.]\s?(\d{1,2})\s?[\/\-.]\s?(\d{4}|\d{2})(?![\d])/u', $r, $m)) {
                continue;
            }
            [$g, $mm, $a] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            $a = $a < 100 ? 2000 + $a : $a;
            if (! checkdate($mm, $g, $a)) {
                continue;
            }
            $d = sprintf('%04d-%02d-%02d', $a, $mm, $g);
            $limite = (new \DateTimeImmutable($oggi->format('Y-m-d')))->modify('+1 day')->format('Y-m-d');
            if ($d > $limite) {
                return [$d, 'La data letta è nel futuro: controllala.'];
            }
            $antica = (new \DateTimeImmutable($oggi->format('Y-m-d')))->modify('-1 year')->format('Y-m-d');
            // Prima cifra tagliata dalla foto (es. «5/10/2026»): la data può essere sbagliata
            $taglio = preg_match('/^\s*\d[\/\-.]/u', $r) && $m[0][0] === $r[0];

            return [$d, $d < $antica ? 'Scontrino di più di un anno fa: controlla la data.' : ($taglio ? 'Il giorno potrebbe essere tagliato dalla foto: controllalo.' : null)];
        }

        return [null, null];
    }

    // ── Partita IVA ──────────────────────────────────────────────────────

    /** @return array{0: ?string, 1: bool} */
    private function partitaIva(array $maiuscole): array
    {
        $candidata = null;
        foreach ($maiuscole as $r) {
            if (preg_match('/(?:P\.?\s*IVA|PARTITA\s+IVA|P\.?\s*I\.|\bPI)\s*[.:]?\s*(?:IT)?\s*([0-9OIL]{11})\b/u', $r, $m)) {
                $n = strtr($m[1], ['O' => '0', 'I' => '1', 'L' => '1']);
                if (self::partitaIvaValida($n)) {
                    return [$n, true];
                }
                $candidata ??= $n;
            }
        }
        // «IVA 01452300567» (la «P.» persa): solo se la cifra di controllo torna
        foreach ($maiuscole as $r) {
            if (preg_match('/\bIVA\s*[.:]?\s*(\d{11})\b/u', $r, $m) && self::partitaIvaValida($m[1])) {
                return [$m[1], true];
            }
        }

        return [$candidata, false];
    }

    /** Cifra di controllo della partita IVA italiana. */
    public static function partitaIvaValida(string $n): bool
    {
        if (! preg_match('/^\d{11}$/', $n) || $n === '00000000000') {
            return false;
        }
        $s = 0;
        for ($i = 0; $i < 10; $i++) {
            $c = (int) $n[$i];
            if ($i % 2 === 1) {
                $c *= 2;
                $c = $c > 9 ? $c - 9 : $c;
            }
            $s += $c;
        }

        return (10 - $s % 10) % 10 === (int) $n[10];
    }

    // ── Fornitore ────────────────────────────────────────────────────────

    /** @return array{nome: ?string, indirizzo: ?string, comune: ?string} */
    private function fornitore(array $righe, array $maiuscole): array
    {
        // Intestazione: le righe prima di «DOCUMENTO COMMERCIALE» (o delle prime 8)
        $fine = 8;
        foreach ($maiuscole as $i => $r) {
            if (preg_match('/DOCUMENTO|COMMERCIAL|DESCRIZ/u', $r)) {
                $fine = $i;
                break;
            }
        }
        $testa = array_slice($righe, 0, min($fine, 9));
        $indirizzo = $comune = null;
        $nome = [];
        $finitoNome = false;
        foreach ($testa as $r) {
            $u = mb_strtoupper($r);
            // Indirizzo anche dopo un logo («Spin P.V. Via Igino Gini, snc»: snc = senza numero civico)
            $eIndirizzo = preg_match('/(^|\s)(P\.?\s?V\.?\s+)?(VIA|V\.LE|VIALE|CORSO|C\.SO|PIAZZA|P\.ZZA|LOC\.|LOCALIT|FRAZ\.|STRADA)\s/u', $u);
            $eComune = preg_match('/\b\d{5}\b/u', $u) || preg_match('/\(\s?[A-Z]{2}\s?\)|-\s?[A-Z]{2}\s?-|\s(TR|PG|VT|RM|SI|AR|FI|MI)$/u', $u);
            $eAltro = preg_match('/P\.\s?IVA|\bP\.?\s?IVA\b|PARTITA\s+IVA|^TEL\b|\bTEL\.|TELEFONO|WWW|C\.F\.|\bPI\d|^P\.?\s?I\.|\bCASSA\b/u', $u);
            if ($eIndirizzo) {
                $indirizzo ??= preg_replace('/^.*?(?=(P\.?\s?V\.?\s+)?(VIA|V\.LE|VIALE|CORSO|C\.SO|PIAZZA|P\.ZZA|LOC\.|LOCALIT|FRAZ\.|STRADA)\s)/iu', '', $r);
                $indirizzo = preg_replace('/^P\.?\s?V\.?\s+/iu', '', $indirizzo);
                $finitoNome = true;
            }
            if ($eComune && ! $eAltro) {
                if (preg_match('/^(.+?)\s*\(\s?[A-Z]{2}\s?\)/u', $r, $m) && ! preg_match('/\d{5}|VIA|CORSO/iu', $m[1])) {
                    $comune ??= $m[1];   // «Orvieto (TR) - cdc 3523», «CASTEL GIORGIO (TR)»
                } elseif (preg_match('/\b\d{5}\b\s*-?\s*(.+)$/u', $r, $m) && preg_match('/\p{L}{3}/u', $m[1]) && ! preg_match('/^(VIA|V\.|CORSO)/iu', $m[1])) {
                    $comune ??= trim(preg_replace('/\s*\(.*$/u', '', $m[1]), ' -');
                } elseif (! $eIndirizzo && ! preg_match('/\d{5}/', $u)) {
                    $comune ??= trim(preg_replace('/[-()]/u', ' ', $r));
                }
                $finitoNome = true;
            }
            if ($eAltro) {
                $finitoNome = true;
            }
            if (! $finitoNome && preg_match('/\p{L}{2,}/u', $r)) {
                $nome[] = $r;
            }
        }
        // Una forma giuridica in testa vince (loghi come «EURO Spin» davanti),
        // anche se OCR.space l'ha messa dopo la via («GEAFLOR S.N.C. & C.»)
        foreach (array_merge($nome, $testa) as $r) {
            if (preg_match(self::FORME, $r) && ! preg_match('/\b(VIA|CORSO)\b/iu', $r)) {
                $nome = [preg_replace('/^(EURO\s+|SPIN\s+)/iu', '', $r)];
                break;
            }
        }
        $nome = array_slice($nome, 0, 2);
        if (count($nome) === 2 && ! preg_match('/^DI\s/iu', $nome[1]) && preg_match(self::FORME, $nome[0])) {
            $nome = [$nome[0]];
        }
        $nome = $nome ? trim(implode(' ', $nome)) : null;
        // Ultima risorsa: il sito stampato («www.eurospin.it» → «Eurospin»)
        if ($nome === null) {
            foreach ($righe as $r) {
                if (preg_match('/www\.([a-z0-9-]{3,})\./iu', $r, $m)) {
                    $nome = mb_convert_case($m[1], MB_CASE_TITLE);
                    break;
                }
            }
        }

        return [
            'nome' => $nome ? mb_substr(rtrim(preg_replace('/\s+a\s+socio\s+unico$/iu', '', $nome), ' .'), 0, 200) : null,
            'indirizzo' => $indirizzo,
            'comune' => $comune ? mb_convert_case(mb_strtolower(trim(preg_replace('/\s+-?(TR|PG|VT|RM|SI|AR|FI|MI)-?$/u', '', trim($comune)))), MB_CASE_TITLE) : null,
        ];
    }

    // ── Come è stato pagato ──────────────────────────────────────────────

    private function conto(array $maiuscole): ?string
    {
        foreach ($maiuscole as $r) {
            if (preg_match('/ELETTRONIC|CARTA\s+DI|BANCOMAT|\bPOS\b/u', $r) && ($i = self::importoFinale($r)) !== null && (float) $i > 0) {
                return 'banca';
            }
        }
        foreach ($maiuscole as $r) {
            if (preg_match('/CONTANT/u', $r) && ($i = self::importoFinale($r)) !== null && (float) $i > 0) {
                return 'cassa';
            }
        }

        return null;
    }

    // ── Righe degli articoli ─────────────────────────────────────────────

    /** @return list<array{descrizione: string, importo: string, dettaglio: ?string}> */
    private function articoli(array $righe, array $maiuscole): array
    {
        $inizio = null;
        foreach ($maiuscole as $i => $r) {
            if (preg_match('/ESCRIZ|DESCRI/u', $r)) {
                $inizio = $i + 1;
                break;
            }
        }
        if ($inizio === null) {
            return [];
        }
        $out = [];
        $dettaglio = null;
        for ($i = $inizio; $i < count($righe); $i++) {
            $u = $maiuscole[$i];
            if (preg_match('/SUBTOT|COMPLESS|^T\s?O\s?T|^TOTALE|DI CUI/u', $u)) {
                break;
            }
            // Quantità: «2x 2,50», «150 x 0,09», «2 PZ x 1,89 EUR/PZ», «Cad 1,89 Pz. 2», «0,8 X 10,00»
            if (preg_match('/^\*?\s*\d+([.,]\d+)?\s*(PZ\s*)?X\s*\d|^CAD\s+\d|EUR\/PZ/u', $u) && ! preg_match('/\p{L}{4,}.*\d+[.,]\d{2}$/u', preg_replace('/EUR\/PZ|PZ|CAD/u', '', $u))) {
                if ($out && preg_match('/^CAD/u', $u)) {
                    $out[count($out) - 1]['dettaglio'] = $righe[$i];
                } else {
                    $dettaglio = $righe[$i];
                }
                continue;
            }
            if (preg_match('/^[\w-]*\d{3,}\w*\*{2,}$|^-?\d{3}\w+\*+/u', $u)) {
                continue;   // codici della cassa
            }
            $imp = self::importoFinale($righe[$i]);
            if ($imp === null || ! preg_match('/\p{L}{2,}/u', $righe[$i])) {
                continue;
            }
            $desc = trim(preg_replace('/\s*(\d{1,2}([.,]\d{2})?\s?%|NS\*?|-?\d[\d.,]*\s*[€E]?)\s*$/u', '', $righe[$i]));
            $desc = trim(preg_replace('/\s*(\d{1,2}([.,]\d{2})?\s?%|NS\*?)\s*$/u', '', $desc));
            $desc = trim(preg_replace('/^IVA\s+\d+\s*$/iu', '', $desc)) ?: 'Riga senza descrizione';
            $out[] = ['descrizione' => $desc, 'importo' => $imp, 'dettaglio' => $dettaglio];
            $dettaglio = null;
        }

        return $out;
    }

    private static function it(string $n): string
    {
        return number_format((float) $n, 2, ',', '.');
    }
}
