<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_verbali_allegati', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verbale_id')
                  ->constrained('gl_verbali')
                  ->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('nome_originale');
            $table->unsignedBigInteger('dimensione');
            $table->unsignedInteger('ordine')->default(0);
            $table->foreignId('caricato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_verbali_allegati');
    }
};
