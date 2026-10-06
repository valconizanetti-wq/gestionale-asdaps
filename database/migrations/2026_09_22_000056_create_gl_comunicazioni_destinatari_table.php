<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_comunicazioni_destinatari', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comunicazione_id')
                  ->constrained('gl_comunicazioni')
                  ->cascadeOnDelete();
            $table->foreignId('socio_id')
                  ->nullable()
                  ->constrained('gl_soci')
                  ->nullOnDelete();
            $table->string('nome', 200);
            $table->string('email', 200)->nullable();
            $table->enum('esito', ['inviata', 'fallita', 'da_consegnare', 'consegnata_mano'])
                  ->default('da_consegnare');
            $table->dateTime('inviata_il')->nullable();
            $table->text('errore')->nullable();
            $table->integer('tentativi')->default(0);
            $table->date('data_notifica_manuale')->nullable();
            $table->text('note_consegna')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_comunicazioni_destinatari');
    }
};
