<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_gdpr_incaricati', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->string('nome', 100);
            $table->string('cognome', 100);
            $table->string('ruolo', 100);
            $table->string('email', 200)->nullable();
            $table->enum('sesso', ['M', 'F'])->nullable();
            $table->text('testo_lettera')->nullable();
            $table->text('trattamenti_autorizzati');
            $table->text('istruzioni')->nullable();
            $table->date('data_nomina');
            $table->enum('stato', ['bozza', 'inviata', 'firmata'])->default('bozza');
            $table->string('lettera_firmata_path', 500)->nullable();
            $table->date('revocato_il')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_gdpr_incaricati');
    }
};
