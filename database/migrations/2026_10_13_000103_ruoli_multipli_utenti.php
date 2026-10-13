<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Più ruoli per utente (v0.40.0, analisi-ruoli-utenti.md §3.1):
 * - tabella ponte gl_users_ruoli (utente ↔ codice del ruolo), con il ruolo
 *   di ogni utente copiato dalla vecchia colonna;
 * - tolta gl_users.ruolo (decisione D1: una sola fonte dei ruoli);
 * - sezione 'utenti' (pagina Utenti: modificano Presidente e Segretario,
 *   leggono Tesoriere e Consigliere; il Revisore no). I permessi sono anche
 *   nel SezioneSeeder, qui per non dover rilanciare il seeder sul server.
 * La tabella gl_password_reset_tokens per il link «Scegli la password» (D2)
 * c'è già (0001_01_01_000000): cambia solo config/auth.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_users_ruoli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('gl_users')->cascadeOnDelete();
            $table->string('ruolo_codice', 50);
            $table->foreign('ruolo_codice')->references('codice')->on('gl_ruoli');
            $table->unique(['user_id', 'ruolo_codice']);
        });

        foreach (DB::table('gl_users')->select('id', 'ruolo')->get() as $u) {
            if ($u->ruolo) {
                DB::table('gl_users_ruoli')->insert(['user_id' => $u->id, 'ruolo_codice' => $u->ruolo]);
            }
        }

        Schema::table('gl_users', function (Blueprint $table) {
            $table->dropForeign(['ruolo']);
            $table->dropColumn('ruolo');
        });

        // Sezione Utenti e permessi (se le tabelle dei permessi ci sono già)
        if (DB::table('gl_sezioni')->exists()) {
            $id = DB::table('gl_sezioni')->where('codice', 'utenti')->value('id')
                ?? DB::table('gl_sezioni')->insertGetId(['codice' => 'utenti', 'label' => 'Utenti', 'ordine' => 175]);
            $ruoli = DB::table('gl_ruoli')->pluck('codice')->all();
            foreach (['presidente' => false, 'segretario' => false, 'tesoriere' => true, 'consigliere' => true] as $ruolo => $lettura) {
                if (in_array($ruolo, $ruoli, true)) {
                    DB::table('gl_ruoli_sezioni')->updateOrInsert(
                        ['ruolo_codice' => $ruolo, 'sezione_id' => $id],
                        ['sola_lettura' => $lettura]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('gl_users', function (Blueprint $table) {
            $table->string('ruolo', 50)->nullable()->after('password');
        });

        // Il primo ruolo nell'ordine di gl_ruoli
        foreach (DB::table('gl_users')->pluck('id') as $id) {
            $codice = DB::table('gl_users_ruoli')
                ->join('gl_ruoli', 'gl_ruoli.codice', '=', 'gl_users_ruoli.ruolo_codice')
                ->where('user_id', $id)->orderBy('gl_ruoli.ordine')->value('ruolo_codice');
            DB::table('gl_users')->where('id', $id)->update(['ruolo' => $codice ?? 'segretario']);
        }

        Schema::table('gl_users', function (Blueprint $table) {
            $table->string('ruolo', 50)->nullable(false)->change();
            $table->foreign('ruolo')->references('codice')->on('gl_ruoli');
        });

        $id = DB::table('gl_sezioni')->where('codice', 'utenti')->value('id');
        if ($id) {
            DB::table('gl_ruoli_sezioni')->where('sezione_id', $id)->delete();
            DB::table('gl_sezioni')->where('id', $id)->delete();
        }

        Schema::dropIfExists('gl_users_ruoli');
    }
};
