<?php

namespace Database\Seeders;

use App\Models\Promemoria;
use App\Promemoria\Registro;
use Illuminate\Database\Seeder;

/**
 * Promemoria pronti di ogni funzione (promemoria.md): si creano solo se la
 * funzione non ne ha ancora, quindi rilanciarlo non tocca quelli modificati.
 */
class PromemoriaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Registro::tutte() as $chiave => $f) {
            if (Promemoria::where('funzione', $chiave)->exists()) {
                continue;
            }
            foreach ($f->predefiniti() as $p) {
                Promemoria::create($p + ['funzione' => $chiave, 'quando' => 'data_fissa', 'ora' => '09:00:00', 'attivo' => true]);
            }
        }
    }
}
