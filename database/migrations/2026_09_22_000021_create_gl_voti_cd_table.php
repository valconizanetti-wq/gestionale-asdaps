<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_voti_cd', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domanda_id')
                  ->constrained('gl_domande')
                  ->cascadeOnDelete();
            $table->string('token_link', 64)->unique();
            $table->string('consigliere_nome');
            $table->string('consigliere_email');
            $table->enum('voto', ['favorevole', 'contrario', 'approvato', 'rifiutato'])->nullable();
            $table->string('motivo_rifiuto')->nullable();
            $table->timestamp('votato_il')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_voti_cd');
    }
};
