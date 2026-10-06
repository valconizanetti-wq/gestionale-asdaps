<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v0.34.0 (06/10/2026), audit-log.md §9: gl_audit_log.created_at passa da
 * TIMESTAMP a DATETIME.
 *
 * Un TIMESTAMP MySQL lo converte secondo il fuso orario della connessione,
 * e la data letta entra nell'impronta di ogni riga
 * (AuditIntegrityService::rigaPerHash). Se il fuso di MySQL cambia (nuovo
 * server, migrazione al dominio definitivo, impostazione del provider) le
 * stesse righe si rileggono con un'altra ora e tutta la catena risulta
 * alterata. Con DATETIME la data resta quella scritta. La conversione usa il
 * fuso della connessione di adesso, lo stesso con cui le righe sono state
 * scritte e verificate: le impronte esistenti restano valide.
 *
 * Trovato in locale (06/10/2026): un riavvio di MariaDB in un altro fuso ha
 * fatto risultare anomala la prima riga del registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE gl_audit_log MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE gl_audit_log MODIFY created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }
};
