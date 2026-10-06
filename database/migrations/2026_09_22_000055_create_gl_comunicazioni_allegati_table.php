<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_comunicazioni_allegati', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comunicazione_id')
                  ->constrained('gl_comunicazioni')
                  ->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_originale');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_comunicazioni_allegati');
    }
};
