<?php

namespace Database\Seeders;

use App\Models\AssociazionePeriodo;
use Illuminate\Database\Seeder;

/**
 * Periodi della Compagnia (profilo-ente.md §2, decisioni di Paolo del
 * 05/10/2026): B.A.S. dal 01/01/2027, A.S.D. dal 01/01/2028. Inserisce
 * solo i periodi che mancano (per data): rilanciarlo non cambia quelli già
 * modificati dalla pagina Impostazioni › Ente.
 *
 *   php artisan db:seed --class=ProfiloEnteSeeder --force
 */
class ProfiloEnteSeeder extends Seeder
{
    private const SEDE = 'Campo di tiro "Franco Cortignani", località Macchietto, Viceno, 05014 Castel Viscardo (TR)';

    public function run(): void
    {
        AssociazionePeriodo::firstOrCreate(['dal' => '2027-01-01'], [
            'denominazione' => 'Compagnia Arcieri Castel Viscardo B.A.S.',
            'sigla' => 'B.A.S.',
            'forma_giuridica' => 'associazione',
            'forma_descrizione' => 'Base Associativa Sportiva',
            'sede_legale' => self::SEDE,
            'regime_fiscale' => 'non_commerciale',
            'ente_affiliazione' => 'CSEN',
            'statuto_versione' => '1.0',
            'informativa_versione' => '1.0',
            'atto' => 'Statuto della B.A.S. rivisto, da adottare in Assemblea entro il 2026',
        ]);

        // Lo statuto della B.A.S. prevede il passaggio con la sola delibera
        // sulla denominazione (art. 2.4): stessa versione, finché il
        // commercialista non indica altro. L'informativa va rifatta con il
        // nuovo titolare: versione 2.0, da pubblicare (lista di controllo).
        AssociazionePeriodo::firstOrCreate(['dal' => '2028-01-01'], [
            'denominazione' => 'Compagnia Arcieri Castel Viscardo A.S.D.',
            'sigla' => 'A.S.D.',
            'forma_giuridica' => 'asd',
            'forma_descrizione' => 'Associazione Sportiva Dilettantistica',
            'sede_legale' => self::SEDE,
            'regime_fiscale' => 'l398',
            'ente_affiliazione' => 'CSEN',
            'statuto_versione' => '1.0',
            'informativa_versione' => '2.0',
            'atto' => null,
        ]);
    }
}
