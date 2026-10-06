<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_volontari_autodichiarazioni', function (Blueprint $table) {
            $table->id();

            $table->foreignId('volontario_id')
                  ->constrained('gl_volontari')
                  ->cascadeOnDelete();

            $table->unsignedSmallInteger('anno');
            $table->decimal('importo_dichiarato', 10, 2)->default(0);
            $table->date('data_dichiarazione');

            // Calcolato in applicazione (importo_dichiarato > 15000), salvato
            // per poterlo interrogare/filtrare direttamente via query.
            $table->boolean('sopra_soglia')->default(false);

            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['volontario_id', 'anno']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_volontari_autodichiarazioni');
    }
};
