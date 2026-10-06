<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_gdpr_documenti', function (Blueprint $table) {
            $table->id();
            $table->enum('categoria', ['informativa', 'dpa', 'registro_art30', 'consenso', 'lettera_incarico', 'altro']);
            $table->string('nome', 200);
            $table->string('versione', 20)->nullable();
            $table->string('path', 500);
            $table->text('note')->nullable();
            $table->foreignId('caricato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_gdpr_documenti');
    }
};
