<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rinnovi quota (rinnovi-quota.md, v0.36.0):
 * - ricevuta numerata per ANNO DI PAGAMENTO (decisione di Paolo, 06/10/2026):
 *   anno_ricevuta + progressivo, unici; numero_ricevuta resta come testo
 *   "13/2027" per le stampe;
 * - chi ha versato (per un minore il genitore: ricevuta e detrazione
 *   sportiva), categoria di quota applicata, assenso del minore dai 14 anni;
 * - annullamento: la ricevuta resta nel registro come annullata, quindi un
 *   socio può avere più righe per lo stesso anno (una sola valida, regola nel
 *   servizio RinnoviQuota). Tolto il vincolo unico (socio, anno).
 * - modalità "carta" (carta o POS, in banca).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_rinnovi', function (Blueprint $table) {
            $table->unsignedSmallInteger('anno_ricevuta')->nullable()->after('numero_ricevuta');
            $table->unsignedInteger('progressivo')->nullable()->after('anno_ricevuta');
            $table->string('categoria', 20)->nullable()->after('importo');
            $table->string('versato_da_nome', 150)->nullable()->after('metodo_pagamento');
            $table->string('versato_da_cf', 16)->nullable()->after('versato_da_nome');
            $table->timestamp('assenso_minore_il')->nullable()->after('versato_da_cf');
            $table->foreignId('assenso_minore_da')->nullable()->after('assenso_minore_il')->constrained('gl_users')->nullOnDelete();
            $table->timestamp('annullata_il')->nullable()->after('ricevuta_stato');
            $table->foreignId('annullata_da')->nullable()->after('annullata_il')->constrained('gl_users')->nullOnDelete();
            $table->text('annullamento_motivo')->nullable()->after('annullata_da');
            $table->index(['socio_id', 'anno'], 'gl_rinnovi_socio_anno_index');
        });

        Schema::table('gl_rinnovi', function (Blueprint $table) {
            $table->dropUnique('gl_rinnovi_socio_id_anno_unique');
            $table->dropUnique('gl_rinnovi_anno_numero_ricevuta_unique');
            $table->unique(['anno_ricevuta', 'progressivo'], 'gl_rinnovi_ricevuta_unique');
        });

        DB::statement("ALTER TABLE gl_rinnovi MODIFY metodo_pagamento ENUM('contante','bonifico','carta','altro') NOT NULL DEFAULT 'contante'");

        // Ricevute già numerate "N/AAAA": progressivo e anno dalla stringa.
        foreach (DB::table('gl_rinnovi')->whereNotNull('numero_ricevuta')->get(['id', 'numero_ricevuta']) as $r) {
            if (preg_match('#^(\d+)/(\d{4})$#', trim($r->numero_ricevuta), $m)) {
                DB::table('gl_rinnovi')->where('id', $r->id)->update(['progressivo' => (int) $m[1], 'anno_ricevuta' => (int) $m[2]]);
            }
        }
    }

    public function down(): void
    {
        DB::statement("UPDATE gl_rinnovi SET metodo_pagamento = 'altro' WHERE metodo_pagamento = 'carta'");
        DB::statement("ALTER TABLE gl_rinnovi MODIFY metodo_pagamento ENUM('contante','bonifico','altro') NOT NULL DEFAULT 'contante'");
        Schema::table('gl_rinnovi', function (Blueprint $table) {
            $table->dropUnique('gl_rinnovi_ricevuta_unique');
            $table->unique(['socio_id', 'anno']);
            $table->unique(['anno', 'numero_ricevuta']);
        });
        Schema::table('gl_rinnovi', function (Blueprint $table) {
            $table->dropIndex('gl_rinnovi_socio_anno_index');
            $table->dropConstrainedForeignId('assenso_minore_da');
            $table->dropConstrainedForeignId('annullata_da');
            $table->dropColumn(['anno_ricevuta', 'progressivo', 'categoria', 'versato_da_nome', 'versato_da_cf',
                'assenso_minore_il', 'annullata_il', 'annullamento_motivo']);
        });
    }
};
