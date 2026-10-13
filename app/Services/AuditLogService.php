<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * AuditLogService — UNICO punto di scrittura su gl_audit_log (audit-log.md).
 *
 * Ripreso dal Gestionale APS, dove la stessa logica (lock, inserimento,
 * calcolo dell'hash) era scritta tre volte: nell'observer, in questo
 * servizio e nella verifica di integrità. Qui:
 * - l'AuditObserver scrive passando da questo servizio;
 * - la formula dell'hash vive solo in AuditIntegrityService::rigaPerHash().
 * Così scrittura e verifica non possono andare fuori sincrono.
 *
 * Usare per gli eventi di business non coperti dall'observer:
 *   AuditLogService::scrivi(['evento' => 'X', 'dettaglio' => '...']);
 */
class AuditLogService
{
    /**
     * @param array $campi Obbligatorio: evento. Opzionali: dettaglio,
     *   operazione, anno, entita_tipo, modello, entita_id, livello_rischio
     *   (default LOW), utente_nome, ip_address, old_values, new_values,
     *   campi_modificati (array).
     * @return int Id della riga inserita.
     */
    public static function scrivi(array $campi): int
    {
        $evento = $campi['evento'] ?? throw new \InvalidArgumentException('AuditLogService: campo evento obbligatorio.');
        $json = fn ($v) => $v ? json_encode($v, JSON_UNESCAPED_UNICODE) : null;

        $riga = [
            'evento'           => $evento,
            'operazione'       => $campi['operazione'] ?? null,
            'anno'             => $campi['anno'] ?? now()->year,
            'entita_tipo'      => $campi['entita_tipo'] ?? null,
            'modello'          => $campi['modello'] ?? ($campi['entita_tipo'] ?? null),
            'entita_id'        => $campi['entita_id'] ?? null,
            'utente_nome'      => $campi['utente_nome'] ?? (Auth::user()?->name ?? 'sistema'),
            'ip_address'       => $campi['ip_address'] ?? (request()?->ip() ?? '—'),
            'dettaglio'        => $campi['dettaglio'] ?? null,
            'old_values'       => $json($campi['old_values'] ?? null),
            'new_values'       => $json($campi['new_values'] ?? null),
            'campi_modificati' => $json($campi['campi_modificati'] ?? null),
            'livello_rischio'  => $campi['livello_rischio'] ?? 'LOW',
        ];

        $id = DB::transaction(function () use ($riga) {
            // Lock sull'ultima riga: due scritture quasi simultanee non
            // possono leggere lo stesso "ultimo hash".
            $previousHash = DB::table('gl_audit_log')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('hash');

            $id = DB::table('gl_audit_log')->insertGetId($riga + [
                'previous_hash' => $previousHash,
                'created_at'    => now(),
            ]);

            // L'hash si calcola sulla riga RILETTA dal database, esattamente
            // come la rilegge la verifica: stessi valori, stessa formattazione.
            $salvata = DB::table('gl_audit_log')->where('id', $id)->first();

            DB::table('gl_audit_log')->where('id', $id)->update([
                'hash' => AuditIntegrityService::hashRiga($salvata, $previousHash),
            ]);

            return $id;
        });

        // Operazione delicata: avviso a chi non l'ha eseguita (v0.40.0)
        if ($riga['livello_rischio'] === 'HIGH') {
            AvvisiOperazioni::dopoAudit($id, $riga);
        }

        return $id;
    }
}
