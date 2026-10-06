<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_tecnici', function (Blueprint $table) {
            $table->id();

            // Un tecnico può essere un socio già registrato, oppure un nominativo
            // esterno inserito a mano. A livello applicativo va validato che
            // almeno uno dei due campi sia valorizzato.
            $table->foreignId('socio_id')
                  ->nullable()
                  ->constrained('gl_soci')
                  ->nullOnDelete();

            $table->string('nome_esterno')->nullable();

            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_tecnici');
    }
};
