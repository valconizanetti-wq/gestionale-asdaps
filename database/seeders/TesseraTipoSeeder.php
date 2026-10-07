<?php

namespace Database\Seeders;

use App\Models\TesseraTipo;
use Illuminate\Database\Seeder;

/**
 * Tipi di tessera CSEN (v0.37.0). Per ora solo «Silver Base» a 4 € (Paolo,
 * 07/10/2026): gli altri tipi del listino si aggiungono dalla pagina
 * Tessere. Si può rilanciare: un tipo già presente non cambia (riceve solo il
 * prezzo, se non ce l'ha).
 */
class TesseraTipoSeeder extends Seeder
{
    public function run(): void
    {
        $t = TesseraTipo::firstOrCreate(['nome' => 'Silver Base'], ['prezzo' => 4.00, 'attivo' => true, 'ordine' => 10]);
        // Creato dalla migration dalle righe già presenti, senza prezzo
        if ($t->prezzo === null) {
            $t->update(['prezzo' => 4.00]);
        }
    }
}
