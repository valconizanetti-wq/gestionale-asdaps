<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo Voto CD (v0.30.0, domande-iscrizione.md §2).
 *
 * gl_domande:
 * - chiusura della votazione (quando e perché: scadenza, tutti hanno
 *   votato, Presidente);
 * - promemoria già inviati (sollecito ai consiglieri, promemoria del
 *   diniego al Presidente), così il comando orario non li ripete;
 * - decisione del Presidente in caso di parità senza il suo voto;
 * - quota fissata all'accoglimento (importo e anno sociale, promozione
 *   di ottobre–dicembre compresa): la quota del CD può cambiare dopo, ma
 *   l'email di accoglimento ha già detto quanto versare.
 *
 * gl_voti_cd:
 * - consigliere_email nullable: un consigliere senza email riceve la sua
 *   riga e conta nel quorum come non votante (decisione di Paolo);
 * - link_inviato_il: quando è partito il link (per "Invia di nuovo");
 * - token_cifrato: il token del link cifrato con la chiave dell'app
 *   (APP_KEY), per rimandare lo stesso link nel sollecito e con "Invia di
 *   nuovo" senza invalidare quello già ricevuto. La ricerca continua a
 *   usare solo l'impronta (token_link): chi ha il database ma non il .env
 *   non può ricostruire i link.
 *
 * Dati: impostazione della promozione e categoria di Prima Nota delle
 * quote, inserite solo se mancano (il deploy non esegue i seeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_domande', function (Blueprint $table) {
            $table->timestamp('votazione_chiusa_il')->nullable()->after('scadenza_cd');
            $table->string('votazione_chiusura', 12)->nullable()->after('votazione_chiusa_il');
            $table->timestamp('sollecito_inviato_il')->nullable()->after('votazione_chiusura');
            $table->timestamp('promemoria_diniego_il')->nullable()->after('esito_comunicato_il');
            $table->timestamp('decisione_presidente_il')->nullable()->after('promemoria_diniego_il');
            $table->decimal('quota_importo', 8, 2)->nullable()->after('quota_scadenza');
            $table->unsignedSmallInteger('quota_anno')->nullable()->after('quota_importo');
            $table->boolean('quota_promo')->default(false)->after('quota_anno');
        });

        Schema::table('gl_voti_cd', function (Blueprint $table) {
            $table->string('consigliere_email')->nullable()->change();
            $table->timestamp('link_inviato_il')->nullable()->after('presidente');
            $table->text('token_cifrato')->nullable()->after('token_link');
        });

        if (DB::table('gl_impostazioni')->where('chiave', 'quota_promo_ultimo_trimestre')->doesntExist()) {
            DB::table('gl_impostazioni')->insert([
                'chiave' => 'quota_promo_ultimo_trimestre',
                'valore' => '1',
                'tipo' => 'boolean',
                'descrizione' => "Se attiva, chi è accolto da ottobre a dicembre versa la quota dell'anno successivo: diventa socio subito e la quota vale fino al 31 dicembre dell'anno dopo. Si può cambiare domanda per domanda.",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('gl_prima_nota_categorie')->where('codice', 'QUOTE')->doesntExist()) {
            DB::table('gl_prima_nota_categorie')->insert([
                'tipo' => 'entrata',
                'nome' => 'Quote associative',
                'codice' => 'QUOTE',
                'codice_dm39' => 'A1',
                'flag_ente_pubblico' => false,
                'flag_non_istituzionale' => false,
                'esempio' => 'Quote annuali dei soci, anche dei nuovi soci iscritti da domanda',
                'attiva' => true,
                'ordine' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('gl_domande', function (Blueprint $table) {
            $table->dropColumn([
                'votazione_chiusa_il', 'votazione_chiusura', 'sollecito_inviato_il',
                'promemoria_diniego_il', 'decisione_presidente_il',
                'quota_importo', 'quota_anno', 'quota_promo',
            ]);
        });

        Schema::table('gl_voti_cd', function (Blueprint $table) {
            $table->dropColumn(['link_inviato_il', 'token_cifrato']);
        });
        // consigliere_email resta nullable: tornare a NOT NULL fallirebbe
        // con le righe dei consiglieri senza email.
        // Impostazione e categoria restano: possono essere già in uso.
    }
};
