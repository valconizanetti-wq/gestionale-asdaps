<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.31.0 (04/10/2026): assenso del minore che ha compiuto 14 anni al
 * proprio tesseramento (art. 16, c. 2, D.Lgs. 36/2021; statuto art. 6.3).
 * Si registra quando è stato prestato: coincide con l'invio della domanda
 * (domande-iscrizione.md §2). Vuoto per adulti e minori sotto i 14 anni.
 * Il testo della dichiarazione comprende foto e video se il genitore ha
 * dato quel consenso (consenso_foto_video): non serve un campo a parte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_domande', function (Blueprint $table) {
            $table->dateTime('assenso_minore_il')->nullable()->after('consenso_foto_video');
        });
    }

    public function down(): void
    {
        Schema::table('gl_domande', function (Blueprint $table) {
            $table->dropColumn('assenso_minore_il');
        });
    }
};
