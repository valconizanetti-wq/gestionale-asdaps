<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scontrini dal telefono con OCR (v0.39.0, analisi-scontrini.md §4, §8, §9).
 * - gl_prima_nota_scontrini: numero per anno, partita IVA letta, conto, nota
 *   del consigliere, stato, esito della lettura, righe lette, anteprime;
 *   l'allegato unico diventa facoltativo (le foto stanno in
 *   gl_prima_nota_scontrini_file);
 * - gl_prima_nota_scontrini_righe: le righe dello scontrino, ognuna con la
 *   sua categoria e il movimento in cui è finita (un movimento per categoria);
 * - gl_prima_nota_scontrini_file: le foto (SC<anno>-<n.>[-<progr.>].<est>);
 * - gl_soggetti: partita IVA (unica se c'è), indirizzo, comune, attivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_soggetti', function (Blueprint $table) {
            $table->string('partita_iva', 11)->nullable()->unique()->after('nome');
            $table->string('indirizzo', 200)->nullable()->after('partita_iva');
            $table->string('comune', 100)->nullable()->after('indirizzo');
            $table->boolean('attivo')->default(true)->after('comune');
        });

        Schema::table('gl_prima_nota_scontrini', function (Blueprint $table) {
            $table->unsignedInteger('numero')->nullable()->after('id');
            $table->string('partita_iva', 11)->nullable()->after('ragione_sociale_raw');
            $table->string('conto', 10)->default('cassa')->after('importo_totale');
            $table->string('nota', 500)->nullable()->after('conto');
            // da_verificare: almeno un movimento su «Uscite da classificare»;
            // da_quadrare: i movimenti non sommano al totale; verificato.
            $table->string('stato', 20)->default('da_verificare')->after('nota');
            $table->string('ocr_esito', 20)->nullable()->after('ocr_testo_grezzo');
            $table->unsignedInteger('ocr_ms')->nullable()->after('ocr_esito');
            $table->json('righe_lette')->nullable()->after('ocr_ms');
            $table->string('anteprima_path')->nullable()->after('allegato_originale');
            $table->string('miniatura_path')->nullable()->after('anteprima_path');
            $table->string('allegato_path')->nullable()->change();
            $table->unique(['anno', 'numero']);
            $table->index('stato');
        });

        Schema::create('gl_prima_nota_scontrini_righe', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scontrino_id')->constrained('gl_prima_nota_scontrini')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->string('descrizione', 200);
            $table->decimal('importo', 10, 2);
            $table->foreignId('categoria_id')->nullable()->constrained('gl_prima_nota_categorie')->nullOnDelete();
            $table->foreignId('prima_nota_id')->nullable()->constrained('gl_prima_nota')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gl_prima_nota_scontrini_file', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scontrino_id')->constrained('gl_prima_nota_scontrini')->cascadeOnDelete();
            $table->string('path');
            $table->string('nome', 100);
            $table->string('originale', 255)->nullable();
            $table->foreignId('caricato_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_prima_nota_scontrini_file');
        Schema::dropIfExists('gl_prima_nota_scontrini_righe');
        Schema::table('gl_prima_nota_scontrini', function (Blueprint $table) {
            $table->dropUnique(['anno', 'numero']);
            $table->dropIndex(['stato']);
            $table->dropColumn(['numero', 'partita_iva', 'conto', 'nota', 'stato', 'ocr_esito', 'ocr_ms', 'righe_lette', 'anteprima_path', 'miniatura_path']);
        });
        Schema::table('gl_soggetti', function (Blueprint $table) {
            $table->dropUnique(['partita_iva']);
            $table->dropColumn(['partita_iva', 'indirizzo', 'comune', 'attivo']);
        });
    }
};
