<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v0.33.0 (05/10/2026), privacy-link.md §2:
 * - consenso a foto e video nella scheda del socio (stato attuale, data
 *   dell'ultima scelta, origine). Prima restava solo nella domanda online;
 * - privacy_metodo con il valore 'link' (conferma dal link personale);
 * - socio_id in gl_email_log, per le email del link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_soci', function (Blueprint $table) {
            $table->boolean('foto_video_consenso')->default(false)->after('privacy_doc_path');
            $table->dateTime('foto_video_il')->nullable()->after('foto_video_consenso');
            $table->enum('foto_video_origine', ['domanda', 'link', 'revoca_email', 'revoca_persona', 'revoca_lettera'])
                ->nullable()->after('foto_video_il');
        });

        DB::statement("ALTER TABLE gl_soci MODIFY privacy_metodo ENUM('online','presenza','cartaceo','link') NULL");

        Schema::table('gl_email_log', function (Blueprint $table) {
            $table->foreignId('socio_id')->nullable()->after('domanda_id')->constrained('gl_soci')->nullOnDelete();
        });

        // Soci già iscritti da una domanda online: il consenso a foto e video
        // dato con la domanda passa nella scheda (prima non veniva copiato).
        DB::statement("
            UPDATE gl_soci s
            JOIN gl_domande d ON d.socio_id = s.id
            SET s.foto_video_consenso = 1, s.foto_video_il = d.created_at, s.foto_video_origine = 'domanda'
            WHERE d.consenso_foto_video = 1 AND s.foto_video_il IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('gl_email_log', function (Blueprint $table) {
            $table->dropConstrainedForeignId('socio_id');
        });

        DB::statement("UPDATE gl_soci SET privacy_metodo = 'online' WHERE privacy_metodo = 'link'");
        DB::statement("ALTER TABLE gl_soci MODIFY privacy_metodo ENUM('online','presenza','cartaceo') NULL");

        Schema::table('gl_soci', function (Blueprint $table) {
            $table->dropColumn(['foto_video_consenso', 'foto_video_il', 'foto_video_origine']);
        });
    }
};
