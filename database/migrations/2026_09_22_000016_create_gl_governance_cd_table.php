<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_governance_cd', function (Blueprint $table) {
            $table->id();
            $table->string('cognome');
            $table->string('nome');
            $table->date('data_nascita')->nullable();
            $table->string('luogo_nascita')->nullable();
            $table->string('codice_fiscale', 16)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('carica'); // es. Presidente, Segretario, Consigliere...
            $table->date('data_inizio_mandato');
            $table->date('data_fine_mandato')->nullable();
            $table->boolean('in_carica')->default(true);
            $table->string('delibera_assemblea')->nullable();
            $table->date('delibera_data')->nullable();
            $table->text('curriculum')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('inserito_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_governance_cd');
    }
};
