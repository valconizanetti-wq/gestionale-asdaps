<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.33.0 (05/10/2026): link personale per l'informativa privacy e il
 * consenso a foto e video (privacy-link.md §2). Una riga per ogni link
 * inviato. Nel database solo l'impronta del token (SHA-256): il token in
 * chiaro esiste solo nell'email. Monouso e senza scadenza; un nuovo invio
 * annulla i link aperti dello stesso socio (annullato_il).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_privacy_link', function (Blueprint $table) {
            $table->id();
            $table->foreignId('socio_id')->constrained('gl_soci')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->enum('destinatario', ['socio', 'tutore']);
            $table->string('email');
            $table->foreignId('inviato_da')->nullable()->constrained('gl_users')->nullOnDelete();
            $table->dateTime('inviato_il');
            $table->dateTime('annullato_il')->nullable();
            $table->dateTime('usato_il')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('informativa_versione', 10)->nullable();
            $table->boolean('foto_video')->nullable();
            $table->timestamps();

            $table->index(['socio_id', 'usato_il', 'annullato_il']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_privacy_link');
    }
};
