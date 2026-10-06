<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_gdpr_violazioni', function (Blueprint $table) {
            $table->id();
            $table->dateTime('data_scoperta');
            $table->string('tipologia', 100);
            $table->text('descrizione');
            $table->string('categorie_dati', 200);
            $table->integer('num_interessati')->default(0);
            $table->enum('livello_rischio', ['improbabile', 'probabile', 'elevato']);
            $table->boolean('notificato_garante')->default(false);
            $table->string('protocollo_garante', 100)->nullable();
            $table->dateTime('data_notifica_garante')->nullable();
            $table->boolean('comunicato_interessati')->default(false);
            $table->text('misure_adottate')->nullable();
            $table->enum('stato', ['aperta', 'chiusa'])->default('aperta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_gdpr_violazioni');
    }
};
