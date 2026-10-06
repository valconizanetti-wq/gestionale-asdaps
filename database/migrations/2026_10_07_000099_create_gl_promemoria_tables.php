<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promemoria: email automatiche pianificate, servizio generale per tutte le
 * funzioni del gestionale (promemoria.md, v0.36.0; richiesta di Paolo del
 * 06/10/2026). La funzione (chiave nel codice, App\Promemoria\Funzioni)
 * decide destinatari, segnaposti e sezione dei permessi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_promemoria', function (Blueprint $table) {
            $table->id();
            $table->string('funzione', 60);
            $table->string('nome', 150);
            $table->boolean('attivo')->default(true);
            // 'data_fissa': ogni anno giorno/mese; 'relativa': giorni prima (<0) o dopo (>0) una data del destinatario
            $table->string('quando', 20)->default('data_fissa');
            $table->unsignedTinyInteger('giorno')->nullable();
            $table->unsignedTinyInteger('mese')->nullable();
            $table->smallInteger('giorni')->nullable();
            $table->time('ora')->default('09:00:00');
            $table->json('parametri')->nullable();
            $table->string('oggetto', 200);
            $table->text('testo');
            $table->foreignId('creato_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gl_promemoria_invii', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promemoria_id')->constrained('gl_promemoria')->cascadeOnDelete();
            // Periodo dell'invio (es. anno sociale "2027"): un destinatario riceve
            // lo stesso promemoria una volta sola per periodo.
            $table->string('periodo', 20);
            $table->foreignId('socio_id')->nullable()->constrained('gl_soci')->nullOnDelete();
            $table->string('chiave_destinatario', 60)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('esito', 20); // inviato, fallito, senza_email, prova
            $table->foreignId('inviato_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamp('inviato_il');
            $table->unique(['promemoria_id', 'periodo', 'chiave_destinatario'], 'gl_promemoria_invii_unico');
            $table->index(['promemoria_id', 'inviato_il']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_promemoria_invii');
        Schema::dropIfExists('gl_promemoria');
    }
};
