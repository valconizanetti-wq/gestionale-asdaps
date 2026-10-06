<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultazione telematica del CD (statuto v1.0, artt. 6.4 e 16.4): in
 * caso di parità prevale il voto del Presidente, e se il Presidente non ha
 * votato la domanda è accolta d'ufficio. Serve quindi sapere quale voto è
 * del Presidente, fissato al momento dell'apertura della consultazione
 * (le cariche possono cambiare dopo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_voti_cd', function (Blueprint $table) {
            // Collegamento al membro del CD che ha ricevuto il link. Nullable
            // con nullOnDelete: il voto resta anche se la carica viene
            // cancellata. Nome ed email restano come istantanea.
            $table->foreignId('governance_cd_id')
                  ->nullable()
                  ->after('domanda_id')
                  ->constrained('gl_governance_cd')
                  ->nullOnDelete();
            $table->boolean('presidente')->default(false)->after('consigliere_email');
        });
    }

    public function down(): void
    {
        Schema::table('gl_voti_cd', function (Blueprint $table) {
            $table->dropConstrainedForeignId('governance_cd_id');
            $table->dropColumn('presidente');
        });
    }
};
