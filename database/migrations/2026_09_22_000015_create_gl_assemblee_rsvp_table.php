<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_assemblee_rsvp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assemblea_id')
                  ->constrained('gl_assemblee')
                  ->cascadeOnDelete();
            $table->string('destinatario_email');
            $table->string('destinatario_nome');
            $table->string('token', 64)->unique();
            $table->boolean('is_test')->default(false);
            $table->enum('risposta', ['confermata', 'assente'])->nullable();
            $table->timestamp('risposta_il')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_assemblee_rsvp');
    }
};
