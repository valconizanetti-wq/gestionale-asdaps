<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.32.0 (05/10/2026): profilo dell'ente per periodi (profilo-ente.md).
 * Ogni riga è un periodo dell'ente con i dati che dipendono dalla forma
 * giuridica: denominazione, forma, sede legale, RASD, regime fiscale, ente
 * di affiliazione, versioni di statuto e informativa in vigore. Un periodo
 * vale dal giorno `dal` fino al giorno prima del successivo. I dati che non
 * cambiano con la forma (codice fiscale, recapiti, banca) restano in
 * gl_associazione.
 *
 * La tabella nasce vuota: senza periodi il gestionale usa i valori di
 * prima (config/sito.php e impostazioni). I periodi della Compagnia si
 * inseriscono con ProfiloEnteSeeder o dalla pagina Impostazioni › Ente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_associazione_periodi', function (Blueprint $table) {
            $table->id();
            $table->date('dal')->unique();
            $table->string('denominazione');
            $table->string('sigla', 20)->nullable();
            $table->enum('forma_giuridica', ['associazione', 'asd', 'ssd', 'aps']);
            $table->string('forma_descrizione', 100)->nullable();
            $table->string('sede_legale');
            $table->string('rasd_numero', 50)->nullable();
            $table->date('rasd_data_iscrizione')->nullable();
            $table->enum('regime_fiscale', ['non_commerciale', 'l398', 'ordinario']);
            $table->string('ente_affiliazione', 100)->nullable();
            $table->string('statuto_versione', 10);
            $table->string('informativa_versione', 10);
            $table->string('atto')->nullable();
            // Voci della lista di controllo del passaggio da spuntare a mano
            // (affiliazione rinnovata, conto bancario, sito web): {codice: data}.
            $table->json('spunte')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_associazione_periodi');
    }
};
