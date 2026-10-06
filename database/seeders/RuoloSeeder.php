<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ruolo;

class RuoloSeeder extends Seeder
{
    public function run(): void
    {
        $ruoli = [
            ['codice' => 'presidente', 'label' => 'Presidente',  'colore_avatar' => '#8e44ad', 'badge_class' => 'bg-purple', 'ordine' => 1],
            ['codice' => 'segretario', 'label' => 'Segretario',  'colore_avatar' => '#2980b9', 'badge_class' => 'bg-blue',   'ordine' => 2],
            ['codice' => 'tesoriere',  'label' => 'Tesoriere',   'colore_avatar' => '#27ae60', 'badge_class' => 'bg-green',  'ordine' => 3],
            ['codice' => 'revisore',   'label' => 'Revisore',    'colore_avatar' => '#d35400', 'badge_class' => 'bg-orange', 'ordine' => 4],
        ];

        foreach ($ruoli as $ruolo) {
            Ruolo::updateOrCreate(['codice' => $ruolo['codice']], $ruolo);
        }
    }
}
