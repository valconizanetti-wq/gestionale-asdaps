<?php

/*
|--------------------------------------------------------------------------
| Stati → badge (vedi ui.md §3)
|--------------------------------------------------------------------------
| Unico punto in cui si decide l'aspetto di uno stato. Le viste usano
| <x-badge-stato dominio="socio" :valore="$socio->stato" /> e non scelgono
| mai il colore da sole. Varianti: ok, warn, ko, info, neutral.
| Uno stato non elencato viene mostrato neutro, con l'etichetta ricavata
| dal valore.
*/

return [
    'socio' => [
        'attivo'     => ['variante' => 'ok',      'label' => 'Attivo'],
        'sospeso'    => ['variante' => 'warn',    'label' => 'Sospeso'],
        'cancellato' => ['variante' => 'neutral', 'label' => 'Cancellato'],
    ],
    // Domande di iscrizione (domande-iscrizione.md)
    'domanda' => [
        'in_verifica'  => ['variante' => 'neutral', 'label' => 'Da confermare'],
        'in_attesa'    => ['variante' => 'warn',    'label' => 'In votazione'],
        'da_rifiutare' => ['variante' => 'warn',    'label' => 'Diniego da inviare'],
        'in_parita'    => ['variante' => 'warn',    'label' => 'Decide il Presidente'],
        'approvata'    => ['variante' => 'ok',      'label' => 'Accolta'],
        'rifiutata'    => ['variante' => 'ko',      'label' => 'Non accolta'],
        'scaduta'      => ['variante' => 'neutral', 'label' => 'Non confermata'],
        'decaduta'     => ['variante' => 'neutral', 'label' => 'Decaduta'],
        // Non è uno stato della tabella: domanda collegata a un socio.
        'iscritta'     => ['variante' => 'ok',      'label' => 'Iscritta'],
    ],
    'tesseramento' => [
        'attivo'  => ['variante' => 'ok',   'label' => 'Attivo'],
        'scaduto' => ['variante' => 'ko',   'label' => 'Scaduto'],
        'sospeso' => ['variante' => 'warn', 'label' => 'Sospeso'],
    ],
    'rimborso' => [
        'bozza'     => ['variante' => 'neutral', 'label' => 'Bozza'],
        'in_attesa' => ['variante' => 'warn',    'label' => 'In attesa'],
        'pagato'    => ['variante' => 'ok',      'label' => 'Pagato'],
        'annullato' => ['variante' => 'neutral', 'label' => 'Annullato'],
    ],
    'rasd_invio' => [
        'bozza'   => ['variante' => 'neutral', 'label' => 'Bozza'],
        'inviata' => ['variante' => 'info',    'label' => 'Inviata'],
    ],
    'rasd_verifica' => [
        'aperta'               => ['variante' => 'warn',    'label' => 'Aperta'],
        'regolarizzata'        => ['variante' => 'ok',      'label' => 'Regolarizzata'],
        'chiusa_senza_seguito' => ['variante' => 'neutral', 'label' => 'Chiusa senza seguito'],
    ],
    'bilancio' => [
        'bozza'     => ['variante' => 'neutral', 'label' => 'Bozza'],
        'approvato' => ['variante' => 'ok',      'label' => 'Approvato'],
    ],
];
