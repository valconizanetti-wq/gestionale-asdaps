<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabella chiave/valore per impostazioni applicative generiche.
        // Risolve il gap gs_config segnalato in MANUALE-CORE.md §6
        // ("da valutare se serve ancora con Laravel") — qui la prima
        // esigenza concreta è la configurabilità dello split scontrini
        // (vedi gl_prima_nota_scontrini).
        Schema::create('gl_impostazioni', function (Blueprint $table) {
            $table->id();
            $table->string('chiave', 100)->unique();
            $table->text('valore')->nullable();
            $table->string('tipo', 20)->default('string'); // string/boolean/integer/json
            $table->text('descrizione')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_impostazioni');
    }
};
