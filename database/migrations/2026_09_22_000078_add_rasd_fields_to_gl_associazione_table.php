<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_associazione', function (Blueprint $table) {
            // Campi RASD (Registro Nazionale Attività Sportive Dilettantistiche),
            // l'unico registro rilevante per un'ASD pura non iscritta al RUNTS
            // (vedi MANUALE-ASD.md §2.1). I campi runts_* esistenti restano
            // invariati ma non applicabili a questa ASD.
            $table->string('rasd_numero')->nullable()->after('runts_sezione');
            $table->date('rasd_data_iscrizione')->nullable()->after('rasd_numero');

            // Scadenza affiliazione (CSEN): segnalata più volte come scadenza
            // critica, senza affiliazione valida l'iscrizione RASD decade
            // (§2.1). I campi rete_affiliazione_nome/_codice/_referente
            // esistenti si riusano invariati per CSEN (già generalizzati per
            // qualunque rete/federazione); mancava solo la data di scadenza.
            $table->date('rete_affiliazione_scadenza')->nullable()->after('rete_affiliazione_referente');
        });

        // Il default 'Associazione di Promozione Sociale' è un residuo diretto
        // del Dante, mai generalizzato: sbagliato per un'ASD. Difesa in
        // profondità nel caso la riga singleton venga ricreata in futuro
        // senza specificare esplicitamente forma_giuridica.
        DB::statement("ALTER TABLE gl_associazione ALTER forma_giuridica SET DEFAULT 'Associazione Sportiva Dilettantistica'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE gl_associazione ALTER forma_giuridica SET DEFAULT 'Associazione di Promozione Sociale'");

        Schema::table('gl_associazione', function (Blueprint $table) {
            $table->dropColumn([
                'rasd_numero',
                'rasd_data_iscrizione',
                'rete_affiliazione_scadenza',
            ]);
        });
    }
};
