<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_rimborsi_volontari', function (Blueprint $table) {
            // Distinzione ordinario (≤150€/mese, spese documentate: le voci
            // già coperte da gl_rimborsi_volontari_voci) vs forfettario
            // (≤400€/mese, legato a un evento/manifestazione riconosciuti).
            $table->enum('tipo_rimborso', ['ordinario', 'forfettario'])
                  ->default('ordinario')
                  ->after('volontario_id');

            // Placeholder testuale in attesa del modulo Gestione gare (stessa
            // convenzione già usata nel core per progetto_id su gl_prima_nota
            // prima che esistesse il modulo Progetti — vedi MANUALE-CORE.md §3).
            // Promemoria: sostituire con FK evento_id -> gl_gare quando
            // costruito, con migration di follow-up.
            $table->string('evento_nome')->nullable()->after('tipo_rimborso');
            $table->date('evento_data')->nullable()->after('evento_nome');

            $table->foreignId('rasd_invio_id')
                  ->nullable()
                  ->after('prima_nota_id')
                  ->constrained('gl_rimborsi_rasd_invii')
                  ->nullOnDelete();

            // Recupero del gap gs_rimborsi_annullati (MANUALE-CORE.md §6):
            // qui gestito come stato sul record principale invece che con una
            // tabella di log separata, dato che gl_rimborsi_volontari non ha
            // soft delete e il record resta comunque nello storico.
            $table->date('data_annullamento')->nullable()->after('data_pagamento');
            $table->string('motivo_annullamento', 500)->nullable()->after('data_annullamento');
        });

        // Aggiunta del valore 'annullato' all'enum esistente. MySQL non
        // permette di modificare un enum tramite Blueprint: serve un ALTER
        // diretto. Il valore di default resta 'bozza', invariato.
        DB::statement("ALTER TABLE gl_rimborsi_volontari MODIFY stato ENUM('bozza','in_attesa','pagato','annullato') DEFAULT 'bozza'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE gl_rimborsi_volontari MODIFY stato ENUM('bozza','in_attesa','pagato') DEFAULT 'bozza'");

        Schema::table('gl_rimborsi_volontari', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rasd_invio_id');
            $table->dropColumn([
                'tipo_rimborso',
                'evento_nome',
                'evento_data',
                'data_annullamento',
                'motivo_annullamento',
            ]);
        });
    }
};
