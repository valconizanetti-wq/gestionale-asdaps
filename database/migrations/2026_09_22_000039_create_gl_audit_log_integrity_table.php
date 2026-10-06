<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_audit_log_integrity', function (Blueprint $table) {
            $table->increments('id');
            $table->smallInteger('anno');
            $table->dateTime('computed_at');
            $table->string('hash_chain', 64);
            $table->integer('records_count')->default(0);
            $table->string('esito', 10)->default('ok');
            $table->string('generato_da', 150)->nullable(); // testo libero, come utente_nome in audit_log
            $table->string('uuid_documento', 36)->nullable();
            $table->dateTime('created_at')->nullable();

            $table->index('anno');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_audit_log_integrity');
    }
};
