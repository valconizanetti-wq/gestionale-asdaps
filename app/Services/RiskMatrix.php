<?php

namespace App\Services;

/**
 * RiskMatrix — livello di rischio di ogni operazione registrata
 * (audit-log.md). Ripreso dal Gestionale APS e adattato ai model ASD.
 *
 * HIGH   : operazioni critiche, con notifica al Presidente.
 * MEDIUM : operazioni operative rilevanti (stati, importi, soci).
 * LOW    : operazioni ordinarie.
 */
class RiskMatrix
{
    /** Campi che, se modificati, rendono l'operazione HIGH su qualunque model. */
    private const CAMPI_HIGH = [
        'is_locked', 'storno_di_id', 'ruolo', 'password', 'attivo',
        'iban', 'codice_fiscale', 'allegato_path',
    ];

    private const CAMPI_MEDIUM = [
        'stato', 'importo', 'totale', 'tipo', 'quota_scadenza',
        'assicurazione_scadenza', 'data_cancellazione', 'pseudonimizzato',
        // v0.33.0: consenso a foto e video dato o revocato (privacy-link.md)
        'foto_video_consenso',
    ];

    /**
     * @param string $operazione created | updated | deleted | restored
     * @param array  $dirty      campi modificati (getDirty())
     */
    public static function valuta(string $modello, string $operazione, array $dirty = []): string
    {
        // Utenti: la creazione è MEDIUM, eliminazione e cambio di ruolo,
        // email, password, nome o stato attivo sono HIGH.
        if ($modello === 'User') {
            return match (true) {
                $operazione === 'deleted' => 'HIGH',
                $operazione === 'created' => 'MEDIUM',
                (bool) array_intersect(['ruolo', 'email', 'password', 'name', 'attivo'], array_keys($dirty)) => 'HIGH',
                default => 'LOW',
            };
        }

        if ($operazione === 'deleted' && in_array($modello, ['PrimaNota', 'Verbale', 'Bilancio', 'RimborsoVolontario'], true)) {
            return 'HIGH';
        }

        if (array_intersect(self::CAMPI_HIGH, array_keys($dirty))) {
            return 'HIGH';
        }

        // Eliminazione e ripristino di un socio: solo per correggere errori
        // (soci.md §3), sempre rilevanti ma non allarmanti.
        if ($modello === 'Socio' && in_array($operazione, ['deleted', 'restored'], true)) {
            return 'MEDIUM';
        }

        if ($operazione === 'updated') {
            if ($modello === 'Bilancio' && ($dirty['stato'] ?? null) === 'approvato') {
                return 'HIGH';
            }
            if (in_array($modello, ['PrimaNota', 'Bilancio', 'Verbale', 'Volontario', 'RimborsoVolontario'], true)) {
                return 'MEDIUM';
            }
            if (array_intersect(self::CAMPI_MEDIUM, array_keys($dirty))) {
                return 'MEDIUM';
            }
        }

        return 'LOW';
    }
}
