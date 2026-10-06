<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_tesseramenti_qualifiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tesseramento_id')
                  ->constrained('gl_tesseramenti')
                  ->cascadeOnDelete();
            $table->foreignId('qualifica_id')
                  ->constrained('gl_qualifiche')
                  ->cascadeOnDelete();
            $table->unique(['tesseramento_id', 'qualifica_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_tesseramenti_qualifiche');
    }
};
