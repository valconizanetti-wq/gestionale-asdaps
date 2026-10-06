<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chiusura dell'anno contabile: il verbale dell'Assemblea che approva il
 * rendiconto si sceglie dall'elenco dei verbali (collaudo J11, v0.35.1),
 * non si scrive a mano. approvazione_data e approvazione_rif restano come
 * copia (data della seduta e titolo), per il registro e la storia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_anni_contabili', function (Blueprint $table) {
            $table->foreignId('approvazione_verbale_id')->nullable()->after('approvazione_rif')
                ->constrained('gl_verbali')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_anni_contabili', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approvazione_verbale_id');
        });
    }
};
