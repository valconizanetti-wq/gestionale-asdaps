<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_erogazioni_liberali', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('prima_nota_id')
                  ->constrained('gl_prima_nota');
            $table->foreignId('soggetto_id')
                  ->constrained('gl_soggetti');
            $table->date('data_erogazione');
            $table->decimal('importo', 10, 2);
            $table->string('conto', 10);
            $table->string('causale', 500);
            $table->text('note')->nullable();
            $table->enum('stato', ['valida', 'annullata'])->default('valida');
            $table->text('motivo_annullamento')->nullable();
            $table->date('annullata_il')->nullable();
            $table->foreignId('storno_prima_nota_id')
                  ->nullable()
                  ->constrained('gl_prima_nota')
                  ->nullOnDelete();
            $table->dateTime('inviata_email_il')->nullable();
            $table->foreignId('registrato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_erogazioni_liberali');
    }
};
