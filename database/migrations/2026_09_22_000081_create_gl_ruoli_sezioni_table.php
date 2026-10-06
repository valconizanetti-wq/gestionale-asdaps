<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_ruoli_sezioni', function (Blueprint $table) {
            $table->id();

            $table->string('ruolo_codice', 50);
            $table->foreign('ruolo_codice')
                  ->references('codice')->on('gl_ruoli')
                  ->cascadeOnDelete();

            $table->foreignId('sezione_id')
                  ->constrained('gl_sezioni')
                  ->cascadeOnDelete();

            // Unica eccezione ai permessi "a sezione intera": il Revisore
            // vede le sezioni contabili in sola lettura, non le modifica.
            // Non introduce una granularità per-funzione: resta un singolo
            // flag sull'associazione ruolo-sezione, non un elenco di azioni.
            $table->boolean('sola_lettura')->default(false);

            $table->unique(['ruolo_codice', 'sezione_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_ruoli_sezioni');
    }
};
