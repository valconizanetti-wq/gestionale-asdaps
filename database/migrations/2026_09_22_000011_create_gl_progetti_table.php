<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_progetti', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->integer('anno');
            $table->text('descrizione')->nullable();
            $table->enum('stato', ['in_pianificazione', 'approvato', 'concluso', 'archiviato'])
                  ->default('in_pianificazione');
            $table->longText('relazione_attivita')->nullable();
            $table->string('codice_documento', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_progetti');
    }
};
