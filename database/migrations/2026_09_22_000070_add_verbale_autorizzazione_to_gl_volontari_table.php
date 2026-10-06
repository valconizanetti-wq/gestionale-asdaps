<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_volontari', function (Blueprint $table) {
            // Collegamento al verbale del CD (gl_verbali, tipo 'cd_delibera')
            // che autorizza le tipologie di spesa/attività ammesse per questo
            // volontario, ai sensi dell'art. 29 D.Lgs. 36/2021 (vedi MANUALE-ASD.md §2.2).
            // Nota: si collega al verbale nel suo complesso, non alla singola
            // delibera interna — il core non ha ancora una tabella
            // gl_delibere_cd granulare (gap noto, vedi MANUALE-CORE.md §6).
            $table->foreignId('verbale_autorizzazione_id')
                  ->nullable()
                  ->after('sessione_cessazione_id')
                  ->constrained('gl_verbali')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_volontari', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verbale_autorizzazione_id');
        });
    }
};
