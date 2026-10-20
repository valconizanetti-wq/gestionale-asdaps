<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anticipi e note di rimborso (v0.43.0, analisi-moduli-anticipi.md parte 2,
 * tavole AN-1…AN-4b; specifiche/anticipi.md).
 *
 * - gl_note_rimborso: una nota raccoglie una o più spese anticipate dallo
 *   stesso socio. Numero RIMB-ANNO-NNN con vincolo unico: una nota
 *   annullata resta una riga «annullata» e il suo numero non torna (difetto
 *   1 del Dante). Tipo «rinuncia» per la rinuncia al rimborso (A6), nella
 *   stessa serie. `dati` è la fotografia della nota al momento della
 *   generazione (socio, carica, visto, righe): il PDF si rifà sempre uguale.
 * - gl_prima_nota: al posto delle colonne del Dante (testo libero, numero
 *   e IBAN su ogni spesa, mai usate da una pagina) il socio che ha
 *   anticipato, la nota e la data della spesa (A1: il movimento prende la
 *   data del rimborso, la data della spesa resta qui).
 * - gl_prima_nota_scontrini: il socio di «Pagato da me» (AN-1b).
 * - Codice DONAZIONI su «Contributi e donazioni da terzi» (E6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_note_rimborso', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->unsignedSmallInteger('anno');
            $table->unsignedInteger('progressivo');
            $table->string('tipo', 10)->default('rimborso');          // rimborso | rinuncia
            $table->foreignId('socio_id')->constrained('gl_soci');
            $table->date('data_nota');
            $table->string('modalita', 10)->nullable();               // cassa | banca (E3); rinuncia: null
            $table->string('iban', 34)->nullable();
            $table->text('note')->nullable();
            $table->decimal('totale', 10, 2);
            $table->unsignedSmallInteger('numero_spese');
            $table->json('dati');
            $table->string('stato', 12)->default('emessa');           // emessa | firmata | pagata | annullata
            $table->string('pdf_path')->nullable();
            $table->string('firmata_path')->nullable();
            $table->string('firmata_nome')->nullable();
            $table->dateTime('firmata_il')->nullable();
            $table->foreignId('firmata_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->date('pagata_il')->nullable();
            $table->string('conto', 10)->nullable();
            $table->dateTime('pagamento_registrato_il')->nullable();
            $table->foreignId('pagata_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->dateTime('annullata_il')->nullable();
            $table->foreignId('annullata_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->text('motivo_annullamento')->nullable();
            $table->foreignId('preparata_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['anno', 'progressivo']);
            $table->index(['socio_id', 'stato']);
        });

        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->foreignId('anticipante_socio_id')->nullable()->after('sospeso')->constrained('gl_soci')->nullOnDelete();
            $table->foreignId('nota_rimborso_id')->nullable()->after('anticipante_socio_id')->constrained('gl_note_rimborso')->nullOnDelete();
            $table->date('data_spesa')->nullable()->after('nota_rimborso_id');
        });

        // Le colonne del Dante non le ha mai scritte una pagina: si prova
        // comunque ad abbinare il testo a un socio, poi si tolgono.
        $vecchi = DB::table('gl_prima_nota')->where('sospeso', true)->get(['id', 'data', 'anticipato_da']);
        foreach ($vecchi as $r) {
            $nome = mb_strtolower(trim((string) $r->anticipato_da));
            $socio = $nome === '' ? null : DB::table('gl_soci')->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereRaw("LOWER(CONCAT(nome, ' ', cognome)) = ?", [$nome])
                    ->orWhereRaw("LOWER(CONCAT(cognome, ' ', nome)) = ?", [$nome]))
                ->value('id');
            DB::table('gl_prima_nota')->where('id', $r->id)->update(['data_spesa' => $r->data, 'anticipante_socio_id' => $socio]);
        }

        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->dropColumn(['anticipato_da', 'tipo_anticipante', 'rimborso_nota_numero', 'data_nota', 'iban_rimborso']);
        });

        Schema::table('gl_prima_nota_scontrini', function (Blueprint $table) {
            $table->foreignId('anticipante_socio_id')->nullable()->after('conto')->constrained('gl_soci')->nullOnDelete();
        });

        DB::table('gl_prima_nota_categorie')
            ->where('tipo', 'entrata')->where('nome', 'Contributi e donazioni da terzi')->whereNull('codice')
            ->update(['codice' => 'DONAZIONI']);
    }

    public function down(): void
    {
        DB::table('gl_prima_nota_categorie')->where('codice', 'DONAZIONI')->update(['codice' => null]);

        Schema::table('gl_prima_nota_scontrini', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anticipante_socio_id');
        });

        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->string('anticipato_da', 200)->nullable()->after('data_documento');
            $table->enum('tipo_anticipante', ['cd', 'esterno'])->nullable()->after('sospeso');
            $table->string('rimborso_nota_numero', 20)->nullable()->after('rimborsato_il');
            $table->date('data_nota')->nullable()->after('rimborso_nota_numero');
            $table->string('iban_rimborso', 34)->nullable()->after('data_nota');
            $table->dropConstrainedForeignId('nota_rimborso_id');
            $table->dropConstrainedForeignId('anticipante_socio_id');
            $table->dropColumn('data_spesa');
        });

        Schema::dropIfExists('gl_note_rimborso');
    }
};
