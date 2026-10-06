<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_prima_nota', function (Blueprint $table) {
            $table->id();

            $table->date('data');
            $table->enum('tipo', ['entrata', 'uscita']);
            $table->string('conto', 10)->nullable(); // cassa / banca, codificato a livello applicativo
            $table->decimal('importo', 10, 2);
            $table->string('descrizione');

            $table->foreignId('categoria_id')
                  ->nullable()
                  ->constrained('gl_prima_nota_categorie')
                  ->nullOnDelete();
            $table->foreignId('socio_id')
                  ->nullable()
                  ->constrained('gl_soci')
                  ->nullOnDelete();
            $table->foreignId('rinnovo_id')
                  ->nullable()
                  ->constrained('gl_rinnovi')
                  ->nullOnDelete();

            $table->string('giustificativo_path')->nullable();
            $table->string('giustificativo_nome')->nullable();
            $table->text('note')->nullable();

            // Dati del documento giustificativo / fornitore
            $table->string('soggetto', 200)->nullable();
            $table->string('tipo_documento', 50)->nullable();
            $table->string('numero_documento', 100)->nullable();
            $table->date('data_documento')->nullable();

            // Anticipi e rimborsi spese
            $table->string('anticipato_da', 200)->nullable();
            $table->boolean('sospeso')->default(false);
            $table->enum('tipo_anticipante', ['cd', 'esterno'])->nullable();
            $table->date('rimborsato_il')->nullable();
            $table->string('rimborso_nota_numero', 20)->nullable();
            $table->date('data_nota')->nullable();
            $table->string('iban_rimborso', 34)->nullable();

            // Collegamenti concettuali non ancora vincolati a livello di FK
            // (moduli non ancora presenti nel core: Progetti, Verbali, Banca Import;
            // giroconto_id/storno_di_id si riferiscono a un'altra riga di gl_prima_nota)
            $table->unsignedBigInteger('giroconto_id')->nullable();
            $table->unsignedBigInteger('progetto_id')->nullable();
            $table->unsignedBigInteger('chiuso_da_verb_id')->nullable();
            $table->unsignedBigInteger('storno_di_id')->nullable();
            $table->unsignedBigInteger('banca_import_id')->nullable();

            $table->boolean('is_locked')->default(false);
            $table->boolean('riconciliato_banca')->default(false);
            $table->string('descrizione_progetto')->nullable();

            $table->foreignId('registrato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_prima_nota');
    }
};
