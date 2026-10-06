<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_archivio_documenti', function (Blueprint $table) {
            $table->id();

            // Riferimento opzionale a un record di un altro modulo ("business")
            // oppure caricamento libero, non legato a nulla ("libero")
            $table->enum('fonte_tipo', ['business', 'libero']);
            $table->string('fonte_modello', 100)->nullable();
            $table->unsignedBigInteger('fonte_id')->nullable();

            $table->string('tipo_documento', 60);
            $table->string('ambito', 60);
            $table->string('titolo');
            $table->text('descrizione')->nullable();
            $table->text('note_interne')->nullable();
            $table->json('tag')->nullable();
            $table->smallInteger('anno');
            $table->date('data_documento');
            $table->string('file_path')->nullable();
            $table->string('file_nome')->nullable();
            $table->string('hash_file', 64)->nullable();
            $table->foreignId('caricato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->unique(['fonte_modello', 'fonte_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_archivio_documenti');
    }
};
