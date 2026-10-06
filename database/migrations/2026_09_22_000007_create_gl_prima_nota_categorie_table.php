<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_prima_nota_categorie', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['entrata', 'uscita']);
            $table->string('nome');
            $table->string('codice', 20)->nullable();
            $table->string('codice_dm39', 5)->nullable(); // classificazione bilancio DM39
            $table->boolean('flag_ente_pubblico')->default(false);
            $table->boolean('flag_non_istituzionale')->default(false);
            $table->string('esempio')->nullable();
            $table->boolean('attiva')->default(true);
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->foreignId('creata_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_prima_nota_categorie');
    }
};
