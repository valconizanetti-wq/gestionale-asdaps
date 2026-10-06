<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_soci_documenti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')
                  ->constrained('gl_soci')
                  ->cascadeOnDelete();
            $table->string('categoria', 20);
            $table->string('descrizione')->nullable();
            $table->string('file_path');
            $table->string('file_originale');
            $table->foreignId('caricato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_soci_documenti');
    }
};
