<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_soggetti', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 200)->unique();
            $table->string('email', 150)->nullable();
            // categoria_id: nessuna tabella di categorie trovata nello schema originale
            // (colonna orfana, senza FK anche in produzione) — lasciata come semplice riferimento libero
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_soggetti');
    }
};
