<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_gdpr_responsabili_docs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('responsabile_id')
                  ->constrained('gl_gdpr_responsabili')
                  ->cascadeOnDelete();
            $table->string('nome');
            $table->string('descrizione', 500)->nullable();
            $table->string('path', 500);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_gdpr_responsabili_docs');
    }
};
