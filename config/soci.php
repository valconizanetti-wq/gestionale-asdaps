<?php

/*
|--------------------------------------------------------------------------
| Modulo Soci — elenchi chiusi (vedi soci.md)
|--------------------------------------------------------------------------
*/

return [

    /*
     * Categorie di gl_soci_documenti.categoria (max 20 caratteri).
     * Esclusi per scelta: la privacy firmata (campo dedicato
     * gl_soci.privacy_doc_path) e il certificato medico (tabella
     * gl_certificati_medici, con scadenza).
     */
    'categorie_documenti' => [
        'documento_identita' => "Documento d'identità",
        'scheda_iscrizione'  => 'Scheda di iscrizione',
        'modulo_tutore'      => 'Modulo del tutore',
        'altro'              => 'Altro',
    ],

    /* File ammessi negli upload del socio. */
    'upload' => [
        'estensioni' => 'pdf,jpg,jpeg,png',
        'max_kb'     => 5120,
    ],
];
