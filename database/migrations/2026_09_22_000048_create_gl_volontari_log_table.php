<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_volontari_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('volontario_id')->constrained('gl_volontari');
            $table->string('user_name', 200); // testo libero, come in gl_audit_log
            $table->string('evento', 100);
            $table->text('dettaglio')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_volontari_log');
    }
};
