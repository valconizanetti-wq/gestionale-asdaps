<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v0.35.0 (06/10/2026), prima-nota.md §2: anni contabili.
 *
 * Sostituisce gl_prima_nota_anni_chiusi, che conteneva solo gli anni chiusi
 * e non aveva dove tenere i saldi di apertura. Un anno ha una riga quando
 * riceve il primo movimento (o quando lo apre il tesoriere) e resta
 * "aperto" finché non viene chiuso dopo l'approvazione del rendiconto.
 *
 * Saldi di apertura:
 * - primo anno del gestionale (nessuna riga per l'anno prima): si scrivono
 *   a mano, per la Compagnia dalla chiusura 2026 del vecchio gestionale
 *   WordPress (gestionale-acv, decisione di Paolo del 06/10/2026: si
 *   migrano solo i saldi);
 * - anni successivi: sono i saldi finali dell'anno prima, calcolati finché
 *   quello è aperto e salvati (saldo_chiusura_*) quando si chiude.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_anni_contabili', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno')->unique();
            $table->enum('stato', ['aperto', 'chiuso'])->default('aperto');
            // Solo per il primo anno: null = da impostare
            $table->decimal('saldo_apertura_cassa', 10, 2)->nullable();
            $table->decimal('saldo_apertura_banca', 10, 2)->nullable();
            $table->string('saldi_fonte', 255)->nullable();
            $table->string('saldi_documento_path')->nullable();
            $table->string('saldi_documento_nome')->nullable();
            // Fotografia dei saldi finali alla chiusura
            $table->decimal('saldo_chiusura_cassa', 10, 2)->nullable();
            $table->decimal('saldo_chiusura_banca', 10, 2)->nullable();
            // Approvazione del rendiconto (il modulo Verbali non c'è ancora:
            // riferimento testuale, come data_assemblea/numero_verbale di gl_bilancio)
            $table->date('approvazione_data')->nullable();
            $table->string('approvazione_rif', 255)->nullable();
            $table->dateTime('chiuso_il')->nullable();
            $table->foreignId('chiuso_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamps();
        });

        // Anni già chiusi nella vecchia tabella (in produzione nessuno).
        foreach (DB::table('gl_prima_nota_anni_chiusi')->get() as $r) {
            DB::table('gl_anni_contabili')->insert([
                'anno' => $r->anno, 'stato' => 'chiuso', 'chiuso_il' => $r->chiuso_il,
                'chiuso_da' => $r->chiuso_da, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('gl_prima_nota_anni_chiusi');
    }

    public function down(): void
    {
        Schema::create('gl_prima_nota_anni_chiusi', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno')->unique();
            $table->timestamp('chiuso_il')->nullable();
            $table->foreignId('chiuso_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamps();
        });

        foreach (DB::table('gl_anni_contabili')->where('stato', 'chiuso')->get() as $r) {
            DB::table('gl_prima_nota_anni_chiusi')->insert([
                'anno' => $r->anno, 'chiuso_il' => $r->chiuso_il, 'chiuso_da' => $r->chiuso_da,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('gl_anni_contabili');
    }
};
