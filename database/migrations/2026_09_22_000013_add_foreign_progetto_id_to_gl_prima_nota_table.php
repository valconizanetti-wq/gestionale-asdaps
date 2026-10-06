<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->foreign('progetto_id')
                  ->references('id')->on('gl_progetti')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->dropForeign(['progetto_id']);
        });
    }
};
