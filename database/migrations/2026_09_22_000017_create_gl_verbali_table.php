<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_verbali', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['assemblea', 'cd_ammissione', 'cd_delibera'])->default('assemblea');
            $table->foreignId('assemblea_id')
                  ->nullable()
                  ->constrained('gl_assemblee')
                  ->nullOnDelete();

            // Collegamento concettuale al modulo Domande/Iscrizione, non ancora
            // costruito nel core: nessuna FK per ora (come nello schema originale)
            $table->unsignedBigInteger('domanda_id')->nullable();

            $table->date('periodo_da')->nullable();
            $table->date('periodo_a')->nullable();
            $table->string('titolo');
            $table->string('presidente_assemblea')->nullable();
            $table->string('segretario_verbalizzante')->nullable();
            $table->unsignedSmallInteger('soci_presenti')->default(0);
            $table->unsignedSmallInteger('soci_deleghe')->default(0);
            $table->json('presenze_cd')->nullable();
            $table->date('data_seduta')->nullable();
            $table->time('ora_inizio')->nullable();
            $table->time('ora_chiusura')->nullable();
            $table->longText('corpo_html');
            $table->enum('stato', ['bozza', 'approvato', 'archiviato'])->default('bozza');
            $table->timestamp('approvato_il')->nullable();
            $table->timestamp('archiviato_il')->nullable();
            $table->string('hash_contabilita', 64)->nullable();
            $table->string('hash_file_verbale', 64)->nullable();
            $table->string('verbale_firmato_path', 500)->nullable();
            $table->unsignedSmallInteger('anno_locked')->nullable()
                  ->comment('Anno contabile bloccato da questo verbale');
            $table->timestamp('chiuso_il')->nullable();
            $table->foreignId('chiuso_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->string('pdf_path')->nullable();
            $table->foreignId('redatto_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_verbali');
    }
};
