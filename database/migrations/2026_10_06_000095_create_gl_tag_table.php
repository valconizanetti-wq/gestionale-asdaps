<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v0.35.0 (06/10/2026), prima-nota.md §2: tag trasversali dei movimenti,
 * ripresi da gestionale-acv (conta_tag). Un tag per movimento, valido per
 * tutti gli anni, creato al volo dal modulo del movimento: serve a vedere
 * il costo di un lavoro che attraversa più categorie (es. "Tettoia").
 * Distinto da Progetti, che resta per bandi e contributi da rendicontare
 * (decisione di Paolo, 06/10/2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_tag', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 100)->unique();
            $table->timestamps();
        });

        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->foreignId('tag_id')->nullable()->after('progetto_id')
                  ->constrained('gl_tag')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gl_prima_nota', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tag_id');
        });
        Schema::dropIfExists('gl_tag');
    }
};
