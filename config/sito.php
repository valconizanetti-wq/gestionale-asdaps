<?php

/*
| Sito della Compagnia (WordPress), di cui le pagine pubbliche del
| gestionale riprendono intestazione, menu e piè di pagina (ui.md §3,
| "Tema del sito"). Se cambia il menu del sito, va aggiornato qui.
| SITO_URL nel .env: oggi il sito temporaneo, poi il dominio definitivo.
*/

return [
    'url' => rtrim(env('SITO_URL', 'https://arciericastello.valconi.it'), '/'),
    // Dominio ed email definitivi (Paolo, 29/09/2026). L'email è già il
    // valore predefinito; il sito si attiva nel .env con
    // SITO_URL=https://arciericastello.it quando è online.
    'dominio_definitivo' => 'arciericastello.it',
    'email_definitiva' => 'info@arciericastello.it',

    // Indirizzo della Compagnia (Paolo, 29/09/2026), in due forme:
    // - pubblico: sito, carta intestata, contatti e comunicazioni;
    // - ufficiale: atti ufficiali e moduli fiscali (Agenzia delle Entrate,
    //   modello AA5/6, RASD), come nello statuto (art. 1, c. 2).
    'indirizzo' => [
        'pubblico' => [
            'Campo di tiro "Franco Cortignani"',
            'Località Macchietto di Viceno, SP107',
            '05014 Castel Viscardo (TR)',
        ],
        'ufficiale' => 'Campo di tiro "Franco Cortignani", località Macchietto, Viceno, 05014 Castel Viscardo (TR)',
    ],

    // Immagini dell'intestazione, copiate dal tema del sito
    // (astra-child/img/) in public/images/sito/.
    'intestazione' => [
        'desktop' => 'images/sito/header-desktop.jpg',
        'telefono' => 'images/sito/header-mobile.jpg',
        'alt' => "Compagnia Arcieri Castel Viscardo - CSEN Settore Nazionale Tiro con l'Arco",
    ],

    // Piè di pagina: stesse righe di footer.php del sito (la ragione
    // sociale diventerà "A.S.D." quando cambierà anche nel sito).
    'piede' => [
        // Denominazione (Paolo, 29/09/2026): sempre "A.S.D.", come nello statuto.
        'intestazione' => 'Compagnia Arcieri Castel Viscardo A.S.D.',
        'email' => env('SITO_EMAIL', 'info@arciericastello.it'),
        'codice_fiscale' => '90022610555',
        // Riga della sede nel piè delle email: indirizzo pubblico su una riga.
        'sede' => 'Campo di tiro "Franco Cortignani" · Località Macchietto di Viceno, SP107 · 05014 Castel Viscardo (TR)',
        'gestione_contabile' => '/hub',
        'credito' => 'made by valconi',
    ],

    // Voci del menu (Aspetto → Menu del sito, posizione "Menu Principale"): etichetta => percorso sul sito. "attiva" = la voce
    // evidenziata nelle pagine del gestionale (sono le pagine di iscrizione).
    'menu' => [
        ['etichetta' => 'Chi siamo', 'percorso' => '/chi-siamo/'],
        ['etichetta' => 'Attività', 'percorso' => '/attivita/'],
        ['etichetta' => 'Calendario & Gare', 'percorso' => '/calendario-gare/'],
        ['etichetta' => 'Iscrizioni', 'percorso' => '/iscrizioni/', 'attiva' => true],
        ['etichetta' => 'Galleria', 'percorso' => '/galleria/'],
        ['etichetta' => 'Area soci', 'percorso' => '/area-riservata/'],
    ],
];
