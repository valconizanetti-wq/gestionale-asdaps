<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.44.1 — Soci e utenti: una sola anagrafica (analisi-soci-utenti.md,
 * decisioni di Paolo del 21/10/2026).
 *
 * gl_users.eliminato_il: eliminazione logica dell'utente (D-SU1). Chi non è
 * più socio, o esce dal CD senza altri ruoli, sparisce dalla pagina Utenti
 * e non entra più; la riga resta per i registri («caricato da…»).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_users', function (Blueprint $table) {
            $table->timestamp('eliminato_il')->nullable()->after('attivo');
        });
    }

    public function down(): void
    {
        Schema::table('gl_users', function (Blueprint $table) {
            $table->dropColumn('eliminato_il');
        });
    }
};
