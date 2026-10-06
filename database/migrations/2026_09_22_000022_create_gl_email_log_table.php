<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_email_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domanda_id')
                  ->nullable()
                  ->constrained('gl_domande')
                  ->nullOnDelete();
            $table->string('tipo_email', 60);
            $table->string('destinatario_email');
            $table->string('destinatario_nome')->nullable();
            $table->string('oggetto')->nullable();
            $table->enum('esito', ['inviata', 'fallita'])->default('inviata');
            $table->text('errore')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_email_log');
    }
};
