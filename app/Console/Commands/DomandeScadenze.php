<?php

namespace App\Console\Commands;

use App\Models\Domanda;
use App\Services\AuditLogService;
use App\Services\VotazioneCd;
use Illuminate\Console\Command;

/**
 * Scadenze delle domande di adesione (domande-iscrizione.md §3), ogni ora.
 * Riscrive il SilenzioAssensoCommand del Gestionale APS, che approvava
 * anche con la maggioranza contraria, scriveva nell'audit senza hash e
 * aggiungeva un finto voto "Sistema". Qui ogni passo usa VotazioneCd, con
 * le stesse regole e gli stessi blocchi delle azioni da pagina, ed è
 * ripetibile: rilanciato più volte non fa due volte la stessa cosa.
 *
 * 1. chiusura delle votazioni scadute (esito, verbale, email);
 * 2. sollecito al terzo giorno a chi non ha votato;
 * 3. promemoria al Presidente per il diniego non ancora inviato;
 * 4. decadenza delle domande accolte senza quota registrata, passato il
 *    termine (art. 6.6) più il margine per l'accredito
 *    (Domanda::GIORNI_MARGINE_DECADENZA);
 * 5. cancellazione per fine conservazione (informativa §6): domande mai
 *    confermate (vecchio flusso) dopo 30 giorni, non accolte o decadute
 *    dopo 12 mesi dall'esito.
 */
class DomandeScadenze extends Command
{
    protected $signature = 'domande:scadenze {--prova : Mostra cosa farebbe, senza fare nulla}';

    protected $description = 'Chiude le votazioni scadute, invia solleciti e promemoria, applica decadenze e cancellazioni delle domande';

    public function handle(VotazioneCd $votazione): int
    {
        $prova = (bool) $this->option('prova');
        $ora = now();

        // 0. Votazioni mai aperte (nessun consigliere in carica all'invio):
        //    si aprono appena il CD è inserito (collaudo A6, 29/09/2026).
        $senzaVotazione = Domanda::where('stato', 'in_attesa')->whereDoesntHave('voti')->get();
        foreach ($senzaVotazione as $domanda) {
            $prova ?: $votazione->apri($domanda);
        }

        // 1. Votazioni scadute. Senza votazione aperta non si chiude: con
        //    zero consiglieri la domanda sarebbe accolta d'ufficio.
        $scadute = Domanda::where('stato', 'in_attesa')->where('scadenza_cd', '<=', $ora)
            ->whereHas('voti')->get();
        foreach ($scadute as $domanda) {
            $prova ?: $votazione->chiudi($domanda, Domanda::CHIUSURA_SCADENZA);
        }

        // 2. Sollecito al terzo giorno
        $daSollecitare = Domanda::where('stato', 'in_attesa')
            ->whereNull('sollecito_inviato_il')
            ->where('created_at', '<=', $ora->copy()->subDays(Domanda::GIORNO_SOLLECITO))
            ->where('scadenza_cd', '>', $ora)
            ->get();
        $solleciti = 0;
        foreach ($daSollecitare as $domanda) {
            $solleciti += $prova ? 0 : $votazione->sollecita($domanda);
        }

        // 3. Promemoria del diniego
        $promemoria = Domanda::where('stato', 'da_rifiutare')
            ->whereNull('promemoria_diniego_il')
            ->where('esito_il', '<=', $ora->copy()->subDays(Domanda::GIORNI_PROMEMORIA_DINIEGO))
            ->get();
        foreach ($promemoria as $domanda) {
            $prova ?: $votazione->ricordaDiniego($domanda);
        }

        // 4. Decadenza: quota non registrata entro il termine più il margine
        //    per l'accredito del bonifico (termine 5 + 5 giorni: decade dal
        //    giorno dopo).
        $decadute = Domanda::where('stato', 'approvata')
            ->whereNull('socio_id')
            ->whereNotNull('quota_scadenza')
            ->whereDate('quota_scadenza', '<', $ora->copy()->subDays(Domanda::GIORNI_MARGINE_DECADENZA)->toDateString())
            ->get();
        foreach ($decadute as $domanda) {
            $prova ?: $votazione->faiDecadere($domanda);
        }

        // 5. Fine conservazione
        $daCancellare = Domanda::whereNull('socio_id')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereIn('stato', ['in_verifica', 'scaduta'])->where('created_at', '<=', $ora->copy()->subDays(30)))
                ->orWhere(fn ($q) => $q->whereIn('stato', ['rifiutata', 'decaduta'])
                    ->where(fn ($q) => $q->where('esito_il', '<=', $ora->copy()->subMonths(12))
                        ->orWhere(fn ($q) => $q->whereNull('esito_il')->where('updated_at', '<=', $ora->copy()->subMonths(12))))))
            ->get();
        foreach ($daCancellare as $domanda) {
            if ($prova) {
                continue;
            }
            $id = $domanda->id;
            $stato = $domanda->stato;
            $isTest = $domanda->is_test;
            $domanda->cancellaPerFineConservazione();
            if (! $isTest) {
                AuditLogService::scrivi([
                    'evento' => 'DOMANDA_CANCELLATA_FINE_CONSERVAZIONE',
                    'entita_tipo' => 'Domanda',
                    'entita_id' => $id,
                    'utente_nome' => 'sistema',
                    'dettaglio' => "Domanda {$id} ({$stato}) cancellata alla fine della conservazione.",
                ]);
            }
        }

        $this->line(($prova ? '[prova] ' : '') . sprintf(
            'Senza votazione aperta: %d · votazioni chiuse: %d · solleciti: %d domande (%d email) · promemoria diniego: %d · decadute: %d · cancellate: %d',
            $senzaVotazione->count(), $scadute->count(), $daSollecitare->count(), $solleciti, $promemoria->count(), $decadute->count(), $daCancellare->count()
        ));

        return self::SUCCESS;
    }
}
