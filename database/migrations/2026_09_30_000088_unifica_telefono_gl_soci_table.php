<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un solo campo telefono per il socio (Paolo, 30/09/2026): il cellulare,
 * se c'è, diventa il telefono; poi la colonna cellulare si toglie.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('gl_soci')
            ->whereNotNull('cellulare')->where('cellulare', '!=', '')
            ->update(['telefono' => DB::raw('cellulare')]);

        Schema::table('gl_soci', function (Blueprint $table) {
            $table->dropColumn('cellulare');
        });
    }

    public function down(): void
    {
        Schema::table('gl_soci', function (Blueprint $table) {
            $table->string('cellulare')->nullable()->after('telefono');
        });
    }
};
