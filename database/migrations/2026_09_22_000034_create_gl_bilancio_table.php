<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_bilancio', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno');
            $table->string('tipo', 20)->default('preventivo'); // preventivo / consuntivo
            $table->enum('stato', ['bozza', 'approvato'])->default('bozza');
            $table->date('approvato_il')->nullable();
            $table->date('data_assemblea')->nullable();
            $table->string('numero_verbale', 50)->nullable();
            $table->text('note_integrative')->nullable();
            $table->string('pdf_approvato_path', 500)->nullable();
            $table->string('codice_documento', 20)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('creato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->unique(['anno', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_bilancio');
    }
};
