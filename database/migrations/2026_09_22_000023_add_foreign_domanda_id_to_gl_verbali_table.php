<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gl_verbali', function (Blueprint $table) {
            $table->foreign('domanda_id')
                  ->references('id')->on('gl_domande')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_verbali', function (Blueprint $table) {
            $table->dropForeign(['domanda_id']);
        });
    }
};
