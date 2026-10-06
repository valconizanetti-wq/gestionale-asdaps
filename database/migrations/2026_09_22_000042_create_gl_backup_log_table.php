<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_backup_log', function (Blueprint $table) {
            $table->increments('id');
            $table->string('tipo', 10)->default('db');
            $table->enum('esito', ['ok', 'fail']);
            $table->text('dettaglio')->nullable();
            $table->string('file_path')->nullable();
            $table->string('hash', 64)->nullable();
            $table->decimal('size_kb', 12, 1)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_backup_log');
    }
};
