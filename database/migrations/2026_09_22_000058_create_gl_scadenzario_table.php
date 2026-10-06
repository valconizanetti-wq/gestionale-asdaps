<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_scadenzario', function (Blueprint $table) {
            $table->id();

            $table->string('titolo');
            $table->text('descrizione')->nullable();

            // Tipo: fisso (ricorrente ogni anno) o una-tantum
            $table->enum('tipo', ['ricorrente', 'una_tantum'])->default('ricorrente');

            // Categoria per raggruppamento ('sda' generalizzata in 'rete_affiliazione',
            // coerente con gl_associazione.rete_affiliazione_*)
            $table->enum('categoria', ['runts', 'rete_affiliazione', 'assemblea', 'contabile', 'altro'])
                  ->default('altro');

            $table->date('data_scadenza');

            // Per le ricorrenti: giorno e mese fissi (es. 30 giugno ogni anno)
            $table->unsignedSmallInteger('giorno_ricorrenza')->nullable(); // 1-31
            $table->unsignedSmallInteger('mese_ricorrenza')->nullable();   // 1-12

            $table->unsignedSmallInteger('giorni_preavviso')->default(30);

            $table->boolean('completato')->default(false);
            $table->date('completato_il')->nullable();
            $table->text('note_completamento')->nullable();

            $table->foreignId('assegnato_a')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->foreignId('creato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_scadenzario');
    }
};
