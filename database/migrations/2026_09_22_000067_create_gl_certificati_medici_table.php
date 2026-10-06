<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_certificati_medici', function (Blueprint $table) {
            $table->id();

            $table->foreignId('socio_id')
                  ->constrained('gl_soci')
                  ->cascadeOnDelete();

            // Nullable e non 1:1 con il tesseramento: un certificato agonistico
            // (validità biennale per il tiro con l'arco) può coprire più stagioni
            // di tesseramento (tipicamente annuale).
            $table->foreignId('tesseramento_id')
                  ->nullable()
                  ->constrained('gl_tesseramenti')
                  ->nullOnDelete();

            $table->enum('tipo', ['non_agonistico', 'agonistico'])->default('non_agonistico');

            $table->date('data_visita');
            $table->date('data_scadenza');

            $table->string('medico_nome')->nullable();
            $table->string('struttura')->nullable();

            $table->enum('stato', ['idoneo', 'non_idoneo', 'sospeso'])->default('idoneo');

            $table->string('file_path')->nullable();
            $table->string('file_originale')->nullable();

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
        Schema::dropIfExists('gl_certificati_medici');
    }
};
