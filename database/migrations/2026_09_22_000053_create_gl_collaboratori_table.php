<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_collaboratori', function (Blueprint $table) {
            $table->id();
            $table->string('cognome', 100);
            $table->string('nome', 100);
            $table->string('codice_fiscale', 16)->nullable()->unique();
            $table->string('email', 150)->nullable();
            $table->string('indirizzo', 250)->nullable();
            $table->string('cap', 5)->nullable();
            $table->string('comune', 100)->nullable();
            $table->string('provincia', 2)->nullable();
            $table->enum('tipo', ['autonomo', 'forfettario', 'occasionale'])->default('autonomo');
            $table->string('specializzazione', 200)->nullable();
            $table->boolean('attivo')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_collaboratori');
    }
};
