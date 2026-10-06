<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_assemblee', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['cd', 'ordinaria', 'straordinaria']);
            $table->date('data');
            $table->time('ora_inizio')->nullable();
            $table->string('luogo')->nullable();
            $table->json('ordine_del_giorno')->nullable();
            $table->enum('stato', ['bozza', 'convocata', 'svolta', 'rinviata'])->default('bozza');
            $table->text('note')->nullable();
            $table->foreignId('creata_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamp('convocazione_inviata_il')->nullable();
            $table->timestamp('svolta_il')->nullable();
            $table->foreignId('svolta_da')
                  ->nullable()
                  ->constrained('gl_users')
                  ->nullOnDelete();
            $table->timestamp('rinviata_il')->nullable();
            $table->text('rinvio_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_assemblee');
    }
};
