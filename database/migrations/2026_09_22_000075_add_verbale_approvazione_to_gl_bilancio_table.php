<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_bilancio', function (Blueprint $table) {
            // Collegamento vero al verbale di assemblea che ha approvato il
            // rendiconto (art. 90 c.18 lett. d L. 289/2002, richiamato
            // dall'art. 7 D.Lgs. 36/2021: iter deliberativo specifico
            // richiesto per l'approvazione). I campi esistenti
            // data_assemblea/numero_verbale restano invariati come testo
            // libero di comodo/fallback (decisione esplicita: non rimossi,
            // vedi MANUALE-ASD.md §6.2) — la fonte di verità
            // per collegamenti reali è questa FK.
            $table->foreignId('verbale_approvazione_id')
                  ->nullable()
                  ->after('data_assemblea')
                  ->constrained('gl_verbali')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_bilancio', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verbale_approvazione_id');
        });
    }
};
