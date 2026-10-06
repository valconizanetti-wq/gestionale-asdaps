<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_gdpr_registro_firmato', function (Blueprint $table) {
            $table->id();
            $table->date('data_firma');
            $table->text('note_modifiche');
            $table->string('pdf_path');
            $table->string('pdf_filename');
            $table->foreignId('caricato_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_gdpr_registro_firmato');
    }
};
