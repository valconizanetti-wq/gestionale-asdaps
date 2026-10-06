<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_ruoli', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codice', 50)->unique();
            $table->string('label', 100);
            $table->string('colore_avatar', 20);
            $table->string('badge_class', 50);
            $table->integer('ordine')->default(0);
            $table->boolean('attivo')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_ruoli');
    }
};
