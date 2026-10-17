<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v0.42.0 — Consiglio Direttivo e utenti (analisi-collaudo-O.md §10–§15,
 * decisioni di Paolo del 16–17/10/2026).
 *
 * - Ruoli nuovi: Vicepresidente (statuto art. 15.2) e Amministratore di
 *   sistema; ordine dei ruoli rivisto.
 * - gl_users: cognome e nome separati (O2.1; `name` resta come «Nome
 *   Cognome» per email, Audit Log e testi), socio_id (utenti del CD).
 *   I nomi presenti si dividono alla prima parola.
 * - gl_governance_cd: carica_codice (la carica come codice: i ruoli degli
 *   utenti ne discendono) e motivo_fine. `carica` resta come etichetta,
 *   letta dal voto del CD e dal nome del Tesoriere sulle ricevute.
 * - Permessi delle sezioni per i ruoli nuovi e per la pagina del CD. Gli
 *   stessi sono nel SezioneSeeder: qui per non dover rilanciare il seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ruoli
        $ordine = ['presidente' => 1, 'vicepresidente' => 2, 'segretario' => 3, 'tesoriere' => 4,
            'consigliere' => 5, 'revisore' => 6, 'amministratore' => 7];
        DB::table('gl_ruoli')->updateOrInsert(['codice' => 'vicepresidente'],
            ['label' => 'Vicepresidente', 'colore_avatar' => '#5b6abf', 'badge_class' => 'bg-indigo', 'ordine' => 2]);
        DB::table('gl_ruoli')->updateOrInsert(['codice' => 'amministratore'],
            ['label' => 'Amministratore', 'colore_avatar' => '#5d6d7e', 'badge_class' => 'bg-grey', 'ordine' => 7]);
        foreach ($ordine as $codice => $n) {
            DB::table('gl_ruoli')->where('codice', $codice)->update(['ordine' => $n]);
        }

        // Utenti: cognome, nome, socio
        Schema::table('gl_users', function (Blueprint $table) {
            $table->string('nome', 100)->nullable()->after('name');
            $table->string('cognome', 100)->nullable()->after('nome');
            $table->foreignId('socio_id')->nullable()->after('id')->unique()->constrained('gl_soci')->nullOnDelete();
        });
        foreach (DB::table('gl_users')->select('id', 'name')->get() as $u) {
            $parti = preg_split('/\s+/u', trim((string) $u->name), 2);
            DB::table('gl_users')->where('id', $u->id)->update(['nome' => $parti[0] ?? '', 'cognome' => $parti[1] ?? '']);
        }

        // Consiglio Direttivo: carica come codice
        Schema::table('gl_governance_cd', function (Blueprint $table) {
            $table->string('carica_codice', 30)->nullable()->after('carica');
            $table->string('motivo_fine', 30)->nullable()->after('data_fine_mandato');
        });
        foreach (DB::table('gl_governance_cd')->select('id', 'carica')->get() as $m) {
            $c = mb_strtolower((string) $m->carica);
            $codice = match (true) {
                str_contains($c, 'vice') => 'vicepresidente',
                str_contains($c, 'presidente') => 'presidente',
                str_contains($c, 'segretari') && str_contains($c, 'tesorier') => 'segretario_tesoriere',
                str_contains($c, 'segretari') => 'segretario',
                str_contains($c, 'tesorier') => 'tesoriere',
                default => 'consigliere',
            };
            DB::table('gl_governance_cd')->where('id', $m->id)->update(['carica_codice' => $codice]);
        }

        // Utenti già presenti collegati ai soci del CD in carica con la
        // stessa email (sul server: il CD di prova inserito con SQL). I ruoli
        // non si toccano: li allinea la pagina del CD alla prima modifica.
        $membri = DB::table('gl_governance_cd')->join('gl_soci', 'gl_soci.id', '=', 'gl_governance_cd.socio_id')
            ->where('gl_governance_cd.in_carica', true)->whereNotNull('gl_soci.email')
            ->select('gl_soci.id', 'gl_soci.email')->get();
        foreach ($membri as $m) {
            $utenti = DB::table('gl_users')->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($m->email))])->whereNull('socio_id')->pluck('id');
            if ($utenti->count() === 1 && ! DB::table('gl_users')->where('socio_id', $m->id)->exists()) {
                DB::table('gl_users')->where('id', $utenti->first())->update(['socio_id' => $m->id]);
            }
        }

        // Permessi
        if (DB::table('gl_sezioni')->exists()) {
            $sezioni = DB::table('gl_sezioni')->pluck('id', 'codice');
            $metti = function (string $ruolo, string $sezione, bool $lettura) use ($sezioni) {
                if (isset($sezioni[$sezione])) {
                    DB::table('gl_ruoli_sezioni')->updateOrInsert(
                        ['ruolo_codice' => $ruolo, 'sezione_id' => $sezioni[$sezione]], ['sola_lettura' => $lettura]);
                }
            };
            // Amministratore: legge tutto, scrive solo Utenti (S4)
            foreach ($sezioni->keys() as $codice) {
                $metti('amministratore', $codice, $codice !== 'utenti');
            }
            // Vicepresidente: come il Consigliere, più le sezioni del Presidente in lettura (§11)
            $metti('vicepresidente', 'scontrini', false);
            foreach (['prima_nota', 'utenti', 'soci', 'domande', 'rinnovi_quota', 'tesseramento', 'scadenzario',
                'promemoria', 'impostazioni', 'audit_log', 'governance_cd'] as $s) {
                $metti('vicepresidente', $s, true);
            }
            // Pagina del CD in lettura per gli altri del CD e per il Revisore
            foreach (['tesoriere', 'consigliere', 'revisore'] as $r) {
                $metti($r, 'governance_cd', true);
            }
        }
    }

    public function down(): void
    {
        Schema::table('gl_governance_cd', function (Blueprint $table) {
            $table->dropColumn(['carica_codice', 'motivo_fine']);
        });
        Schema::table('gl_users', function (Blueprint $table) {
            $table->dropForeign(['socio_id']);
            $table->dropUnique(['socio_id']);
            $table->dropColumn(['socio_id', 'nome', 'cognome']);
        });
        DB::table('gl_users_ruoli')->whereIn('ruolo_codice', ['vicepresidente', 'amministratore'])->delete();
        DB::table('gl_ruoli_sezioni')->whereIn('ruolo_codice', ['vicepresidente', 'amministratore'])->delete();
        DB::table('gl_ruoli')->whereIn('codice', ['vicepresidente', 'amministratore'])->delete();
        foreach (['presidente' => 1, 'segretario' => 2, 'tesoriere' => 3, 'revisore' => 4, 'consigliere' => 5] as $c => $n) {
            DB::table('gl_ruoli')->where('codice', $c)->update(['ordine' => $n]);
        }
    }
};
