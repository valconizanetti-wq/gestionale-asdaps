<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_audit_archivi', function (Blueprint $table) {
            $table->id();
            $table->dateTime('data_archiviazione');
            $table->string('file_archivio');
            $table->string('periodo_label', 50);
            $table->dateTime('soglia_data');
            $table->unsignedInteger('record_eliminati');
            $table->longText('hash_righe_eliminate');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_audit_archivi');
    }
};
