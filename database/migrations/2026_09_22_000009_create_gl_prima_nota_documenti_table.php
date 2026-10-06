<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_prima_nota_documenti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prima_nota_id')
                  ->constrained('gl_prima_nota')
                  ->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('nome');
            $table->string('descrizione', 500)->nullable();
            $table->foreignId('caricato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_prima_nota_documenti');
    }
};
