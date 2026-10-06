<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_gdpr_richieste', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo', ['accesso', 'rettifica', 'cancellazione', 'limitazione', 'portabilita', 'opposizione']);
            $table->string('nominativo', 200);
            $table->string('contatto', 200)->nullable();
            $table->date('data_ricezione');
            $table->date('scadenza');
            $table->boolean('identita_verificata')->default(false);
            $table->text('descrizione')->nullable();
            $table->enum('stato', ['aperta', 'in_lavorazione', 'chiusa', 'rifiutata'])->default('aperta');
            $table->text('risposta')->nullable();
            $table->date('data_risposta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_gdpr_richieste');
    }
};
