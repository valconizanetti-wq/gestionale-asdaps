<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_domande', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();

            // Anagrafica del richiedente
            $table->string('cognome');
            $table->string('nome');
            $table->date('data_nascita')->nullable();
            $table->string('luogo_nascita')->nullable();
            $table->string('codice_fiscale', 16)->nullable();
            $table->string('sesso', 10)->nullable();
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->string('indirizzo')->nullable();
            $table->string('cap', 10)->nullable();
            $table->string('citta')->nullable();
            $table->string('provincia', 5)->nullable();
            $table->enum('tipo_socio', ['ordinario', 'ordinario_rivista', 'giovane', 'minorenne', 'vitalizio'])
                  ->default('ordinario');

            // Per richiedenti minorenni
            $table->string('genitore_nome')->nullable();
            $table->string('genitore_codice_fiscale', 16)->nullable();
            $table->string('genitore_email')->nullable();

            // Tracciamento e consensi
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('informativa_aperta_il')->nullable();
            $table->string('informativa_hash', 100)->nullable();
            $table->boolean('consenso_privacy')->default(false);
            $table->boolean('consenso_arbitrato')->default(false);
            $table->boolean('consenso_extra_ue')->default(false);
            $table->boolean('consenso_foto_video')->default(false);

            // Iter della domanda
            $table->enum('stato', [
                'in_verifica', 'in_attesa', 'approvata', 'rifiutata',
                'da_rifiutare', 'in_parita', 'scaduta',
            ])->default('in_attesa');
            $table->string('token_conferma', 64)->nullable();
            $table->dateTime('token_conferma_scadenza')->nullable();
            $table->timestamp('scadenza_cd')->nullable();
            $table->text('motivo_rifiuto')->nullable();
            $table->text('commento_domanda')->nullable();
            $table->boolean('cartacea')->default(false);
            $table->boolean('is_test')->default(false);
            $table->boolean('silenzio_assenso')->default(false);

            $table->foreignId('verbale_cd_id')
                  ->nullable()
                  ->constrained('gl_verbali')
                  ->nullOnDelete();

            $table->string('ricevuta_path', 500)->nullable();
            $table->timestamp('ricevuta_at')->nullable();

            $table->foreignId('socio_id')
                  ->nullable()
                  ->constrained('gl_soci')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_domande');
    }
};
