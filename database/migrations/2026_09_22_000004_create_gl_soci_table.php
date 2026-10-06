<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_soci', function (Blueprint $table) {
            $table->id();

            // Tessera
            $table->string('numero_tessera', 30)->nullable();
            $table->unsignedInteger('numero_tessera_int')->nullable()->unique();
            $table->foreignId('blocco_tessera_id')
                  ->nullable()
                  ->constrained('gl_tessere_blocchi')
                  ->nullOnDelete();
            $table->unsignedSmallInteger('anno_iscrizione')->default(date('Y'));

            // Tipo
            $table->enum('tipo', ['ordinario', 'giovane', 'minorenne'])->default('ordinario');

            // Stato
            $table->enum('stato', ['attivo', 'sospeso', 'cancellato'])->default('attivo');
            $table->date('data_cancellazione')->nullable();
            $table->string('motivo_cancellazione')->nullable();

            // Privacy / pseudonimizzazione (es. dopo cancellazione, per conservare i dati contabili)
            $table->boolean('pseudonimizzato')->default(false);

            // Anagrafica
            $table->string('cognome');
            $table->string('nome');
            $table->date('data_nascita')->nullable();
            $table->string('luogo_nascita')->nullable();
            $table->string('codice_fiscale', 16)->nullable()->unique();
            $table->enum('sesso', ['M', 'F', 'altro'])->nullable();
            $table->string('nazionalita')->nullable()->default('Italiana');

            // Residenza
            $table->string('indirizzo')->nullable();
            $table->string('cap', 10)->nullable();
            $table->string('citta')->nullable();
            $table->string('provincia', 5)->nullable();

            // Contatti
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('cellulare')->nullable();

            // Per soci minorenni
            $table->string('tutore_nome')->nullable();
            $table->string('tutore_relazione')->nullable(); // genitore, tutore legale, ecc.
            $table->string('tutore_telefono')->nullable();
            $table->string('tutore_email')->nullable();

            // Quote
            $table->date('data_prima_iscrizione');
            $table->date('quota_scadenza')->nullable(); // fine validità quota corrente

            // Note
            $table->text('note')->nullable();
            $table->boolean('scheda_registrazione_presente')->default(false);

            // Provenienza del record
            $table->foreignId('inserito_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->boolean('importato')->default(false);

            $table->timestamps();
            $table->softDeletes(); // cancellazione logica, con conservazione dati contabili

            // Consenso privacy
            $table->timestamp('privacy_accettata_il')->nullable();
            $table->string('privacy_ip', 45)->nullable();
            $table->string('privacy_versione', 10)->nullable();
            $table->enum('privacy_metodo', ['online', 'presenza', 'cartaceo'])->nullable();
            $table->string('privacy_doc_path', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_soci');
    }
};
