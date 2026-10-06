<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_tesseramenti_divisioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tesseramento_id')
                  ->constrained('gl_tesseramenti')
                  ->cascadeOnDelete();
            $table->foreignId('divisione_id')
                  ->constrained('gl_divisioni_arco')
                  ->cascadeOnDelete();
            $table->unique(['tesseramento_id', 'divisione_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_tesseramenti_divisioni');
    }
};
