<?php

namespace App\Console\Commands;

use App\Models\Promemoria;
use App\Promemoria\Invii;
use Illuminate\Console\Command;

/**
 * Invia i promemoria arrivati alla loro data (promemoria.md §3). Pianificato
 * ogni 15 minuti (routes/console.php): parte al primo giro dopo l'ora scelta;
 * se il server era spento, recupera nei 7 giorni dopo. Ogni destinatario
 * riceve lo stesso promemoria una volta sola per periodo.
 * Nessun dato personale nell'output (finisce nei log del cron).
 */
class InviaPromemoria extends Command
{
    protected $signature = 'promemoria:invia {--prova : Mostra quanti riceverebbero, senza inviare}';

    protected $description = 'Invia i promemoria pianificati arrivati alla loro data';

    public function handle(Invii $invii): int
    {
        $adesso = now();
        foreach (Promemoria::where('attivo', true)->orderBy('id')->get() as $p) {
            if (! Invii::eIlMomento($p, $adesso)) {
                continue;
            }
            if ($this->option('prova')) {
                $this->line("Promemoria {$p->id}: {$invii->daAvvisare($p, $adesso)->count()} da avvisare.");

                continue;
            }
            $e = $invii->esegui($p, $adesso);
            if (array_sum($e)) {
                $this->line("Promemoria {$p->id}: {$e['inviati']} inviati, {$e['falliti']} non riusciti, {$e['senza_email']} senza email.");
            }
        }

        return self::SUCCESS;
    }
}
