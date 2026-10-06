<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Allinea l'enum tipo_socio a gl_soci.tipo (ordinario/giovane/
        // minorenne), rimuovendo due valori non applicabili a questa ASD:
        // - 'ordinario_rivista': specifico della Società Dante Alighieri
        //   (abbonamento alla rivista sociale), mai generalizzato nel core;
        // - 'vitalizio': valutato e scartato esplicitamente per questa ASD.
        // Senza questo allineamento, una domanda approvata con uno di questi
        // due valori non avrebbe un corrispondente in gl_soci.tipo al momento
        // della trasformazione in socio. Tabella oggi vuota (associazione
        // non ancora costituita), nessun dato esistente da conciliare.
        DB::statement("ALTER TABLE gl_domande MODIFY tipo_socio ENUM('ordinario','giovane','minorenne') DEFAULT 'ordinario'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE gl_domande MODIFY tipo_socio ENUM('ordinario','ordinario_rivista','giovane','minorenne','vitalizio') DEFAULT 'ordinario'");
    }
};
