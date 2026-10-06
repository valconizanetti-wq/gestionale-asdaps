<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_comunicazioni', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['circolare']);
            $table->string('oggetto', 500);
            $table->text('testo');
            $table->text('odg')->nullable();
            $table->foreignId('assemblea_id')
                  ->nullable()
                  ->constrained('gl_assemblee')
                  ->nullOnDelete();
            $table->string('gruppo_destinatari', 50)->default('attivi');
            $table->text('soci_ids_custom')->nullable();
            $table->dateTime('data_invio')->nullable();
            $table->foreignId('inviata_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->integer('n_destinatari')->default(0);
            $table->integer('n_consegnate')->default(0);
            $table->integer('n_fallite')->default(0);
            $table->integer('n_cartaceo')->default(0);
            $table->string('hash_registro', 64)->nullable();
            $table->text('hash_allegati')->nullable();
            $table->enum('canale_primario', ['email', 'manuale'])->default('email');
            $table->enum('stato', ['bozza', 'inviata'])->default('bozza');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_comunicazioni');
    }
};
