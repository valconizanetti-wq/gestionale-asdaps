<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_tesseramenti_specialita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tesseramento_id')
                  ->constrained('gl_tesseramenti')
                  ->cascadeOnDelete();
            $table->foreignId('specialita_id')
                  ->constrained('gl_specialita_gara')
                  ->cascadeOnDelete();
            $table->unique(['tesseramento_id', 'specialita_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_tesseramenti_specialita');
    }
};
