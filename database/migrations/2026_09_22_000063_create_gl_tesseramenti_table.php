<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_tesseramenti', function (Blueprint $table) {
            $table->id();

            $table->foreignId('socio_id')
                  ->constrained('gl_soci')
                  ->cascadeOnDelete();

            $table->unsignedSmallInteger('stagione_sportiva');

            $table->string('ente_affiliante', 50)->default('CSEN');
            $table->string('numero_tessera_csen', 30);

            $table->date('data_inserimento')->nullable();
            $table->date('data_rilascio');

            // Pacchetto/livello assicurativo CSEN (es. "Base", "Silver Base", "Gold Base").
            // Stringa libera, non enum: CSEN può introdurre nuovi pacchetti senza
            // richiedere una migration.
            $table->string('pacchetto_tesseramento', 50)->nullable();

            $table->foreignId('tecnico_riferimento_id')
                  ->nullable()
                  ->constrained('gl_tecnici')
                  ->nullOnDelete();

            $table->enum('stato', ['attivo', 'scaduto', 'sospeso'])->default('attivo');

            $table->text('note')->nullable();

            $table->foreignId('inserito_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->unique(['socio_id', 'stagione_sportiva']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_tesseramenti');
    }
};
