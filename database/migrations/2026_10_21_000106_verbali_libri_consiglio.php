<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verbali e Consiglio Direttivo (v0.44.0, analisi-verbali-assemblee.md,
 * specifiche/verbali.md, tavole VB-1…VB-5, CD-5, CT-1…CT-3).
 *
 * - gl_verbali: libro (cd, assemblee) con numero progressivo nell'anno e
 *   vincolo unico (V1); stati bozza, definitivo, firmato (non più
 *   approvato e archiviato); PDF definitivo con impronta; copia a firma
 *   digitale (V2, V14); verbali registrati da fuori (V13); delibera che
 *   approva il rendiconto, per la chiusura dell'anno contabile.
 * - gl_assemblee diventa la seduta (riunione del CD, poi le Assemblee):
 *   videoconferenza, urgenza (V4), annullamento, presidente e segretario.
 * - Nuove: presenze, punti del verbale con la delibera, voti di ciascuno,
 *   rettifiche, consultazioni telematiche con i voti dal link, documenti.
 * - Permessi della sezione Verbali (V12): il CD e il Revisore leggono.
 *
 * Le tabelle nuove sono vuote; i verbali esistenti sono solo quelli di
 * prova delle domande (cd_ammissione, in bozza).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Verbali ──────────────────────────────────────────────────────
        DB::statement("ALTER TABLE gl_verbali MODIFY tipo VARCHAR(20) NOT NULL DEFAULT 'assemblea'");
        DB::statement("ALTER TABLE gl_verbali MODIFY stato VARCHAR(12) NOT NULL DEFAULT 'bozza'");
        DB::statement('ALTER TABLE gl_verbali MODIFY corpo_html LONGTEXT NULL');
        DB::table('gl_verbali')->where('tipo', 'cd_delibera')->update(['tipo' => 'cd_riunione']);
        // Nessuna funzione del gestionale ha mai scritto «approvato» o
        // «archiviato»: se ci fossero righe così, tornano in bozza, per
        // prendere il numero e il PDF con «Rendi definitivo» (un definitivo
        // senza numero né PDF non sarebbe valido).
        DB::table('gl_verbali')->whereIn('stato', ['approvato', 'archiviato'])->update(['stato' => 'bozza']);

        Schema::table('gl_verbali', function (Blueprint $table) {
            $table->string('libro', 10)->default('cd')->after('tipo');
            $table->unsignedSmallInteger('anno_libro')->nullable()->after('libro');
            $table->unsignedInteger('numero_libro')->nullable()->after('anno_libro');
            $table->boolean('da_fuori')->default(false)->after('numero_libro');
            $table->string('organo', 15)->nullable()->after('da_fuori');           // cd, ordinaria, straordinaria
            $table->string('luogo')->nullable()->after('data_seduta');
            $table->unsignedSmallInteger('approva_rendiconto_anno')->nullable()->after('anno_locked');
            $table->string('pdf_impronta', 64)->nullable()->after('pdf_path');
            $table->dateTime('definitivo_il')->nullable()->after('pdf_impronta');
            $table->foreignId('definitivo_da')->nullable()->after('definitivo_il')->constrained('gl_users')->nullOnDelete();
            $table->string('firma_tipo', 8)->nullable()->after('verbale_firmato_path');     // PAdES, CAdES
            $table->unsignedTinyInteger('firme_numero')->nullable()->after('firma_tipo');
            $table->string('firma_dichiarata', 12)->nullable()->after('firme_numero');      // entrambi, segretario
            $table->string('firmatari', 500)->nullable()->after('firma_dichiarata');
            $table->string('firmato_nome')->nullable()->after('firmatari');
            $table->dateTime('firmato_il')->nullable()->after('firmato_nome');
            $table->foreignId('firmato_da')->nullable()->after('firmato_il')->constrained('gl_users')->nullOnDelete();
            $table->unique(['libro', 'anno_libro', 'numero_libro']);
        });
        DB::table('gl_verbali')->where('tipo', 'assemblea')->update(['libro' => 'assemblee']);

        // ── Sedute (gl_assemblee) ────────────────────────────────────────
        DB::statement("ALTER TABLE gl_assemblee MODIFY stato VARCHAR(12) NOT NULL DEFAULT 'bozza'");
        Schema::table('gl_assemblee', function (Blueprint $table) {
            $table->boolean('videoconferenza')->default(false)->after('luogo');
            $table->string('collegamento', 500)->nullable()->after('videoconferenza');
            $table->boolean('urgente')->default(false)->after('ordine_del_giorno');
            $table->string('motivo_urgenza', 500)->nullable()->after('urgente');
            $table->unsignedSmallInteger('convocati')->nullable()->after('convocazione_inviata_il');
            $table->foreignId('presidente_socio_id')->nullable()->after('convocati')->constrained('gl_soci')->nullOnDelete();
            $table->foreignId('segretario_socio_id')->nullable()->after('presidente_socio_id')->constrained('gl_soci')->nullOnDelete();
            $table->dateTime('annullata_il')->nullable()->after('rinvio_note');
            $table->text('motivo_annullamento')->nullable()->after('annullata_il');
        });

        Schema::create('gl_sedute_presenze', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seduta_id')->constrained('gl_assemblee')->cascadeOnDelete();
            $table->foreignId('socio_id')->nullable()->constrained('gl_soci')->nullOnDelete();
            $table->foreignId('governance_cd_id')->nullable()->constrained('gl_governance_cd')->nullOnDelete();
            $table->string('nome');
            $table->string('carica', 100)->nullable();
            $table->boolean('presidente')->default(false);
            $table->string('presenza', 8);                     // sede, video, assente
            $table->boolean('giustificato')->default(false);
            $table->timestamps();
            $table->unique(['seduta_id', 'socio_id']);
        });

        // ── Punti del verbale, delibere e voti ───────────────────────────
        Schema::create('gl_verbali_punti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verbale_id')->constrained('gl_verbali')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordine');
            $table->string('titolo', 500);
            $table->longText('discussione_html')->nullable();
            $table->boolean('ha_delibera')->default(false);
            $table->string('tipo_delibera', 20)->nullable();
            $table->longText('delibera_html')->nullable();
            $table->string('esito', 12)->nullable();           // approvata, respinta
            $table->string('esito_testo', 500)->nullable();
            $table->unsignedSmallInteger('favorevoli')->default(0);
            $table->unsignedSmallInteger('contrari')->default(0);
            $table->unsignedSmallInteger('astenuti')->default(0);
            $table->unsignedSmallInteger('non_partecipano')->default(0);
            $table->timestamps();
        });

        Schema::create('gl_verbali_voti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('punto_id')->constrained('gl_verbali_punti')->cascadeOnDelete();
            $table->foreignId('socio_id')->nullable()->constrained('gl_soci')->nullOnDelete();
            $table->string('nome');
            $table->string('carica', 100)->nullable();
            $table->boolean('presidente')->default(false);
            $table->string('voto', 14)->nullable();            // favorevole, contrario, astenuto, non_partecipa, riunione
            $table->text('nota')->nullable();
            $table->dateTime('votato_il')->nullable();
            $table->string('collegamento', 12)->nullable();
            $table->timestamps();
        });

        Schema::create('gl_verbali_rettifiche', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verbale_id')->constrained('gl_verbali')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->longText('testo_html');
            $table->text('motivo');
            $table->string('pdf_path')->nullable();
            $table->string('pdf_impronta', 64)->nullable();
            $table->string('firmato_path')->nullable();
            $table->string('firmato_nome')->nullable();
            $table->string('firmato_impronta', 64)->nullable();
            $table->string('firma_tipo', 8)->nullable();
            $table->unsignedTinyInteger('firme_numero')->nullable();
            $table->dateTime('firmato_il')->nullable();
            $table->foreignId('creata_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['verbale_id', 'numero']);
        });

        // ── Consultazioni telematiche del CD (art. 16.4) ─────────────────
        Schema::create('gl_consultazioni', function (Blueprint $table) {
            $table->id();
            $table->string('oggetto', 255);
            $table->longText('testo_html');
            $table->dateTime('termine')->nullable();
            $table->string('stato', 12)->default('bozza');    // bozza, in_corso, chiusa, ritirata
            $table->string('esito', 12)->nullable();           // approvata, respinta, non_valida, riunione, ritirata
            $table->string('esito_testo', 500)->nullable();
            $table->unsignedTinyInteger('componenti')->nullable();
            $table->foreignId('preparata_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->dateTime('avviata_il')->nullable();
            $table->foreignId('avviata_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->boolean('in_sostituzione')->default(false);
            $table->dateTime('chiusa_il')->nullable();
            $table->text('motivo_ritiro')->nullable();
            $table->foreignId('verbale_id')->nullable()->constrained('gl_verbali')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('gl_consultazioni_voti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultazione_id')->constrained('gl_consultazioni')->cascadeOnDelete();
            $table->foreignId('governance_cd_id')->nullable()->constrained('gl_governance_cd')->nullOnDelete();
            $table->foreignId('socio_id')->nullable()->constrained('gl_soci')->nullOnDelete();
            $table->string('nome');
            $table->string('carica', 100)->nullable();
            $table->string('email')->nullable();
            $table->boolean('presidente')->default(false);
            $table->string('token_hash', 64)->nullable()->unique();
            $table->text('token_cifrato')->nullable();
            $table->string('voto', 12)->nullable();            // favorevole, contrario, astenuto, riunione
            $table->text('nota')->nullable();
            $table->dateTime('votato_il')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->dateTime('sollecitato_il')->nullable();
            $table->timestamps();
        });

        // ── Documenti di sedute, consultazioni e verbali ─────────────────
        Schema::create('gl_organi_documenti', function (Blueprint $table) {
            $table->id();
            $table->string('allegabile_type', 20);             // seduta, consultazione, verbale
            $table->unsignedBigInteger('allegabile_id');
            $table->string('path');
            $table->string('nome');
            $table->unsignedBigInteger('dimensione')->default(0);
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->foreignId('caricato_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['allegabile_type', 'allegabile_id']);
        });

        // ── Permessi (V12): il CD e il Revisore leggono i verbali ─────────
        $sezione = DB::table('gl_sezioni')->where('codice', 'verbali')->value('id');
        if ($sezione) {
            foreach (['vicepresidente', 'tesoriere', 'consigliere', 'revisore'] as $ruolo) {
                if (DB::table('gl_ruoli')->where('codice', $ruolo)->exists()) {
                    DB::table('gl_ruoli_sezioni')->updateOrInsert(
                        ['ruolo_codice' => $ruolo, 'sezione_id' => $sezione], ['sola_lettura' => true]);
                }
            }
        }
    }

    public function down(): void
    {
        $sezione = DB::table('gl_sezioni')->where('codice', 'verbali')->value('id');
        DB::table('gl_ruoli_sezioni')->where('sezione_id', $sezione)
            ->whereIn('ruolo_codice', ['vicepresidente', 'tesoriere', 'consigliere', 'revisore'])->delete();

        Schema::dropIfExists('gl_organi_documenti');
        Schema::dropIfExists('gl_consultazioni_voti');
        Schema::dropIfExists('gl_consultazioni');
        Schema::dropIfExists('gl_verbali_rettifiche');
        Schema::dropIfExists('gl_verbali_voti');
        Schema::dropIfExists('gl_verbali_punti');
        Schema::dropIfExists('gl_sedute_presenze');

        Schema::table('gl_assemblee', function (Blueprint $table) {
            $table->dropConstrainedForeignId('presidente_socio_id');
            $table->dropConstrainedForeignId('segretario_socio_id');
            $table->dropColumn(['videoconferenza', 'collegamento', 'urgente', 'motivo_urgenza', 'convocati', 'annullata_il', 'motivo_annullamento']);
        });
        DB::table('gl_assemblee')->where('stato', 'annullata')->update(['stato' => 'rinviata']);
        DB::statement("ALTER TABLE gl_assemblee MODIFY stato ENUM('bozza','convocata','svolta','rinviata') NOT NULL DEFAULT 'bozza'");

        Schema::table('gl_verbali', function (Blueprint $table) {
            $table->dropUnique(['libro', 'anno_libro', 'numero_libro']);
            $table->dropConstrainedForeignId('definitivo_da');
            $table->dropConstrainedForeignId('firmato_da');
            $table->dropColumn(['libro', 'anno_libro', 'numero_libro', 'da_fuori', 'organo', 'luogo', 'approva_rendiconto_anno',
                'pdf_impronta', 'definitivo_il', 'firma_tipo', 'firme_numero', 'firma_dichiarata', 'firmatari', 'firmato_nome', 'firmato_il']);
        });
        DB::table('gl_verbali')->whereIn('tipo', ['cd_riunione', 'cd_consultazione'])->update(['tipo' => 'cd_delibera']);
        DB::table('gl_verbali')->where('stato', 'definitivo')->update(['stato' => 'approvato']);
        DB::table('gl_verbali')->where('stato', 'firmato')->update(['stato' => 'archiviato']);
        DB::table('gl_verbali')->whereNull('corpo_html')->update(['corpo_html' => '']);
        DB::statement('ALTER TABLE gl_verbali MODIFY corpo_html LONGTEXT NOT NULL');
        DB::statement("ALTER TABLE gl_verbali MODIFY stato ENUM('bozza','approvato','archiviato') NOT NULL DEFAULT 'bozza'");
        DB::statement("ALTER TABLE gl_verbali MODIFY tipo ENUM('assemblea','cd_ammissione','cd_delibera') NOT NULL DEFAULT 'assemblea'");
    }
};
