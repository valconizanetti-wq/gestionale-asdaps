<?php

namespace App\Console\Commands;

use App\Mail\AuditAnomalia;
use App\Models\User;
use App\Services\AuditConsultazione;
use App\Services\AuditIntegrityService;
use App\Services\AuditLogService;
use App\Support\InvioEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Verifica mensile della catena dell'Audit Log (audit-log.md §9). Ripreso
 * dal Gestionale APS con due correzioni: si controlla l'intera catena, non
 * solo l'anno precedente (un'alterazione dell'anno in corso emergeva solo
 * l'anno dopo), e con anomalie si avvisano il Presidente e, se ci sono, i
 * Revisori (prima solo log e registro). Mai correzioni automatiche.
 */
class VerificaIntegritaAudit extends Command
{
    protected $signature = 'audit:verifica-integrita {--silenzioso : Nessuna email anche con anomalie}';

    protected $description = 'Verifica la catena SHA-256 dell\'Audit Log; con anomalie avvisa Presidente e Revisori (nessuna correzione)';

    public function handle(): int
    {
        $esito = AuditIntegrityService::verificaCatena();
        $dati = AuditConsultazione::registraVerifica($esito, 'automatica');

        if ($esito['primo_anello'] === null) {
            $this->info('Registro ancora vuoto: niente da verificare.');

            return self::SUCCESS;
        }

        $this->info("Righe controllate: {$dati['righe']}. Impronta dell'ultima riga: {$dati['hash_finale']}");

        if ($dati['anomalie'] === 0) {
            $this->info('Catena integra.');
            Log::info("audit:verifica-integrita OK righe={$dati['righe']}");

            return self::SUCCESS;
        }

        $collegamento = count($esito['anomalie_collegamento']);
        $this->error("ANOMALIE: {$dati['anomalie']} (collegamento {$collegamento}, contenuto " . count($esito['anomalie_contenuto']) . "), dalla riga n. {$dati['primo_anomalo']}.");
        Log::critical("audit:verifica-integrita ANOMALIE={$dati['anomalie']} primo={$dati['primo_anomalo']} righe={$dati['righe']}");

        try {
            AuditLogService::scrivi([
                'evento' => 'AUDIT_INTEGRITY_FAIL',
                'entita_tipo' => 'AuditLog',
                'utente_nome' => 'sistema',
                'ip_address' => '127.0.0.1',
                'livello_rischio' => 'HIGH',
                'dettaglio' => "Verifica automatica: {$dati['anomalie']} righe anomale su {$dati['righe']}, dalla n. {$dati['primo_anomalo']}.",
            ]);
        } catch (\Throwable $e) {
            Log::error('audit:verifica-integrita: riga AUDIT_INTEGRITY_FAIL non scritta: ' . $e->getMessage());
        }

        if (! $this->option('silenzioso')) {
            $destinatari = User::whereIn('ruolo', ['presidente', 'revisore'])->where('attivo', true)->whereNotNull('email')->get();
            if ($destinatari->isEmpty()) {
                Log::warning('audit:verifica-integrita: nessun Presidente o Revisore con email da avvisare.');
            }
            foreach ($destinatari as $u) {
                InvioEmail::invia(new AuditAnomalia($dati, $esito['anomalie'][0]['anno'] ?? now()->year), $u->email, $u->name, 'audit_anomalia');
            }
        }

        return self::FAILURE;
    }
}
