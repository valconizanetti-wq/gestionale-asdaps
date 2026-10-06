<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stesso pattern di gl_volontari_sessioni: una "sessione" di invio
        // periodico verso un ente esterno (lì la PEC di vidimazione, qui la
        // comunicazione trimestrale al RASD dei rimborsi forfettari erogati).
        Schema::create('gl_rimborsi_rasd_invii', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anno');
            $table->unsignedTinyInteger('trimestre'); // 1-4
            $table->date('data_invio')->nullable();
            $table->enum('stato', ['bozza', 'inviata'])->default('bozza');
            $table->string('ricevuta_path', 500)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['anno', 'trimestre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gl_rimborsi_rasd_invii');
    }
};
