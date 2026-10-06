<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_governance_cd', function (Blueprint $table) {
            // Elimina la ridondanza anagrafica: i membri del CD sono sempre
            // anche soci. Obbligatoria (non nullable): la tabella è oggi
            // vuota (associazione non ancora costituita), nessun dato
            // esistente da conciliare.
            $table->foreignId('socio_id')
                  ->after('id')
                  ->constrained('gl_soci');

            // Collegamento vero al verbale di nomina, stessa convenzione già
            // usata per gl_bilancio.verbale_approvazione_id e
            // gl_volontari.verbale_autorizzazione_id. delibera_assemblea/
            // delibera_data (esistenti) restano come fallback testuale.
            $table->foreignId('verbale_nomina_id')
                  ->nullable()
                  ->after('delibera_data')
                  ->constrained('gl_verbali')
                  ->nullOnDelete();

            // Incompatibilità art. 11 D.Lgs. 36/2021, come modificato dal
            // D.L. 96/2025 (conv. L. 119/2025, in vigore dal 10/08/2025):
            // oggi il divieto di ricoprire cariche in altre ASD/SSD affiliate
            // alla stessa federazione/EPS si applica solo al Presidente. Non
            // è una clausola statutaria obbligatoria, ma consigliabile per
            // evidenza di conformità. Campo tenuto generico per ogni carica
            // (non solo Presidente): la norma è già cambiata due volte in
            // pochi anni (da "tutti gli amministratori" a "solo il
            // presidente"), la logica applicativa decide chi deve
            // dichiararlo in base a 'carica' e alla normativa vigente.
            $table->text('dichiarazione_incompatibilita')->nullable();
            $table->date('dichiarazione_incompatibilita_data')->nullable();

            $table->dropColumn([
                'cognome',
                'nome',
                'data_nascita',
                'luogo_nascita',
                'codice_fiscale',
                'email',
                'telefono',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('gl_governance_cd', function (Blueprint $table) {
            $table->string('cognome')->nullable();
            $table->string('nome')->nullable();
            $table->date('data_nascita')->nullable();
            $table->string('luogo_nascita')->nullable();
            $table->string('codice_fiscale', 16)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();

            $table->dropConstrainedForeignId('verbale_nomina_id');
            $table->dropColumn([
                'dichiarazione_incompatibilita',
                'dichiarazione_incompatibilita_data',
            ]);
            $table->dropConstrainedForeignId('socio_id');
        });
    }
};
