<?php

/*
| Audit Log — etichette leggibili (audit-log.md §9). Il codice resta nel
| registro e si vede nel dettaglio; qui solo come lo mostrano le pagine.
| Un evento non in elenco si mostra ricavando il nome dal codice
| ("ESEMPIO_EVENTO" -> "Esempio evento").
*/

return [
    'rischi' => [
        'HIGH' => ['Alto', 'ko'],
        'MEDIUM' => ['Medio', 'warn'],
        'LOW' => ['Basso', 'neutral'],
    ],

    'operazioni' => [
        'created' => 'Creazione',
        'updated' => 'Modifica',
        'deleted' => 'Eliminazione',
        'restored' => 'Ripristino',
    ],

    'modelli' => [
        'Socio' => 'Socio',
        'User' => 'Utente',
        'Domanda' => 'Domanda',
        'AssociazionePeriodo' => "Profilo dell'ente",
        'Impostazione' => 'Impostazione',
        'AuditLog' => 'Audit Log',
        'AttestazioneTecnica' => 'Attestazione tecnica',
        'PrimaNota' => 'Prima Nota',
        'PrimaNotaDocumento' => 'Giustificativo',
        'PrimaNotaCategoria' => 'Categoria di Prima Nota',
        'AnnoContabile' => 'Anno contabile',
        'Tag' => 'Tag',
    ],

    'eventi' => [
        'SOCI_ESPORTATI' => 'Soci esportati',
        'PRIVACY_LINK_INVIATO' => 'Link privacy inviato',
        'PROFILO_ENTE_MODIFICATO' => "Profilo dell'ente modificato",
        'IMPOSTAZIONE_MODIFICATA' => 'Impostazione modificata',
        'DOMANDA_ONLINE_INVIATA' => 'Domanda online inviata',
        'DOMANDA_ONLINE_CONFERMATA' => 'Domanda online confermata',
        'DOMANDA_ONLINE_ANNULLATA' => 'Domanda online annullata',
        'DOMANDA_DECADUTA' => 'Domanda decaduta',
        'DOMANDA_DECISIONE_PRESIDENTE' => 'Decisione del Presidente',
        'DOMANDA_DINIEGO_INVIATO' => 'Diniego inviato',
        'DOMANDA_CANCELLATA_FINE_CONSERVAZIONE' => 'Domanda cancellata (fine conservazione)',
        'VOTAZIONE_CD_APERTA' => 'Votazione del CD aperta',
        'VOTAZIONE_CD_CHIUSA' => 'Votazione del CD chiusa',
        'VOTO_CD_ESPRESSO' => 'Voto del CD espresso',
        'CERTIFICATO_INTEGRITA_GENERATO' => 'Certificato di integrità generato',
        'ATTESTAZIONE_TECNICA_CARICATA' => 'Attestazione tecnica caricata',
        'AUDIT_INTEGRITY_FAIL' => 'Anomalia nella catena',
        'AUDIT_ESPORTATO' => 'Audit Log esportato',
        'PRIMA_NOTA_ESPORTATA' => 'Prima Nota esportata',
        'PRIMA_NOTA_CORREZIONE_ANNO_CHIUSO' => 'Correzione su anno chiuso',
        'SALDI_APERTURA_MODIFICATI' => 'Saldi di apertura modificati',
        'ANNO_CONTABILE_RIAPERTO' => 'Anno contabile riaperto',
    ],
];
