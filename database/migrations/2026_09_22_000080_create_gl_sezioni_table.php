<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_sezioni', function (Blueprint $table) {
            $table->id();
            $table->string('codice', 50)->unique();
            $table->string('label', 100);
            $table->unsignedSmallInteger('ordine')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_sezioni');
    }
};
