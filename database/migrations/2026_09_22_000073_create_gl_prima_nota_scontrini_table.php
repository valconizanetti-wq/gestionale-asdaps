<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_prima_nota_scontrini', function (Blueprint $table) {
            $table->id();

            $table->date('data_scontrino');
            $table->decimal('importo_totale', 10, 2);
            $table->unsignedSmallInteger('anno')->nullable();

            $table->foreignId('soggetto_id')
                  ->nullable()
                  ->constrained('gl_soggetti')
                  ->nullOnDelete();

            // Testo grezzo letto dall'OCR per la ragione sociale, prima del
            // meccanismo "trova o crea" su gl_soggetti — utile per audit/debug
            // se il collegamento a soggetto_id risulta sbagliato.
            $table->string('ragione_sociale_raw')->nullable();

            $table->boolean('ocr_utilizzato')->default(false);
            $table->text('ocr_testo_grezzo')->nullable();

            $table->string('allegato_path');
            $table->string('allegato_originale')->nullable();

            // Ricalcolato dall'applicazione ad ogni variazione dei movimenti
            // collegati: somma(gl_prima_nota.importo dove scontrino_id =
            // questo) confrontata con importo_totale. Se lo split è
            // disabilitato (gl_impostazioni), il movimento unico creato è
            // sempre quadrato per costruzione.
            $table->boolean('quadrato')->default(false);

            $table->text('note')->nullable();

            $table->foreignId('registrato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_prima_nota_scontrini');
    }
};
