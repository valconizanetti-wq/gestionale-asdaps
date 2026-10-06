<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Associazione;

class AssociazioneSeeder extends Seeder
{
    public function run(): void
    {
        Associazione::updateOrCreate(
            ['id' => 1],
            [
                'ragione_sociale'             => 'Associazione di Esempio APS',
                'nome_breve'                  => null,
                'codice_fiscale'              => '00000000000',
                'forma_giuridica'             => 'Associazione di Promozione Sociale',
                'indirizzo'                   => 'Via da definire, 1',
                'cap'                         => '00000',
                'citta'                       => 'Città da definire',
                'provincia'                   => 'XX',
                'email'                       => null,
                'email_pec'                   => null,
                'telefono'                    => null,
                'sito_web'                    => null,
                'firma_comunicazioni'         => null,
                'banca_nome'                  => null,
                'iban'                        => null,
                'bic_swift'                   => null,
                'runts_numero'                => null,
                'runts_data_iscrizione'       => null,
                'runts_sezione'               => 'APS',
                'rete_affiliazione_nome'      => null,
                'rete_affiliazione_codice'    => null,
                'rete_affiliazione_referente' => null,
                'anno_sociale_corrente'       => (int) date('Y'),
            ]
        );
    }
}
