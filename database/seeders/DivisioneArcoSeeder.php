<?php

namespace Database\Seeders;

use App\Models\DivisioneArco;
use Illuminate\Database\Seeder;

class DivisioneArcoSeeder extends Seeder
{
    public function run(): void
    {
        $divisioni = [
            ['codice' => 'olimpico', 'nome' => 'Arco Olimpico (Ricurvo)'],
            ['codice' => 'compound', 'nome' => 'Compound'],
            ['codice' => 'nudo', 'nome' => 'Arco Nudo (Barebow)'],
            ['codice' => 'longbow', 'nome' => 'Longbow'],
            ['codice' => 'istintivo', 'nome' => 'Arco Istintivo (Tradizionale)'],
        ];

        foreach ($divisioni as $d) {
            DivisioneArco::firstOrCreate(['codice' => $d['codice']], $d);
        }
    }
}
