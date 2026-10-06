<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_attestazioni_tecniche', function (Blueprint $table) {
            $table->id();
            $table->string('oggetto');
            $table->date('data_firma');
            $table->text('note')->nullable();
            $table->string('eccezioni_ids')->nullable();
            $table->unsignedInteger('eccezioni_count')->default(0);
            $table->unsignedInteger('anomalie_count')->default(0);
            $table->unsignedInteger('records_totali')->default(0);
            $table->unsignedInteger('record_pre_catena')->default(0);
            $table->unsignedInteger('corretti')->default(0);
            $table->unsignedInteger('gap_count')->default(0);
            $table->string('pdf_path');
            $table->string('pdf_filename');
            $table->string('relazione_path')->nullable();
            $table->string('relazione_filename')->nullable();
            $table->string('caricato_da'); // testo libero, come nel resto del modulo audit
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_attestazioni_tecniche');
    }
};
