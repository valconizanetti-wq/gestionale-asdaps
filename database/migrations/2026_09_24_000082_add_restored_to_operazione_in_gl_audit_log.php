<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aggiunge 'restored' ai valori ammessi di gl_audit_log.operazione, così
 * l'AuditObserver può registrare il ripristino di un record in soft delete
 * (audit-log.md §8). Senza, MySQL rifiuta la riga e il ripristino resta
 * fuori dal registro.
 *
 * Legge dal database la definizione attuale dell'ENUM invece di riscriverla
 * a memoria: aggiunge solo il valore mancante, senza toccare gli altri.
 * Se la colonna non è un ENUM (es. varchar) non fa nulla.
 */
return new class extends Migration
{
    public function up(): void
    {
        $valori = $this->valoriEnum();
        if ($valori === null || in_array('restored', $valori, true)) {
            return;
        }

        $this->impostaValori([...$valori, 'restored']);
    }

    public function down(): void
    {
        $valori = $this->valoriEnum();
        if ($valori === null || ! in_array('restored', $valori, true)) {
            return;
        }

        // Le righe 'restored' diventano NULL, altrimenti la modifica fallirebbe.
        DB::table('gl_audit_log')->where('operazione', 'restored')->update(['operazione' => null]);
        $this->impostaValori(array_values(array_diff($valori, ['restored'])));
    }

    /** @return string[]|null Valori dell'ENUM, o null se la colonna non è un ENUM. */
    private function valoriEnum(): ?array
    {
        $tipo = DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'gl_audit_log')
            ->where('COLUMN_NAME', 'operazione')
            ->value('COLUMN_TYPE');

        if (! $tipo || ! preg_match("/^enum\((.*)\)$/i", $tipo, $m)) {
            return null;
        }

        return array_map(fn ($v) => str_replace("''", "'", trim($v, "'")), str_getcsv($m[1], ',', "'"));
    }

    private function impostaValori(array $valori): void
    {
        $lista = implode(',', array_map(fn ($v) => DB::getPdo()->quote($v), $valori));
        DB::statement("ALTER TABLE gl_audit_log MODIFY operazione ENUM({$lista}) NULL");
    }
};
