<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_gdpr_trattamenti', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 200);
            $table->text('finalita');
            $table->text('base_giuridica')->nullable();
            $table->string('categorie_speciali', 100)->nullable();
            $table->string('categorie_interessati', 200);
            $table->text('categorie_dati');
            $table->text('responsabili_esterni')->nullable();
            $table->string('trasferimenti_esteri', 100)->nullable();
            $table->string('conservazione', 200);
            $table->text('misure_sicurezza');
            $table->text('dpia_note')->nullable();
            $table->string('responsabile_interno', 100)->nullable();
            $table->date('ultima_revisione')->nullable();
            $table->string('note_modifica', 500)->nullable();
            $table->date('cessato_il')->nullable();
            $table->foreignId('creato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_gdpr_trattamenti');
    }
};
