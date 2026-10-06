<?php

namespace Database\Seeders;

use App\Models\Qualifica;
use Illuminate\Database\Seeder;

class QualificaSeeder extends Seeder
{
    public function run(): void
    {
        $qualifiche = [
            ['codice' => 'atleta_praticante', 'label' => 'Atleta Praticante'],
            ['codice' => 'atleta_agonista', 'label' => 'Atleta Agonista'],
            ['codice' => 'dirigente', 'label' => 'Dirigente'],
            ['codice' => 'tecnico', 'label' => 'Tecnico'],
        ];

        foreach ($qualifiche as $q) {
            Qualifica::firstOrCreate(['codice' => $q['codice']], $q);
        }
    }
}
