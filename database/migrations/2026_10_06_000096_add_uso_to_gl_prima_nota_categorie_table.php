<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.35.0 (06/10/2026), prima-nota.md §2: uso della categoria e natura da
 * verificare.
 *
 * uso:
 * - movimenti: categoria ordinaria, si sceglie nei movimenti;
 * - solo_bilancio: "Disavanzo anno precedente", "Attivo anno precedente":
 *   voci di preventivo e rendiconto. In Prima Nota il riporto lo fanno i
 *   saldi di apertura, usarle nei movimenti conterebbe due volte (deciso
 *   da Paolo, 06/10/2026);
 * - sistema: "Uscite da classificare", assegnata in automatico agli
 *   scontrini registrati dal telefono, mai scelta a mano.
 *
 * natura_da_verificare: entrate che dal 2028 (regime 398) potrebbero
 * essere commerciali; da confermare con il commercialista prima di
 * valorizzare flag_non_istituzionale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_prima_nota_categorie', function (Blueprint $table) {
            $table->string('uso', 20)->default('movimenti')->after('codice_dm39');
            $table->boolean('natura_da_verificare')->default(false)->after('flag_non_istituzionale');
        });
    }

    public function down(): void
    {
        Schema::table('gl_prima_nota_categorie', function (Blueprint $table) {
            $table->dropColumn(['uso', 'natura_da_verificare']);
        });
    }
};
