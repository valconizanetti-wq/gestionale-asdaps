<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_volontari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')
                  ->constrained('gl_soci'); // niente onDelete: come nello schema originale (RESTRICT implicito)

            $table->string('mansione', 100);
            $table->json('mansioni_extra')->nullable();
            $table->date('data_inizio_attivita');
            $table->date('data_fine_attivita')->nullable();
            $table->boolean('cessazione_vidimata')->default(false);
            $table->date('vidimato_pec_il')->nullable();

            $table->string('assicurazione_compagnia', 100);
            $table->string('assicurazione_numero', 100);
            $table->date('assicurazione_scadenza');
            $table->string('path_polizza', 500)->nullable();
            $table->string('path_documento_identita', 500)->nullable();
            $table->date('scadenza_documento_identita')->nullable();

            $table->boolean('consenso_trattamento')->default(false);
            $table->boolean('consenso_comunicazioni')->default(false);
            $table->text('note')->nullable();

            $table->enum('status', ['bozza', 'vidimato', 'cessato'])->default('bozza');

            $table->foreignId('sessione_id')
                  ->nullable()
                  ->constrained('gl_volontari_sessioni')
                  ->nullOnDelete();
            $table->foreignId('sessione_cessazione_id')
                  ->nullable()
                  ->constrained('gl_volontari_sessioni')
                  ->nullOnDelete();

            $table->unsignedInteger('numero_registro')->nullable();
            $table->unsignedInteger('pagina_registro')->nullable();
            $table->unsignedInteger('pagina_cessazione')->nullable();
            $table->string('hash_record', 64)->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_volontari');
    }
};
