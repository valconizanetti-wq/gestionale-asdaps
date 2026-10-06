<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_progetti_preventivo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progetto_id')
                  ->constrained('gl_progetti')
                  ->cascadeOnDelete();
            $table->foreignId('categoria_id')
                  ->nullable()
                  ->constrained('gl_prima_nota_categorie')
                  ->nullOnDelete();
            $table->enum('tipo', ['E', 'U']); // Entrata / Uscita
            $table->string('codice_dm39', 5)->nullable();
            $table->string('descrizione');
            $table->decimal('importo', 10, 2)->default(0);
            $table->integer('ordine')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_progetti_preventivo');
    }
};
