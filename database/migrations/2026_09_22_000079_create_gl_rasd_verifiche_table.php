<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Traccia le verifiche sistematiche condotte da Sport e Salute
        // (incrocio con Anagrafe Tributaria) sulle ASD iscritte al RASD,
        // attive dal 2026, con 90 giorni per regolarizzare eventuali
        // anomalie riscontrate (vedi MANUALE-ASD.md §2.1). Evento
        // ripetibile nel tempo, non uno stato — stesso pattern già usato
        // per gl_gdpr_violazioni.
        Schema::create('gl_rasd_verifiche', function (Blueprint $table) {
            $table->id();

            $table->date('data_verifica');
            $table->text('anomalie_riscontrate');
            $table->date('scadenza_regolarizzazione');

            $table->enum('stato', ['aperta', 'regolarizzata', 'chiusa_senza_seguito'])
                  ->default('aperta');
            $table->date('regolarizzata_il')->nullable();
            $table->text('azioni_correttive')->nullable();

            $table->text('note')->nullable();

            $table->foreignId('gestita_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_rasd_verifiche');
    }
};
