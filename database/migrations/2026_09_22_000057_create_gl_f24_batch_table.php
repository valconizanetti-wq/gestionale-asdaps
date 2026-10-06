<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_f24_batch', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('mese')->comment('1-12');
            $table->unsignedSmallInteger('anno');
            $table->decimal('totale_lordi', 12, 2)->default(0);
            $table->decimal('totale_ritenute', 12, 2)->default(0);
            $table->string('codice_tributo', 10)->default('1040');
            $table->enum('stato', ['in_attesa', 'non_documentato', 'versato'])->default('in_attesa');
            $table->date('scadenza')->nullable()->comment('Giorno 16 del mese successivo');
            $table->date('data_versamento')->nullable();
            $table->string('quietanza_path', 500)->nullable();
            $table->string('quietanza_nome_originale')->nullable();
            $table->foreignId('prima_nota_id')
                  ->nullable()
                  ->constrained('gl_prima_nota')
                  ->nullOnDelete();
            $table->foreignId('versato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamp('versato_il')->nullable();
            $table->timestamps();

            $table->unique(['mese', 'anno']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_f24_batch');
    }
};
