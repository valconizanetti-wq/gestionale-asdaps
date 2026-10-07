<?php

namespace App\Observers;

use App\Services\AuditLogService;
use App\Services\RiskMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * AuditObserver — registra in gl_audit_log creazione, modifica,
 * eliminazione e ripristino dei model che lo dichiarano con
 *   #[ObservedBy([AuditObserver::class])]
 * (vedi audit-log.md). Ripreso dal Gestionale APS, con queste differenze:
 * - scrive tramite AuditLogService (una sola implementazione di lock e hash);
 * - registra anche il ripristino (restored), prima non tracciato;
 * - esclude ultimo_accesso, aggiornato a ogni login;
 * - la notifica HIGH al Presidente non contiene dati personali, solo il
 *   riferimento alla riga di log;
 * - il DETTAGLIO elenca solo i NOMI dei campi modificati, mai i valori:
 *   il dettaglio entra nell'hash della catena, quindi non deve contenere
 *   dati personali (che non si potrebbero più oscurare). I valori stanno
 *   in old_values/new_values, fuori dall'hash (AuditIntegrityService).
 */
class AuditObserver
{
    /** Campi mai registrati nei valori old/new (sicurezza e rumore). */
    private const ESCLUSI = [
        'created_at', 'updated_at', 'remember_token', 'password',
        'email_verified_at', 'ultimo_accesso',
        'two_factor_secret', 'two_factor_recovery_codes',
        // Token dei link personali (domanda, conferma email, voto CD):
        // chi legge l'audit potrebbe usarli per agire al posto
        // dell'interessato finché il link è valido (v0.26.0).
        'token', 'token_conferma', 'token_link',
    ];

    public function created($model): void  { $this->registra($model, 'created'); }
    public function updated($model): void  { $this->registra($model, 'updated'); }
    public function deleted($model): void  { $this->registra($model, 'deleted'); }
    public function restored($model): void { $this->registra($model, 'restored'); }

    private function registra($model, string $operazione): void
    {
        // Record di prova (es. domande di iscrizione con is_test): mai nella
        // catena dell'audit, così si possono eliminare senza lasciare righe
        // permanenti né dover ricalcolare gli hash. Stessa regola del
        // Gestionale APS, che la applicava nei controller (v76); qui vale
        // per ogni model che abbia il campo.
        // Letto dall'array degli attributi, non con getAttribute(): se un
        // giorno si attivasse Model::shouldBeStrict(), leggere un campo
        // inesistente (Socio, User non hanno is_test) lancerebbe
        // un'eccezione fuori dal try qui sotto e bloccherebbe il salvataggio.
        if ((bool) ($model->getAttributes()['is_test'] ?? false)) {
            return;
        }

        $modello = class_basename($model);

        try {
            $old = null;
            $new = null;
            $campi = [];
            $dirty = [];

            if ($operazione === 'created') {
                $new = $this->filtra($model->getAttributes());
            } elseif ($operazione === 'updated') {
                $dirty = array_diff_key($model->getChanges(), array_flip(self::ESCLUSI));
                if (empty($dirty)) {
                    return; // solo timestamp o campi esclusi: niente da registrare
                }
                $campi = array_keys($dirty);
                $old = array_intersect_key($model->getOriginal(), $dirty);
                $new = $dirty;
            } else { // deleted, restored
                $old = $operazione === 'deleted' ? $this->filtra($model->getAttributes()) : null;
            }

            // Model che aggiungono un contesto ai valori (es. PrimaNota: il
            // verso del giroconto, i giustificativi tolti con il movimento),
            // così un'operazione dell'utente resta una riga sola (v0.35.1).
            if ($operazione !== 'restored' && method_exists($model, 'auditContesto')) {
                $extra = $model->auditContesto($operazione);
                if ($extra) {
                    if ($operazione === 'deleted') {
                        $old = ($old ?? []) + $extra;
                    } else {
                        $new = ($new ?? []) + $extra;
                    }
                }
            }

            // Model che dichiarano quali campi possono comparire con il
            // valore (es. Domanda): degli altri resta solo il nome in
            // campi_modificati. Serve per i dati personali che vanno
            // cancellati alla scadenza della conservazione (informativa
            // §7): nell'audit resterebbero per sempre.
            if (property_exists($model, 'auditCampiConValore')) {
                $ammessi = array_flip($model->auditCampiConValore);
                $old = $old === null ? null : array_intersect_key($old, $ammessi);
                $new = $new === null ? null : array_intersect_key($new, $ammessi);
            }

            $rischio = RiskMatrix::valuta($modello, $operazione, $dirty);

            $id = AuditLogService::scrivi([
                'evento'           => strtoupper($modello) . '_' . strtoupper($operazione),
                'operazione'       => $operazione,
                'anno'             => $this->anno($model),
                'entita_tipo'      => $modello,
                'modello'          => $modello,
                'entita_id'        => $model->getKey(),
                'dettaglio'        => $this->dettaglio($modello, $operazione, $campi),
                'old_values'       => $old,
                'new_values'       => $new,
                'campi_modificati' => $campi,
                'livello_rischio'  => $rischio,
            ]);

            if ($rischio === 'HIGH') {
                $this->notificaHigh($modello, $operazione, $id);
            }
        } catch (\Throwable $e) {
            // L'audit non deve bloccare l'operazione, ma il fallimento resta nel log.
            Log::error("AuditObserver: scrittura fallita [{$modello}/{$operazione}]: " . $e->getMessage());
        }
    }

    /**
     * Email al Presidente: solo riferimenti, nessun dato personale (GDPR).
     * Pubblica: la usa anche chi scrive l'audit a mano senza eventi del
     * model (es. eliminazione della scheda del socio, v0.37.1).
     */
    public function notificaHigh(string $modello, string $operazione, int $logId): void
    {
        try {
            $presidente = DB::table('gl_users')->where('ruolo', 'presidente')->where('attivo', true)->first();
            if (! $presidente?->email) {
                return;
            }

            $corpo = "Operazione ad alto rischio registrata nel Gestionale ASD.\n\n"
                . "Oggetto    : {$modello}\n"
                . 'Operazione : ' . strtoupper($operazione) . "\n"
                . 'Eseguita da: ' . (auth()->user()?->name ?? 'sistema') . "\n"
                . "Riga di log: #{$logId}\n"
                . 'Data e ora : ' . now()->format('d/m/Y H:i:s') . "\n\n"
                . "I dettagli sono consultabili nel gestionale, sezione Audit Log.\n"
                . url('/') . "\n";

            Mail::raw($corpo, fn ($m) => $m
                ->to($presidente->email, $presidente->name)
                ->subject('[Audit] Operazione ad alto rischio: ' . $modello . ' ' . strtoupper($operazione)));
        } catch (\Throwable $e) {
            Log::warning('AuditObserver: notifica HIGH non inviata: ' . $e->getMessage());
        }
    }

    private function filtra(array $attributi): array
    {
        return array_diff_key($attributi, array_flip(self::ESCLUSI));
    }

    private function anno($model): int
    {
        if (isset($model->anno) && is_numeric($model->anno)) {
            return (int) $model->anno;
        }
        if (! empty($model->data)) {
            try {
                return (int) \Carbon\Carbon::parse($model->data)->format('Y');
            } catch (\Throwable $e) {
                // si usa l'anno corrente
            }
        }

        return (int) now()->format('Y');
    }

    /** Solo nomi di campo, mai valori: il dettaglio entra nell'hash. */
    private function dettaglio(string $modello, string $operazione, array $campi): string
    {
        return match ($operazione) {
            'created'  => "{$modello} creato.",
            'deleted'  => "{$modello} eliminato.",
            'restored' => "{$modello} ripristinato.",
            default    => "{$modello} aggiornato: " . implode(', ', $campi) . '.',
        };
    }
}
