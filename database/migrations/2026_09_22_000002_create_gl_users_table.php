<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('ruolo', 50);
            $table->boolean('attivo')->default(true);
            $table->timestamp('ultimo_accesso')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('ruolo')->references('codice')->on('gl_ruoli');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_users');
    }
};
