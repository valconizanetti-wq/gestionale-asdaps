<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_prima_nota', function (Blueprint $table) {
            // Un movimento nato dal flusso scontrino/OCR si collega qui;
            // giustificativo_path/giustificativo_nome (esistenti) restano
            // invariati per i movimenti inseriti a mano senza passare dal
            // flusso scontrino: l'allegato di uno scontrino vive su
            // gl_prima_nota_scontrini.allegato_path, condiviso da tutti i
            // movimenti collegati (split).
            $table->foreignId('scontrino_id')
                  ->nullable()
                  ->after('rinnovo_id')
                  ->constrained('gl_prima_nota_scontrini')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scontrino_id');
        });
    }
};
