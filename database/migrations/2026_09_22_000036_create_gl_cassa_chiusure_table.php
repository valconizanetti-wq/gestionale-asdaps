<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_cassa_chiusure', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno');
            $table->unsignedTinyInteger('mese');
            $table->decimal('saldo_pn', 10, 2);
            $table->decimal('saldo_fisico', 10, 2);
            $table->decimal('scostamento', 10, 2);
            $table->text('note_scostamento')->nullable();
            $table->foreignId('chiuso_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->dateTime('chiuso_il');

            $table->unique(['anno', 'mese']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_cassa_chiusure');
    }
};
