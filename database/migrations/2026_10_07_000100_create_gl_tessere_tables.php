<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tessere CSEN (analisi-tessere-scadenze.md, tavole TS-1…TS-5, v0.37.0).
 *
 * - gl_tessere_tipi: listino dell'EPS (Silver Base 4 €, …). Prezzo vuoto
 *   per un tipo arrivato dal carico del CSV e non ancora prezzato.
 * - gl_tessere_pacchetti: tessere comprate a pacchetti, con intervallo di
 *   numeri, prezzo e uscita in Prima Nota. I numeri del CSEN sono un
 *   prefisso di stagione + un progressivo («26B2749167»): il pacchetto tiene
 *   prefisso e progressivi dal… al…, con il numero di cifre per riscriverli.
 * - gl_tesseramenti: ogni riga è una tessera emessa (o caricata da un
 *   elenco del CSEN), valida un anno dall'emissione. Tolto il vincolo
 *   unico (socio, stagione): un socio rinnova a 12 mesi, non per stagione.
 *   Numero unico.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Prima di toccare qualcosa: il numero diventa unico. MySQL non
        // annulla le modifiche di struttura, quindi un doppione scoperto a
        // metà lascerebbe la migration a metà (revisione v0.37.0).
        $doppi = DB::table('gl_tesseramenti')
            ->selectRaw("UPPER(TRIM(numero_tessera_csen)) AS n, COUNT(*) AS c")
            ->groupBy('n')->havingRaw('COUNT(*) > 1 OR n = ?', [''])->pluck('c', 'n');
        if ($doppi->isNotEmpty()) {
            throw new \RuntimeException('gl_tesseramenti: numeri di tessera vuoti o ripetuti, da correggere prima della migration: '
                . $doppi->map(fn ($c, $n) => ($n === '' ? '(vuoto)' : $n) . " ×{$c}")->implode(', '));
        }

        Schema::create('gl_tessere_tipi', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 50)->unique();
            $table->decimal('prezzo', 8, 2)->nullable();
            $table->boolean('attivo')->default(true);
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });

        Schema::create('gl_tessere_pacchetti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_id')->constrained('gl_tessere_tipi')->restrictOnDelete();
            $table->date('acquistato_il');
            $table->string('prefisso', 10)->default('');
            $table->unsignedInteger('dal');
            $table->unsignedInteger('al');
            $table->unsignedTinyInteger('cifre')->default(0);
            $table->decimal('prezzo_unitario', 8, 2);
            $table->decimal('totale', 10, 2);
            $table->foreignId('prima_nota_id')->nullable()->constrained('gl_prima_nota')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('inserito_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamps();
            $table->index(['dal', 'al']);
        });

        Schema::table('gl_tesseramenti', function (Blueprint $table) {
            // L'indice unico (socio, stagione) serviva anche alla FK sul socio:
            // prima un indice semplice, poi si toglie quello unico.
            $table->index('socio_id', 'gl_tesseramenti_socio_index');
        });

        Schema::table('gl_tesseramenti', function (Blueprint $table) {
            $table->dropUnique(['socio_id', 'stagione_sportiva']);
            $table->foreignId('tipo_id')->nullable()->after('pacchetto_tesseramento')->constrained('gl_tessere_tipi')->nullOnDelete();
            $table->foreignId('pacchetto_id')->nullable()->after('tipo_id')->constrained('gl_tessere_pacchetti')->nullOnDelete();
            $table->unsignedInteger('progressivo')->nullable()->after('numero_tessera_csen');
            $table->date('scadenza')->nullable()->after('data_rilascio');
            $table->foreignId('precedente_id')->nullable()->after('scadenza')->constrained('gl_tesseramenti')->nullOnDelete();
            $table->string('origine', 10)->default('emessa')->after('precedente_id');   // emessa | caricata
            $table->string('discipline', 100)->nullable()->after('origine');
            $table->timestamp('portale_csen_il')->nullable()->after('discipline');
            $table->boolean('senza_quota')->default(false)->after('portale_csen_il');
            $table->unique('numero_tessera_csen', 'gl_tesseramenti_numero_unique');
            $table->index('scadenza');
        });

        // Righe già presenti (inserite a mano prima della v0.37.0): scadenza
        // un anno dopo il rilascio meno un giorno (come Tesseramento::scadenzaDa;
        // MySQL porta il 29/02 al 28/02), progressivo dal numero, origine
        // «caricata» (nessun pacchetto), già sul portale CSEN (venivano da lì),
        // tipo dal campo libero pacchetto_tesseramento.
        DB::statement('UPDATE gl_tesseramenti SET scadenza = DATE_SUB(DATE_ADD(data_rilascio, INTERVAL 1 YEAR), INTERVAL 1 DAY) WHERE scadenza IS NULL');
        DB::table('gl_tesseramenti')->update(['origine' => 'caricata', 'portale_csen_il' => DB::raw('data_rilascio')]);
        foreach (DB::table('gl_tesseramenti')->get(['id', 'numero_tessera_csen', 'pacchetto_tesseramento']) as $t) {
            $agg = [];
            if (preg_match('/(\d{1,9})$/', trim((string) $t->numero_tessera_csen), $m)) {
                $agg['progressivo'] = (int) $m[1];
            }
            $nome = trim(preg_replace('/\s+/', ' ', (string) $t->pacchetto_tesseramento));
            if ($nome !== '') {
                $nome = mb_substr(mb_convert_case(mb_strtolower($nome), MB_CASE_TITLE), 0, 50);
                $agg['tipo_id'] = DB::table('gl_tessere_tipi')->where('nome', $nome)->value('id')
                    ?? DB::table('gl_tessere_tipi')->insertGetId(['nome' => $nome, 'prezzo' => null, 'attivo' => true, 'ordine' => 0, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($agg) {
                DB::table('gl_tesseramenti')->where('id', $t->id)->update($agg);
            }
        }
    }

    public function down(): void
    {
        Schema::table('gl_tesseramenti', function (Blueprint $table) {
            $table->dropUnique('gl_tesseramenti_numero_unique');
            $table->dropIndex(['scadenza']);
            $table->dropConstrainedForeignId('tipo_id');
            $table->dropConstrainedForeignId('pacchetto_id');
            $table->dropConstrainedForeignId('precedente_id');
            $table->dropColumn(['progressivo', 'scadenza', 'origine', 'discipline', 'portale_csen_il', 'senza_quota']);
            $table->unique(['socio_id', 'stagione_sportiva']);
        });
        Schema::table('gl_tesseramenti', function (Blueprint $table) {
            $table->dropIndex('gl_tesseramenti_socio_index');
        });
        Schema::dropIfExists('gl_tessere_pacchetti');
        Schema::dropIfExists('gl_tessere_tipi');
    }
};
