<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RuoloSeeder::class,
        ]);

        // Utente di prova SOLO in locale: in produzione un presidente con
        // password "password" sarebbe una porta aperta. Gli utenti reali si
        // creano a mano al primo deploy (vedi la scheda del deploy).
        if (app()->environment('local')) {
            User::firstOrCreate(
                ['email' => 'test@example.com'],
                [
                    'name' => 'Test User',
                    'password' => Hash::make('password'),
                    'ruolo' => 'presidente',
                    'attivo' => true,
                ],
            );
        }

        $this->call([
            AssociazioneSeeder::class,
            // Periodi dell'ente: B.A.S. dal 2027, A.S.D. dal 2028 (profilo-ente.md).
            ProfiloEnteSeeder::class,
        ]);

        $this->call([
            QualificaSeeder::class,
            DivisioneArcoSeeder::class,
            SpecialitaGaraSeeder::class,
        ]);

        $this->call([
            ImpostazioneSeeder::class,
            ImpostazioniSociSeeder::class,
        ]);

        $this->call([
            SezioneSeeder::class,
            // Categorie di Prima Nota riprese da gestionale-acv (v0.35.0, prima-nota.md)
            PrimaNotaCategoriaSeeder::class,
            PromemoriaSeeder::class,
        ]);
    }
}
