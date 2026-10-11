<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scadenze dell'associazione (v0.38.0, scadenzario.md; tavole SC-1…SC-3).
 *
 * - gl_scadenzario:
 *   - chiave: le scadenze che il gestionale conosce per nome
 *     («affiliazione_eps», «polizza_rc», «rendiconto»): riquadri di
 *     Impostazioni › Ente e avvisi le trovano da qui, e non si eliminano;
 *   - prima_nota_categoria_id: scadenza con un pagamento («Rinnovata»
 *     registra l'uscita in quella categoria); vuota = adempimento («Fatta»);
 *   - categoria: in più «assicurazione» (polizza RC).
 * - gl_scadenzario_rinnovi: storico di ogni «Rinnovata» o «Fatta»: data
 *   della scadenza soddisfatta, pagamento (importo, conto, movimento) e
 *   prossima scadenza. L'ultimo rinnovo si mostra nei riquadri.
 * - gl_associazione: contatto del referente dell'EPS e dati della polizza
 *   RC (compagnia, numero, copertura). La scadenza non sta qui: la tiene
 *   lo Scadenzario (un solo dato); rete_affiliazione_scadenza ne è una copia
 *   aggiornata da «Rinnovata».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_scadenzario', function (Blueprint $table) {
            $table->string('chiave', 40)->nullable()->unique()->after('id');
            $table->foreignId('prima_nota_categoria_id')->nullable()->after('categoria')
                ->constrained('gl_prima_nota_categorie')->nullOnDelete();
            $table->index(['completato', 'data_scadenza']);
        });
        DB::statement("ALTER TABLE gl_scadenzario MODIFY categoria ENUM('runts','rete_affiliazione','assicurazione','assemblea','contabile','altro') NOT NULL DEFAULT 'altro'");

        Schema::create('gl_scadenzario_rinnovi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scadenza_id')->constrained('gl_scadenzario')->restrictOnDelete();
            $table->date('data_scadenza');            // la scadenza soddisfatta
            $table->date('fatto_il');                 // pagata o fatta il
            $table->decimal('importo', 10, 2)->nullable();
            $table->string('conto', 10)->nullable();
            $table->foreignId('prima_nota_id')->nullable()->constrained('gl_prima_nota')->nullOnDelete();
            $table->date('prossima_scadenza')->nullable();   // null: una tantum chiusa
            $table->text('note')->nullable();
            $table->foreignId('registrato_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['scadenza_id', 'fatto_il']);
        });

        Schema::table('gl_associazione', function (Blueprint $table) {
            $table->string('rete_affiliazione_contatto')->nullable()->after('rete_affiliazione_referente');
            $table->string('polizza_compagnia')->nullable()->after('rete_affiliazione_scadenza');
            $table->string('polizza_numero', 100)->nullable()->after('polizza_compagnia');
            $table->string('polizza_copertura')->nullable()->after('polizza_numero');
        });
    }

    public function down(): void
    {
        Schema::table('gl_associazione', function (Blueprint $table) {
            $table->dropColumn(['rete_affiliazione_contatto', 'polizza_compagnia', 'polizza_numero', 'polizza_copertura']);
        });
        Schema::dropIfExists('gl_scadenzario_rinnovi');
        DB::table('gl_scadenzario')->where('categoria', 'assicurazione')->update(['categoria' => 'altro']);
        DB::statement("ALTER TABLE gl_scadenzario MODIFY categoria ENUM('runts','rete_affiliazione','assemblea','contabile','altro') NOT NULL DEFAULT 'altro'");
        Schema::table('gl_scadenzario', function (Blueprint $table) {
            $table->dropIndex(['completato', 'data_scadenza']);
            $table->dropConstrainedForeignId('prima_nota_categoria_id');
            $table->dropUnique(['chiave']);
            $table->dropColumn('chiave');
        });
    }
};
