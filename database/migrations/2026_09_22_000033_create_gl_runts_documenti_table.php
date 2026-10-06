<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_runts_documenti', function (Blueprint $table) {
            $table->id();
            $table->string('nome_file');
            $table->string('nome_originale');
            $table->string('tipo_documento', 50)->default('altro');
            $table->date('data_documento')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('uploaded_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_runts_documenti');
    }
};
