<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->foreign('chiuso_da_verb_id')
                  ->references('id')->on('gl_verbali')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->dropForeign(['chiuso_da_verb_id']);
        });
    }
};
