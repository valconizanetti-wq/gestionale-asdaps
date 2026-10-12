<?php

namespace App\Console\Commands;

use App\Models\Promemoria;
use App\Promemoria\Interni;
use App\Promemoria\Invii;
use Illuminate\Console\Command;

/**
 * Invia i promemoria arrivati alla loro data (promemoria.md §3). Pianificato
 * ogni 15 minuti (routes/console.php): parte al primo giro dopo l'ora scelta;
 * se il server era spento, recupera nei 7 giorni dopo. Ogni destinatario
 * riceve lo stesso promemoria una volta sola per periodo. I promemoria «N
 * giorni prima» (v0.38.0) si controllano a ogni giro: parte chi è arrivato
 * alla sua data.
 * Nessun dato personale nell'output (finisce nei log del cron).
 */
class InviaPromemoria extends Command
{
    protected $signature = 'promemoria:invia {--prova : Mostra quanti riceverebbero, senza inviare}';

    protected $description = 'Invia i promemoria pianificati arrivati alla loro data';

    public function handle(Invii $invii): int
    {
        $adesso = now();
        $prova = (bool) $this->option('prova');
        $inviati = 0;
        foreach (Promemoria::where('attivo', true)->orderBy('id')->get() as $p) {
            $nome = "Promemoria {$p->id} «{$p->nome}»";
            // Data fissa: solo nella sua finestra. Relativa («N giorni prima»):
            // ogni destinatario ha la sua data, il motore sceglie chi è dovuto.
            if ($p->quando !== 'relativa' && ! Invii::eIlMomento($p, $adesso)) {
                if ($prova) {
                    $this->line("{$nome}: non è il momento; prossimo invio {$this->quando(Invii::prossimo($p, $adesso))}.");
                }

                continue;
            }
            if ($prova) {
                $this->line("{$nome}: {$invii->daAvvisare($p, $adesso)->count()} da avvisare adesso.{$this->spiegazione($p, $adesso)}");

                continue;
            }
            $e = $invii->esegui($p, $adesso);
            if (array_sum($e)) {
                $inviati += array_sum($e);
                $this->line("{$nome}: {$e['inviati']} inviati, {$e['falliti']} non riusciti, {$e['senza_email']} senza email.");
            }
        }
        // Una riga anche quando non parte nulla (collaudo M11, v0.38.1): dal
        // terminale si capisce che il comando ha girato. Il cron la butta.
        if (! $prova && $inviati === 0) {
            $this->line('Nessun promemoria da inviare adesso. Per vedere perché: php artisan promemoria:invia --prova');
        }

        return self::SUCCESS;
    }

    /**
     * Per «--prova» sui promemoria «N giorni prima o dopo»: quanti
     * destinatari la funzione calcola in tutto e quando parte il prossimo.
     * Senza dati personali (l'output può finire nei log).
     */
    private function spiegazione(Promemoria $p, \Illuminate\Support\Carbon $adesso): string
    {
        if ($p->quando !== 'relativa') {
            return '';
        }
        $f = $p->funzioneOggetto();
        $tutti = $f ? $f->destinatari($p, $adesso)->count() : 0;
        if ($tutti === 0) {
            return array_key_exists('destinatari', $f?->parametri() ?? []) && Interni::utenti($p->parametro('destinatari'))->isEmpty()
                ? ' Nessun utente attivo con il ruolo scelto in «Destinatari».'
                : ' Nessun destinatario per nessuna data: la funzione non trova nulla da segnalare.';
        }

        return " Destinatari calcolati per tutte le date: {$tutti}; prossimo invio {$this->quando(Invii::prossimo($p, $adesso))}.";
    }

    private function quando(?\Illuminate\Support\Carbon $c): string
    {
        return $c ? 'il ' . $c->format('d/m/Y H:i') : 'non previsto (tutti già avvisati o nessuna data in arrivo)';
    }
}
