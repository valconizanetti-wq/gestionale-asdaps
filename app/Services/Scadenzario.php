<?php

namespace App\Services;

use App\Models\Associazione;
use App\Models\PrimaNota;
use App\Models\Scadenza;
use App\Models\ScadenzaRinnovo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Scadenze dell'associazione (scadenzario.md, tavole SC-1…SC-3).
 *
 * «Rinnovata» (pagamento) o «Fatta» (adempimento) registra lo storico,
 * l'eventuale uscita in Prima Nota con i giustificativi e sposta la
 * scadenza ricorrente all'anno dopo (una tantum: la chiude). La scadenza
 * dell'affiliazione si copia anche nella scheda dell'ente.
 */
class Scadenzario
{
    public function __construct(private AnniContabili $anni)
    {
    }

    /**
     * @param array{per_scadenza: string, fatto_il: string, movimento: bool, importo?: ?float, conto?: ?string,
     *              prossima_scadenza?: ?string, note?: ?string} $d
     * @param \Closure(PrimaNota): void|null $dopo riceve il movimento (giustificativi), dentro la transazione
     *
     * @throws \RuntimeException con un messaggio per l'utente
     */
    public function rinnova(Scadenza $s, array $d, User $utente, ?\Closure $dopo = null): ScadenzaRinnovo
    {
        $fatto = Carbon::parse($d['fatto_il'])->startOfDay();
        if ($d['movimento']) {
            if (! $s->conPagamento()) {
                throw new \RuntimeException('Questa scadenza non ha un pagamento: si segna come fatta, senza uscita.');
            }
            if ($fatto->year < RinnoviQuota::annoAvvio()) {
                throw new \RuntimeException("Il pagamento del {$fatto->year} è prima dell'avvio della contabilità nel gestionale (" . RinnoviQuota::annoAvvio() . "): togli la spunta «Registra l'uscita», il pagamento è nel vecchio gestionale.");
            }
            if ($this->anni->chiuso($fatto->year)) {
                throw new \RuntimeException("L'anno {$fatto->year} della Prima Nota è chiuso: l'uscita non si registra con questa data.");
            }
        }

        return DB::transaction(function () use ($s, $d, $fatto, $utente, $dopo) {
            // Lock e controllo della data: un doppio invio (o due persone
            // insieme) non sposta la scadenza di due anni.
            $s = Scadenza::with('categoriaPrimaNota')->whereKey($s->id)->lockForUpdate()->firstOrFail();
            if ($s->completato) {
                throw new \RuntimeException("«{$s->titolo}» è già stata segnata come fatta.");
            }
            if ($s->data_scadenza->toDateString() !== $d['per_scadenza']) {
                throw new \RuntimeException("«{$s->titolo}» è già stata rinnovata (ora scade il {$s->data_scadenza->format('d/m/Y')}): ricarica la pagina.");
            }

            $prossima = null;
            if ($s->ricorrente()) {
                $prossima = Carbon::parse($d['prossima_scadenza'])->startOfDay();
                if ($prossima->lte($s->data_scadenza)) {
                    throw new \RuntimeException('La prossima scadenza deve venire dopo quella di adesso (' . $s->data_scadenza->format('d/m/Y') . ').');
                }
            }

            $m = null;
            if ($d['movimento']) {
                $this->anni->assicura($fatto->year);
                $m = new PrimaNota();
                $m->forceFill([
                    'data' => $fatto->toDateString(),
                    'tipo' => 'uscita',
                    'conto' => $d['conto'],
                    'importo' => round((float) $d['importo'], 2),
                    'descrizione' => "{$s->titolo}, scadenza del {$s->data_scadenza->format('d/m/Y')}",
                    'categoria_id' => $s->prima_nota_categoria_id,
                    'soggetto' => self::soggetto($s),
                    'registrato_da' => $utente->id,
                ])->save();
                if ($dopo) {
                    $dopo($m);
                }
            }

            $r = ScadenzaRinnovo::create([
                'scadenza_id' => $s->id,
                'data_scadenza' => $s->data_scadenza->toDateString(),
                'fatto_il' => $fatto->toDateString(),
                // Anche senza uscita (pagata nel vecchio gestionale) l'importo, se
                // scritto, resta: serve per la proposta del prossimo rinnovo
                'importo' => isset($d['importo']) ? round((float) $d['importo'], 2) : null,
                'conto' => $d['movimento'] ? $d['conto'] : null,
                'prima_nota_id' => $m?->id,
                'prossima_scadenza' => $prossima?->toDateString(),
                'note' => $d['note'] ?? null,
                'registrato_da' => $utente->id,
            ]);

            if ($prossima) {
                $s->fill([
                    'data_scadenza' => $prossima->toDateString(),
                    'giorno_ricorrenza' => $prossima->day,
                    'mese_ricorrenza' => $prossima->month,
                ])->save();
            } else {
                $s->fill(['completato' => true, 'completato_il' => $fatto->toDateString()])->save();
            }
            $this->copiaNellEnte($s);

            return $r;
        });
    }

    /**
     * Annulla l'ultimo rinnovo (errore di registrazione): la scadenza torna
     * alla data di prima, l'uscita in Prima Nota si elimina con i
     * giustificativi (non su un anno chiuso). Motivazione nell'Audit Log.
     *
     * @throws \RuntimeException
     */
    public function annullaUltimo(Scadenza $s, string $motivo): void
    {
        $file = DB::transaction(function () use ($s, $motivo) {
            $s = Scadenza::whereKey($s->id)->lockForUpdate()->firstOrFail();
            // L'ultimo registrato (non il più recente per data): è quello che ha
            // spostato la scadenza alla data di adesso
            $r = $s->rinnovi()->orderByDesc('id')->first();
            if (! $r) {
                throw new \RuntimeException('Non ci sono rinnovi da annullare.');
            }
            $m = $r->movimento;
            if ($m && $this->anni->chiuso($m->annoMovimento())) {
                throw new \RuntimeException("L'uscita n. {$m->id} è dell'anno chiuso {$m->annoMovimento()}: il rinnovo non si annulla.");
            }

            AuditLogService::scrivi([
                'evento' => 'SCADENZA_RINNOVO_ANNULLATO',
                'entita_tipo' => 'Scadenza',
                'entita_id' => $s->id,
                'livello_rischio' => 'MEDIUM',
                // La motivazione nei valori, fuori dall'hash (come per le schede eliminate)
                'dettaglio' => "Annullato il rinnovo del {$r->fatto_il->format('d/m/Y')} di «{$s->titolo}» (scadenza del {$r->data_scadenza->format('d/m/Y')})"
                    . ($m ? ", con l'uscita n. {$m->id}" : '') . ', con motivazione.',
                'new_values' => ['motivazione' => $motivo],
            ]);

            $s->fill([
                'data_scadenza' => $r->data_scadenza->toDateString(),
                'giorno_ricorrenza' => $s->ricorrente() ? $r->data_scadenza->day : $s->giorno_ricorrenza,
                'mese_ricorrenza' => $s->ricorrente() ? $r->data_scadenza->month : $s->mese_ricorrenza,
                'completato' => false,
                'completato_il' => null,
            ])->save();
            $r->delete();

            $file = [];
            if ($m) {
                $file = $m->documenti()->pluck('path')->all();
                $m->documenti()->delete();
                $m->delete();
            }
            $this->copiaNellEnte($s);

            return $file;
        });
        foreach ($file as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    /** La scadenza dell'affiliazione si copia nella scheda dell'ente (rete_affiliazione_scadenza). */
    public function copiaNellEnte(Scadenza $s): void
    {
        if ($s->chiave === Scadenza::AFFILIAZIONE) {
            Associazione::istanza()->forceFill(['rete_affiliazione_scadenza' => $s->data_scadenza->toDateString()])->save();
        }
    }

    /** Chi riceve il pagamento, per il movimento: l'EPS per l'affiliazione, la compagnia per la polizza. */
    public static function soggetto(Scadenza $s): ?string
    {
        return match ($s->chiave) {
            Scadenza::AFFILIAZIONE => ProfiloEnte::al()->ente_affiliazione ?: null,
            Scadenza::POLIZZA => Associazione::istanza()->polizza_compagnia ?: null,
            default => null,
        };
    }

    /** Importo proposto: quello dell'ultimo pagamento. */
    public static function importoProposto(Scadenza $s): ?float
    {
        $r = $s->relationLoaded('ultimoRinnovo') ? $s->ultimoRinnovo : $s->ultimoRinnovo()->first();

        return $r?->importo !== null ? (float) $r->importo : null;
    }

    /**
     * Avviso sull'affiliazione (riquadro, Dashboard, Scadenzario, Tessere):
     * null se valida e lontana.
     *
     * @return array{tipo: string, titolo: string, testo: string, scadenza: Scadenza}|null
     */
    public static function avvisoAffiliazione(?Carbon $oggi = null): ?array
    {
        $s = Scadenza::perChiave(Scadenza::AFFILIAZIONE);
        if (! $s) {
            return null;
        }
        $st = $s->stato($oggi);
        $ente = ProfiloEnte::al()->ente_affiliazione ?: 'all\'EPS';
        $nome = 'Affiliazione ' . $ente;

        return match ($st['stato']) {
            'scaduta' => ['tipo' => 'ko', 'scadenza' => $s,
                'titolo' => "{$nome} scaduta il {$s->data_scadenza->format('d/m/Y')}.",
                'testo' => 'Senza affiliazione valida non si emettono né rinnovano tessere e l\'iscrizione al RASD decade. Rinnovala e registra il pagamento.'],
            'in_scadenza' => ['tipo' => 'warn', 'scadenza' => $s,
                'titolo' => "{$nome}: {$st['testo']} ({$s->data_scadenza->format('d/m/Y')}).",
                'testo' => 'Rinnovala per tempo: senza affiliazione valida non si emettono tessere. Poi segna «Rinnovata» nello Scadenzario.'],
            default => null,
        };
    }
}
