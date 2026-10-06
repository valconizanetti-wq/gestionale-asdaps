<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_rinnovi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('socio_id')
                  ->constrained('gl_soci')
                  ->cascadeOnDelete();

            $table->unsignedSmallInteger('anno');
            $table->decimal('importo', 8, 2);
            $table->date('data_pagamento');
            $table->enum('metodo_pagamento', ['contante', 'bonifico', 'altro'])->default('contante');

            // Ricevuta
            $table->string('numero_ricevuta', 20)->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('ricevuta_inviata_il')->nullable();
            $table->enum('ricevuta_stato', ['non_generata', 'generata', 'inviata', 'errore'])
                  ->default('non_generata');

            $table->text('note')->nullable();

            $table->foreignId('registrato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->unique(['socio_id', 'anno']);           // un solo rinnovo per anno per socio
            $table->unique(['anno', 'numero_ricevuta']);    // numerazione ricevute non duplicabile nello stesso anno
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_rinnovi');
    }
};
