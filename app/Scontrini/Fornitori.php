<?php

namespace App\Scontrini;

use App\Models\Soggetto;
use Illuminate\Support\Collection;

/**
 * Riconoscimento del fornitore letto da uno scontrino tra quelli già in
 * elenco (analisi-scontrini.md §9, §12; tavole SN-2, SN-3):
 * 1. partita IVA uguale → «già in elenco» (sicuro);
 * 2. nome uguale senza badare a maiuscole, punteggiatura e forme come
 *    «S.R.L.», «& C.» → trovato; se in elenco non ha partita IVA, gli si
 *    aggiunge quella letta;
 * 3. altrimenti «Forse è uno di questi?»: partita IVA quasi uguale (al più
 *    tre cifre diverse: OCR.space ne ha sbagliate tre su un Eurospin) o nome
 *    simile; al più tre;
 * 4. nessuno → nuovo fornitore con i dati letti.
 */
class Fornitori
{
    private const FORME = ['srl', 'srls', 'spa', 'snc', 'sas', 'sapa', 'scarl', 'scrl', 'coop', 'onlus', 'aps', 'asd', 'ssd', 'di', 'e', 'c', 'f', 'lli', 'flli', 'socio', 'unico', 'a'];

    /** «ACQUA & SAPONE S.R.L.» e «Acqua e Sapone srl» → «acqua sapone». */
    public static function normalizza(?string $nome): string
    {
        $t = mb_strtolower(trim((string) $nome));
        $t = strtr($t, ['à' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', '&' => ' e ']);
        // «S.R.L.», «S. N. C.»: le lettere puntate si uniscono prima di togliere la punteggiatura
        $t = preg_replace_callback('/\b(?:[a-z]\s?\.\s?){2,}/u', fn ($m) => str_replace(['.', ' '], '', $m[0]) . ' ', $t);
        $t = preg_replace('/[^a-z0-9]+/u', ' ', $t);
        $parole = array_filter(explode(' ', $t), fn ($p) => $p !== '' && ! in_array($p, self::FORME, true));

        return implode(' ', $parole);
    }

    /** Quante cifre diverse tra due partite IVA di 11 cifre (99 se non confrontabili). */
    public static function cifreDiverse(?string $a, ?string $b): int
    {
        if (! $a || ! $b || strlen($a) !== 11 || strlen($b) !== 11) {
            return 99;
        }
        $n = 0;
        for ($i = 0; $i < 11; $i++) {
            $n += $a[$i] !== $b[$i] ? 1 : 0;
        }

        return $n;
    }

    /**
     * @return array{trovato: ?Soggetto, come: ?string, simili: Collection<int, Soggetto>, aggiungi_piva: bool}
     */
    public function riconosci(?string $nome, ?string $partitaIva): array
    {
        $piva = $partitaIva && LettoreScontrino::partitaIvaValida($partitaIva) ? $partitaIva : null;
        $norma = self::normalizza($nome);
        $tutti = Soggetto::orderBy('nome')->get();

        $trovato = null;
        $come = null;
        if ($piva) {
            $trovato = $tutti->firstWhere('partita_iva', $piva);
            $come = $trovato ? 'partita_iva' : null;
        }
        if (! $trovato && $norma !== '') {
            $trovato = $tutti->first(fn ($s) => self::normalizza($s->nome) === $norma
                && (! $piva || ! $s->partita_iva || $s->partita_iva === $piva));
            $come = $trovato ? 'nome' : null;
        }

        $aggiungiPiva = $trovato && $come === 'nome' && $piva && ! $trovato->partita_iva
            && ! $tutti->contains('partita_iva', $piva);

        $simili = collect();
        if (! $trovato) {
            $simili = $tutti->where('attivo', true)
                ->map(fn ($s) => [$s, $this->somiglianza($s, $norma, $partitaIva)])
                ->filter(fn ($c) => $c[1] >= 0.6)
                ->sortByDesc(fn ($c) => $c[1])
                ->take(3)
                ->map(fn ($c) => $c[0])
                ->values();
        }

        return ['trovato' => $trovato, 'come' => $come, 'simili' => $simili, 'aggiungi_piva' => $aggiungiPiva];
    }

    private function somiglianza(Soggetto $s, string $norma, ?string $piva): float
    {
        $punti = 0.0;
        $diverse = self::cifreDiverse($s->partita_iva, $piva);
        if ($diverse <= 3) {
            $punti = 0.95 - 0.05 * $diverse;
        }
        $suo = self::normalizza($s->nome);
        if ($norma !== '' && $suo !== '') {
            if (mb_strlen($norma) >= 4 && mb_strlen($suo) >= 4 && (str_contains($suo, $norma) || str_contains($norma, $suo))) {
                $punti = max($punti, 0.8);
            }
            similar_text($norma, $suo, $percento);
            $punti = max($punti, $percento / 100);
        }

        return $punti;
    }
}
