<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_lettere', function (Blueprint $table) {
            $table->id();

            // Protocollo
            $table->unsignedSmallInteger('anno');
            $table->unsignedInteger('numero'); // progressivo per anno
            $table->string('numero_protocollo', 20); // es. 2026/001

            $table->enum('tipo', ['uscita', 'interna', 'circolare'])->default('uscita');

            $table->foreignId('destinatario_id')
                  ->nullable()
                  ->constrained('gl_destinatari')
                  ->nullOnDelete();

            // Snapshot destinatario al momento della protocollazione (immutabile)
            $table->string('destinatario_nome')->nullable();
            $table->string('destinatario_indirizzo')->nullable();
            $table->string('destinatario_email')->nullable();

            $table->date('data_lettera');
            $table->string('oggetto');
            $table->longText('testo_html');

            $table->foreignId('firmatario_id')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->string('firma_testo_snapshot')->nullable();

            $table->enum('stato', ['bozza', 'protocollata'])->default('bozza');
            $table->timestamp('protocollata_il')->nullable();
            $table->foreignId('protocollata_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();

            $table->string('pdf_path')->nullable();

            $table->foreignId('creata_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->unique(['anno', 'numero']); // nessun buco nella numerazione
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_lettere');
    }
};
