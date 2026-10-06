<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_lettere_invii', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lettera_id')
                  ->constrained('gl_lettere')
                  ->cascadeOnDelete();
            $table->string('destinatario_email');
            $table->enum('stato', ['inviata', 'errore'])->default('inviata');
            $table->text('errore_messaggio')->nullable();
            $table->foreignId('inviata_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamp('inviata_il')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_lettere_invii');
    }
};
