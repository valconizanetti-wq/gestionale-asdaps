<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_gdpr_responsabili', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 200);
            $table->string('servizio', 200);
            $table->text('dati_trattati');
            $table->string('paese', 100)->default('Italia');
            $table->boolean('dpa_firmato')->default(false);
            $table->date('dpa_data')->nullable();
            $table->date('dpa_scadenza')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_gdpr_responsabili');
    }
};
