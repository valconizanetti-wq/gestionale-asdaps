<?php

namespace App\Services;

use App\Models\AnnoContabile;
use App\Models\PrimaNota;
use App\Models\PrimaNotaCategoria;
use App\Models\User;
use App\Models\Verbale;
use Illuminate\Support\Facades\DB;

/**
 * Anni contabili della Prima Nota (prima-nota.md §3): saldi di apertura e
 * finali, chiusura e riapertura.
 *
 * Regole:
 * - un anno ha una riga dal primo movimento (assicura()); prima è "nuovo";
 * - nel primo anno del gestionale i saldi di apertura si scrivono a mano
 *   (per la Compagnia: chiusura 2026 di gestionale-acv); negli anni dopo
 *   sono i saldi finali dell'anno prima, calcolati finché quello è aperto
 *   e fotografati quando si chiude;
 * - si chiude un anno finito, con l'anno prima già chiuso, senza movimenti
 *   da classificare né anticipi da rimborsare, scegliendo il verbale
 *   dell'Assemblea che approva il rendiconto (verbaliApprovazione());
 * - su un anno chiuso ogni correzione chiede una motivazione (nel
 *   controller) e rifà la fotografia dei saldi (aggiornaChiusura()).
 */
class AnniContabili
{
    public function trova(int $anno): ?AnnoContabile
    {
        return AnnoContabile::where('anno', $anno)->first();
    }

    /** Crea la riga dell'anno se manca (stato aperto). */
    public function assicura(int $anno): AnnoContabile
    {
        return AnnoContabile::firstOrCreate(['anno' => $anno], ['stato' => 'aperto']);
    }

    public function chiuso(int $anno): bool
    {
        return AnnoContabile::where('anno', $anno)->where('stato', 'chiuso')->exists();
    }

    /** Ultimo anno con una riga prima di $anno. */
    public function precedente(int $anno): ?AnnoContabile
    {
        return AnnoContabile::where('anno', '<', $anno)->orderByDesc('anno')->first();
    }

    /** Primo anno del gestionale: nessuna riga per gli anni precedenti. */
    public function primoAnno(int $anno): bool
    {
        return ! AnnoContabile::where('anno', '<', $anno)->exists();
    }

    /**
     * Saldi di apertura.
     *
     * @return array{cassa: float, banca: float, da_impostare: bool, da_anno_prima: bool}
     */
    public function apertura(int $anno): array
    {
        // L'ultimo anno registrato prima di questo: di solito anno - 1, ma un
        // anno senza movimenti non ha una riga e il saldo passa intatto.
        $prima = $this->precedente($anno);
        if ($prima) {
            $finali = $prima->isChiuso() && $prima->saldo_chiusura_cassa !== null
                ? ['cassa' => (float) $prima->saldo_chiusura_cassa, 'banca' => (float) $prima->saldo_chiusura_banca]
                : $this->finali($prima->anno);

            return ['cassa' => $finali['cassa'], 'banca' => $finali['banca'], 'da_impostare' => false, 'da_anno_prima' => true];
        }

        $riga = $this->trova($anno);

        return [
            'cassa' => (float) ($riga?->saldo_apertura_cassa ?? 0),
            'banca' => (float) ($riga?->saldo_apertura_banca ?? 0),
            'da_impostare' => $riga?->saldo_apertura_cassa === null || $riga?->saldo_apertura_banca === null,
            'da_anno_prima' => false,
        ];
    }

    /**
     * Entrate meno uscite per conto, nell'anno, dei movimenti che contano
     * nel saldo (giroconti compresi: spostano soldi tra i conti).
     *
     * @return array{cassa: float, banca: float}
     */
    public function variazioni(int $anno): array
    {
        $righe = PrimaNota::query()->anno($anno)->effettivi()
            ->whereIn('conto', array_keys(PrimaNota::CONTI))
            ->selectRaw("conto, SUM(CASE WHEN tipo = 'entrata' THEN importo ELSE -importo END) AS s")
            ->groupBy('conto')->pluck('s', 'conto');

        return ['cassa' => round((float) ($righe['cassa'] ?? 0), 2), 'banca' => round((float) ($righe['banca'] ?? 0), 2)];
    }

    /** @return array{cassa: float, banca: float} */
    public function finali(int $anno): array
    {
        $a = $this->apertura($anno);
        $v = $this->variazioni($anno);

        return ['cassa' => round($a['cassa'] + $v['cassa'], 2), 'banca' => round($a['banca'] + $v['banca'], 2)];
    }

    /**
     * Cosa manca per chiudere l'anno: elenco di [testo, fatto, nota, solo avviso].
     * Un controllo «solo avviso» non fatto non blocca la chiusura.
     *
     * @return array<int, array{0: string, 1: bool, 2: string, 3?: bool}>
     */
    public function controlliChiusura(int $anno): array
    {
        $daClassificare = PrimaNota::query()->anno($anno)
            ->whereHas('categoria', fn ($q) => $q->where('codice', PrimaNotaCategoria::DA_CLASSIFICARE))->count();
        $anticipi = PrimaNota::query()->anno($anno)->where('sospeso', true)->whereNull('rimborsato_il')->count();
        $importoAnticipi = (string) PrimaNota::query()->anno($anno)->where('sospeso', true)->whereNull('rimborsato_il')->sum('importo');
        // Scontrini con le righe che non tornano al totale (v0.39.0, scontrini.md §7)
        $daQuadrare = \App\Models\PrimaNotaScontrino::where('anno', $anno)->where('stato', 'da_quadrare')->count();
        $prima = $this->precedente($anno);
        $finito = now()->year > $anno;
        $apertura = $this->apertura($anno);

        return [
            ["L'anno è finito", $finito, $finito ? 'Dal 1° gennaio ' . ($anno + 1) : "Si chiude dal 1° gennaio " . ($anno + 1)],
            ["L'anno prima è chiuso", ! $prima || $prima->isChiuso(), $prima && ! $prima->isChiuso() ? "Prima va chiuso il {$prima->anno}" : 'Nessun anno aperto prima'],
            ['Saldi di apertura impostati', ! $apertura['da_impostare'], $apertura['da_impostare'] ? 'Mancano i saldi di apertura di cassa e banca' : ($apertura['da_anno_prima'] ? 'Dai saldi finali del ' . $prima?->anno : 'Scritti a mano')],
            ['Nessun movimento da classificare', $daClassificare === 0, $daClassificare === 0 ? 'Tutti i movimenti hanno la loro categoria' : $daClassificare . ' ' . ($daClassificare === 1 ? 'movimento' : 'movimenti') . ' in «Uscite da classificare»'],
            ['Nessuno scontrino da quadrare', $daQuadrare === 0, $daQuadrare === 0 ? 'I movimenti di ogni scontrino tornano al suo totale' : $daQuadrare . ' ' . ($daQuadrare === 1 ? 'scontrino' : 'scontrini') . ' da quadrare (Scontrini)'],
            // A2 (v0.43.0): avviso, non blocco. Una spesa aperta entra nell'anno
            // in cui si rimborsa; nel rendiconto è un debito verso i soci.
            ['Anticipi da rimborsare', $anticipi === 0, $anticipi === 0 ? 'Nessuna spesa anticipata in sospeso'
                : $anticipi . ' ' . ($anticipi === 1 ? 'spesa anticipata' : 'spese anticipate') . ' ancora da rimborsare ('
                . \App\Support\Importo::it($importoAnticipi, true) . '): non bloccano la chiusura, entrano nell\'anno del rimborso e nel rendiconto sono debiti verso i soci', true],
        ];
    }

    public function chiudibile(int $anno): bool
    {
        $riga = $this->trova($anno);

        return $riga && ! $riga->isChiuso()
            && collect($this->controlliChiusura($anno))->every(fn ($c) => $c[1] || ($c[3] ?? false));
    }

    /**
     * Verbali dell'Assemblea tra cui scegliere quello che approva il
     * rendiconto di $anno (collaudo J11): sedute dell'anno in corso,
     * successive al 31/12 di $anno e non future. Dalla v0.44.0 solo i
     * verbali firmati del libro delle Assemblee con la delibera «Approva il
     * rendiconto dell'anno $anno» (analisi verbali §4.5, difetto 13 del
     * Dante): non più tutte le Assemblee dell'anno.
     *
     * @return \Illuminate\Support\Collection<int, Verbale>
     */
    public function verbaliApprovazione(int $anno): \Illuminate\Support\Collection
    {
        $inizio = max($anno + 1, now()->year) . '-01-01';

        return Verbale::where('libro', 'assemblee')
            ->where('approva_rendiconto_anno', $anno)
            ->where('stato', 'firmato')
            ->whereNotNull('data_seduta')
            ->whereBetween('data_seduta', [$inizio, today()->toDateString()])
            ->orderByDesc('data_seduta')->orderByDesc('id')
            ->get(['id', 'titolo', 'data_seduta', 'stato', 'anno_libro', 'numero_libro', 'libro']);
    }

    /** @throws \RuntimeException */
    public function chiudi(int $anno, Verbale $verbale, User $utente): AnnoContabile
    {
        if (! $this->verbaliApprovazione($anno)->contains('id', $verbale->id)) {
            throw new \RuntimeException("Il verbale scelto non è un verbale firmato dell'Assemblea che approva il rendiconto {$anno}.");
        }
        $dataApprovazione = $verbale->data_seduta->toDateString();
        $riferimento = mb_substr($verbale->titolo, 0, 255);

        return DB::transaction(function () use ($anno, $dataApprovazione, $riferimento, $verbale, $utente) {
            $riga = AnnoContabile::where('anno', $anno)->lockForUpdate()->first();
            if (! $riga || $riga->isChiuso()) {
                throw new \RuntimeException("L'anno {$anno} non è aperto.");
            }
            if (! $this->chiudibile($anno)) {
                throw new \RuntimeException("L'anno {$anno} non si può ancora chiudere: controlla la lista qui sotto.");
            }
            $finali = $this->finali($anno);
            $riga->update([
                'stato' => 'chiuso',
                'saldo_chiusura_cassa' => $finali['cassa'],
                'saldo_chiusura_banca' => $finali['banca'],
                'approvazione_data' => $dataApprovazione,
                'approvazione_rif' => $riferimento,
                'approvazione_verbale_id' => $verbale->id,
                'chiuso_il' => now(),
                'chiuso_da' => $utente->id,
            ]);

            return $riga;
        });
    }

    /** Solo il Presidente, con motivazione (controller). @throws \RuntimeException */
    public function riapri(int $anno, string $motivazione): AnnoContabile
    {
        return DB::transaction(function () use ($anno, $motivazione) {
            $riga = AnnoContabile::where('anno', $anno)->lockForUpdate()->first();
            if (! $riga || ! $riga->isChiuso()) {
                throw new \RuntimeException("L'anno {$anno} non è chiuso.");
            }
            if ($this->chiuso($anno + 1)) {
                throw new \RuntimeException('Prima va riaperto il ' . ($anno + 1) . '.');
            }
            $riga->update([
                'stato' => 'aperto', 'saldo_chiusura_cassa' => null, 'saldo_chiusura_banca' => null,
                'chiuso_il' => null, 'chiuso_da' => null,
            ]);
            AuditLogService::scrivi([
                'evento' => 'ANNO_CONTABILE_RIAPERTO',
                'entita_tipo' => 'AnnoContabile',
                'entita_id' => $riga->id,
                'anno' => $anno,
                'livello_rischio' => 'HIGH',
                'dettaglio' => "Anno contabile {$anno} riaperto. Motivazione: {$motivazione}",
            ]);

            return $riga;
        });
    }

    /**
     * Dopo una correzione su un anno chiuso: nuova fotografia dei saldi
     * finali, così l'apertura dell'anno dopo resta allineata.
     */
    public function aggiornaChiusura(int $anno): void
    {
        $riga = $this->trova($anno);
        if (! $riga?->isChiuso()) {
            return;
        }
        $finali = $this->finali($anno);
        $riga->update(['saldo_chiusura_cassa' => $finali['cassa'], 'saldo_chiusura_banca' => $finali['banca']]);
    }
}
