<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.30.3 (collaudo A8, 29/09/2026): lo statuto, come l'informativa, va
 * aperto prima di inviare la domanda. Si registra quando è stato aperto
 * (domande-iscrizione.md §2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_domande', function (Blueprint $table) {
            $table->dateTime('statuto_aperto_il')->nullable()->after('informativa_aperta_il');
        });
    }

    public function down(): void
    {
        Schema::table('gl_domande', function (Blueprint $table) {
            $table->dropColumn('statuto_aperto_il');
        });
    }
};
