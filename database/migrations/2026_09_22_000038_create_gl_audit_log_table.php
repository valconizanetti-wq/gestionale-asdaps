<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gl_audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('evento', 100);
            $table->enum('operazione', ['created', 'updated', 'deleted'])->nullable();
            $table->smallInteger('anno')->nullable();

            // Riferimento generico all'entità coinvolta (qualsiasi modulo)
            $table->string('entita_tipo', 50)->nullable();
            $table->string('modello', 80)->nullable();
            $table->unsignedBigInteger('entita_id')->nullable();

            // Nome dell'utente conservato come testo: l'audit trail deve
            // restare leggibile anche se l'utente viene poi eliminato
            $table->string('utente_nome', 150);
            $table->string('ip_address', 45)->nullable();
            $table->text('dettaglio')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->enum('livello_rischio', ['LOW', 'MEDIUM', 'HIGH'])->default('LOW');
            $table->json('campi_modificati')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Catena hash per la rilevazione di manomissioni
            $table->string('hash', 64)->nullable();
            $table->string('previous_hash', 64)->nullable();

            $table->index('evento');
            $table->index(['entita_tipo', 'entita_id']);
            $table->index('modello');
            $table->index('livello_rischio');
            $table->index('operazione');
            $table->index('anno');
            $table->index('hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_audit_log');
    }
};
