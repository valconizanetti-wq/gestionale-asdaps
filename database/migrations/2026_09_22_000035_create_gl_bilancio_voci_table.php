<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_bilancio_voci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bilancio_id')
                  ->constrained('gl_bilancio')
                  ->cascadeOnDelete();
            $table->enum('tipo', ['entrata', 'uscita']);
            $table->string('codice', 20)->nullable(); // codice DM 39/2020
            $table->string('descrizione');
            $table->decimal('importo_preventivo', 10, 2)->default(0);
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->foreignId('categoria_id')
                  ->nullable()
                  ->constrained('gl_prima_nota_categorie')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_bilancio_voci');
    }
};
