<?php

namespace Database\Seeders;

use App\Models\Impostazione;
use Illuminate\Database\Seeder;

/**
 * Impostazioni del modulo Soci (soci.md). firstOrCreate: non sovrascrive
 * valori già modificati, si può rilanciare senza rischi.
 */
class ImpostazioniSociSeeder extends Seeder
{
    public function run(): void
    {
        Impostazione::firstOrCreate(['chiave' => 'privacy_versione_corrente'], [
            'valore' => '1.0',
            'tipo' => 'string',
            'descrizione' => "Versione dell'informativa privacy in vigore, registrata con ogni consenso. Aggiornarla quando l'informativa cambia.",
        ]);

        Impostazione::firstOrCreate(['chiave' => 'certificato_medico_richiesto'], [
            'valore' => '0',
            'tipo' => 'boolean',
            'descrizione' => "Se attivo, il certificato medico diventa un requisito dell'iscrizione. Prima di attivarlo: attivare il modulo Certificati e censire il trattamento dei dati sanitari nel registro GDPR (gdpr.md).",
        ]);
    }
}
