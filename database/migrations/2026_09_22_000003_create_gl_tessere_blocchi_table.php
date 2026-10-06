<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_tessere_blocchi', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno');
            $table->unsignedInteger('numero_da');
            $table->unsignedInteger('numero_a');
            $table->text('note')->nullable();
            $table->foreignId('inserito_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();

            // Un blocco non può sovrapporsi ad un altro dello stesso anno
            // (gestito a livello applicativo, come nel Gestionale Dante)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_tessere_blocchi');
    }
};
