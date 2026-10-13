<?php

/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
| Gruppi e voci del menu (vedi ui.md §3). Una voce compare solo se:
|   1. la rotta esiste già (il modulo è stato costruito), e
|   2. il ruolo dell'utente ha accesso alla sezione (User::puoAccedere).
| Un gruppo senza voci visibili non compare.
|
| 'sezione' = codice in gl_sezioni; 'rotta' = nome della pagina principale.
| 'se' (facoltativo) = modulo che dipende dal periodo dell'ente in vigore
| oggi (ProfiloEnte::haModulo: 'rasd', 'l398', 'runts'; profilo-ente.md).
| L'ordine qui è l'ordine nel menu.
*/

return [
    [
        'gruppo' => 'Associazione',
        'voci' => [
            ['label' => 'Associazione',  'sezione' => 'associazione',  'rotta' => 'associazione.index'],
            ['label' => 'Soci',          'sezione' => 'soci',          'rotta' => 'soci.index'],
            ['label' => 'Domande',       'sezione' => 'domande',       'rotta' => 'domande.index'],
            ['label' => 'Tesseramento',  'sezione' => 'tesseramento',  'rotta' => 'tesseramento.index'],
            ['label' => 'Volontari',     'sezione' => 'volontari',     'rotta' => 'volontari.index'],
        ],
    ],
    [
        'gruppo' => 'Contabilità',
        'voci' => [
            ['label' => 'Prima Nota',    'sezione' => 'prima_nota',    'rotta' => 'prima-nota.index'],
            ['label' => 'Scontrini',     'sezione' => 'scontrini',     'rotta' => 'scontrini.index'],
            ['label' => 'Rinnovi quota', 'sezione' => 'rinnovi_quota', 'rotta' => 'rinnovi.index'],
            ['label' => 'Bilancio',      'sezione' => 'bilancio',      'rotta' => 'bilancio.index'],
            ['label' => 'Progetti',      'sezione' => 'progetti',      'rotta' => 'progetti.index'],
        ],
    ],
    [
        'gruppo' => 'Governance',
        'voci' => [
            ['label' => 'Consiglio Direttivo', 'sezione' => 'governance_cd', 'rotta' => 'governance.index'],
            ['label' => 'Assemblee',           'sezione' => 'assemblee',     'rotta' => 'assemblee.index'],
            ['label' => 'Verbali',             'sezione' => 'verbali',       'rotta' => 'verbali.index'],
            ['label' => 'Comunicazioni',       'sezione' => 'comunicazioni', 'rotta' => 'comunicazioni.index'],
            ['label' => 'Promemoria',          'sezione' => 'promemoria',    'rotta' => 'promemoria.index'],
            ['label' => 'Archivio documenti',  'sezione' => 'archivio',      'rotta' => 'archivio.index'],
        ],
    ],
    [
        'gruppo' => 'Controllo',
        'voci' => [
            ['label' => 'Compliance RASD', 'sezione' => 'compliance_rasd', 'rotta' => 'compliance-rasd.index', 'se' => 'rasd'],
            ['label' => 'Scadenzario',     'sezione' => 'scadenzario',     'rotta' => 'scadenzario.index'],
            ['label' => 'GDPR',            'sezione' => 'gdpr',            'rotta' => 'gdpr.index'],
            ['label' => 'Audit Log',       'sezione' => 'audit_log',       'rotta' => 'audit-log.index'],
        ],
    ],
    [
        'gruppo' => null, // in fondo, senza titolo di gruppo
        'voci' => [
            ['label' => 'Impostazioni', 'sezione' => 'impostazioni', 'rotta' => 'impostazioni.index'],
            ['label' => 'Utenti',       'sezione' => 'utenti',       'rotta' => 'utenti.index'],
        ],
    ],
];
