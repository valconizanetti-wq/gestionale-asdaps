<?php

namespace Database\Seeders;

use App\Models\SpecialitaGara;
use Illuminate\Database\Seeder;

class SpecialitaGaraSeeder extends Seeder
{
    public function run(): void
    {
        $specialita = [
            ['codice' => 'targa', 'nome' => 'Tiro alla Targa'],
            ['codice' => 'campagna', 'nome' => 'Tiro di Campagna (Field)'],
            ['codice' => '3d', 'nome' => 'Tiro 3D'],
        ];

        foreach ($specialita as $s) {
            SpecialitaGara::firstOrCreate(['codice' => $s['codice']], $s);
        }
    }
}
