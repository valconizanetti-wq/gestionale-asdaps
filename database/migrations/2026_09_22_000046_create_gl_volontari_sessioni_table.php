<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_volontari_sessioni', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('numero');
            $table->year('anno');
            $table->timestamp('data_generazione');
            $table->date('periodo_dal');
            $table->date('periodo_al');
            $table->unsignedInteger('pagina_inizio')->nullable();
            $table->unsignedInteger('pagina_fine')->nullable();
            $table->string('file_pdf_path', 500)->nullable();
            $table->string('file_firmato_path', 500)->nullable();
            $table->string('file_pec_ricevuta_path', 500)->nullable();
            $table->timestamp('timestamp_consegna')->nullable();
            $table->string('hash_controllo', 64)->nullable();
            $table->string('protocollo_pec', 100)->nullable();
            $table->enum('status', ['bozza', 'pdf_generato', 'firmato', 'pec_inviata', 'archiviata'])
                  ->default('bozza');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_volontari_sessioni');
    }
};
