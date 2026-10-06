<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_associazione', function (Blueprint $table) {
            $table->id();

            // Dati istituzionali
            $table->string('ragione_sociale');
            $table->string('nome_breve')->nullable(); // nome informale/colloquiale, es. "La Dante"
            $table->string('codice_fiscale', 20);
            $table->string('forma_giuridica')->default('Associazione di Promozione Sociale');

            // Sede
            $table->string('indirizzo');
            $table->string('cap', 10);
            $table->string('citta');
            $table->string('provincia', 5);

            // Contatti
            $table->string('email')->nullable();
            $table->string('email_pec')->nullable();
            $table->string('telefono')->nullable();
            $table->string('sito_web')->nullable();

            // Comunicazioni
            $table->string('firma_comunicazioni')->nullable(); // formula di chiusura email, es. "il Comitato di Orvieto"

            // Immagini istituzionali
            $table->string('logo_path')->nullable();
            $table->string('firma_path')->nullable();
            $table->string('firma_testo')->nullable();
            $table->string('timbro_path', 500)->nullable();

            // Dati bancari (visibili solo a presidente e tesoriere)
            $table->string('banca_nome')->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('bic_swift', 15)->nullable();

            // RUNTS
            $table->string('runts_numero')->nullable();
            $table->date('runts_data_iscrizione')->nullable();
            $table->string('runts_sezione')->nullable();

            // Rete di affiliazione (federazione nazionale, se presente — es. Dante Alighieri, AVIS, ARCI...)
            $table->string('rete_affiliazione_nome')->nullable();
            $table->string('rete_affiliazione_codice')->nullable();
            $table->string('rete_affiliazione_referente')->nullable();

            // Anno sociale corrente
            $table->smallInteger('anno_sociale_corrente')->default(date('Y'));

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_associazione');
    }
};
