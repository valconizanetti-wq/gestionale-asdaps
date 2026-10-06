<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domanda di iscrizione online (v0.26.0) — campi richiesti dal modulo CSEN
 * e dallo statuto v1.0 (art. 6), vedi scheda domande-iscrizione.md §2.
 * Tabella oggi vuota: nessun dato da conciliare.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_domande', function (Blueprint $table) {
            // Versioni dei testi accettati, fissate al momento della domanda:
            // l'informativa o lo statuto possono cambiare prima
            // dell'approvazione (nel Gestionale APS la versione veniva
            // scritta su colonne inesistenti e andava persa).
            $table->string('informativa_versione', 10)->nullable()->after('informativa_hash');
            $table->string('statuto_versione', 20)->nullable()->after('informativa_versione');

            // Documento d'identità di chi sottoscrive (richiedente, o genitore
            // se minorenne): sezione "documento" del modulo CSEN. Nessuna
            // copia del documento (decisione di Paolo, 8a).
            $table->string('documento_tipo', 30)->nullable()->after('statuto_versione');
            $table->string('documento_numero', 30)->nullable()->after('documento_tipo');
            $table->string('documento_rilasciato_da', 100)->nullable()->after('documento_numero');
            $table->date('documento_data_rilascio')->nullable()->after('documento_rilasciato_da');

            // Dati del genitore richiesti dal modulo CSEN per i minorenni; il
            // telefono alimenta gl_soci.tutore_telefono (obbligatorio per i
            // minori, soci.md).
            $table->date('genitore_data_nascita')->nullable()->after('genitore_email');
            $table->string('genitore_luogo_nascita')->nullable()->after('genitore_data_nascita');
            $table->string('genitore_telefono', 30)->nullable()->after('genitore_luogo_nascita');

            // Iter dell'art. 6 dello statuto: esito del CD (anche d'ufficio),
            // comunicazione del diniego entro 10 giorni, versamento della
            // quota entro 30 giorni dalla comunicazione dell'accoglimento.
            // Il socio si crea solo al versamento (decisione di Paolo).
            $table->timestamp('esito_il')->nullable()->after('scadenza_cd');
            $table->timestamp('esito_comunicato_il')->nullable()->after('esito_il');
            $table->date('quota_scadenza')->nullable()->after('esito_comunicato_il');
            $table->date('quota_versata_il')->nullable()->after('quota_scadenza');
        });

        // Nuovo stato 'decaduta': domanda accolta ma quota non versata entro
        // 30 giorni (art. 6.6). Distinto da 'scaduta', che resta per il link
        // di conferma email non usato entro 48 ore. MySQL non modifica un
        // ENUM tramite Blueprint: stessa tecnica della migration 000077.
        DB::statement("ALTER TABLE gl_domande MODIFY stato ENUM('in_verifica','in_attesa','approvata','rifiutata','da_rifiutare','in_parita','scaduta','decaduta') NOT NULL DEFAULT 'in_attesa'");
    }

    public function down(): void
    {
        // Senza questo passaggio MySQL rifiuterebbe (o svuoterebbe, a
        // seconda della modalità SQL) le righe con lo stato rimosso.
        DB::table('gl_domande')->where('stato', 'decaduta')->update(['stato' => 'scaduta']);

        DB::statement("ALTER TABLE gl_domande MODIFY stato ENUM('in_verifica','in_attesa','approvata','rifiutata','da_rifiutare','in_parita','scaduta') NOT NULL DEFAULT 'in_attesa'");

        Schema::table('gl_domande', function (Blueprint $table) {
            $table->dropColumn([
                'informativa_versione',
                'statuto_versione',
                'documento_tipo',
                'documento_numero',
                'documento_rilasciato_da',
                'documento_data_rilascio',
                'genitore_data_nascita',
                'genitore_luogo_nascita',
                'genitore_telefono',
                'esito_il',
                'esito_comunicato_il',
                'quota_scadenza',
                'quota_versata_il',
            ]);
        });
    }
};
