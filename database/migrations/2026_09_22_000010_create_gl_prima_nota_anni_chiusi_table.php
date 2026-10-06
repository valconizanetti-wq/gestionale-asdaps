<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_prima_nota_anni_chiusi', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno')->unique();
            $table->timestamp('chiuso_il')->nullable();
            $table->foreignId('chiuso_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_prima_nota_anni_chiusi');
    }
};
