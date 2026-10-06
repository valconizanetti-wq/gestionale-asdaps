<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_rimborsi_volontari_voci', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rimborso_id')
                  ->constrained('gl_rimborsi_volontari')
                  ->cascadeOnDelete();
            $table->enum('tipo', ['km', 'viaggio', 'vitto']);
            $table->string('descrizione', 300)->nullable();
            $table->string('tratta', 300)->nullable();
            $table->string('mezzo', 100)->nullable();
            $table->decimal('km', 8, 2)->nullable();
            $table->decimal('tariffa_aci', 8, 4)->nullable();
            $table->unsignedTinyInteger('persone')->default(1);
            $table->date('data_pasto')->nullable();
            $table->text('note')->nullable();
            $table->decimal('quantita', 8, 2)->nullable()->comment('Km oppure null');
            $table->decimal('importo_unitario', 8, 4)->nullable()->comment('Tariffa ACI per km');
            $table->decimal('importo', 10, 2);
            $table->date('data_voce')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_rimborsi_volontari_voci');
    }
};
