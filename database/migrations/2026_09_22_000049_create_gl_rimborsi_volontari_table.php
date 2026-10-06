<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_rimborsi_volontari', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique()->comment('RIM-YYYY-NNN');
            $table->unsignedSmallInteger('anno')->nullable();
            $table->unsignedInteger('progressivo')->nullable();

            $table->foreignId('volontario_id')
                  ->constrained('gl_volontari');

            $table->date('data_rimborso');
            $table->string('descrizione', 500)->nullable();
            $table->string('attivita_svolta', 500)->nullable();
            $table->foreignId('categoria_id')
                  ->nullable()
                  ->constrained('gl_prima_nota_categorie')
                  ->nullOnDelete();
            $table->decimal('totale', 10, 2)->default(0);
            $table->enum('stato', ['bozza', 'in_attesa', 'pagato'])->default('bozza');
            $table->string('conto_pagamento', 10)->nullable()->comment('cc o cassa');
            $table->text('note')->nullable();
            $table->date('data_pagamento')->nullable();

            $table->foreignId('prima_nota_id')
                  ->nullable()
                  ->constrained('gl_prima_nota')
                  ->nullOnDelete();

            $table->string('allegato_path', 500)->nullable();
            $table->string('allegato_nome')->nullable();
            $table->timestamp('allegato_caricato_il')->nullable();
            $table->string('allegato_caricato_da', 150)->nullable(); // testo libero, come nello schema originale

            $table->foreignId('registrato_da')
                  ->constrained('gl_users');
            $table->string('creato_da', 150)->nullable(); // testo libero, come nello schema originale

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_rimborsi_volontari');
    }
};
