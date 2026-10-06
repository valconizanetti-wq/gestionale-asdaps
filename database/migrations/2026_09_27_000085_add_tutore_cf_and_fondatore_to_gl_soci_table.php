<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_soci', function (Blueprint $table) {
            // Codice fiscale del genitore: richiesto da CSEN per il
            // tesseramento del minore e raccolto dalla domanda online
            // (gl_domande.genitore_codice_fiscale), ma gl_soci non aveva
            // dove tenerlo.
            $table->string('tutore_codice_fiscale', 16)->nullable()->after('tutore_nome');

            // Qualifica di socio fondatore (statuto v1.0, art. 5.2): non è
            // una categoria di quota, quindi non va in 'tipo'. Non comporta
            // diritti diversi (art. 5.3).
            $table->boolean('fondatore')->default(false)->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('gl_soci', function (Blueprint $table) {
            $table->dropColumn(['tutore_codice_fiscale', 'fondatore']);
        });
    }
};
