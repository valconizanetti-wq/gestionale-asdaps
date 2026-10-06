<?php

/*
| Testi della pagina di iscrizione (domande-iscrizione.md §3, "Pagina
| unica"). Stanno qui, e non nelle viste, perché si possano correggere
| senza toccare il codice: dopo la modifica serve solo il deploy.
|
| Attenzione ai numeri: 5 giorni, 10 e 30 giorni sono termini dello
| statuto (art. 6) e delle costanti in Domanda e IscrizioneOnline. Se
| cambiano lì, vanno cambiati anche qui.
|
| Testi rivisti da Paolo il 28/09/2026; passo "Invia la domanda" al posto
| della conferma via email dal 29/09/2026 (v0.30.0).
*/

return [
    'occhiello' => 'Iscrizioni',
    'titolo' => 'Diventa socio',

    'perche' => [
        'occhiello' => 'Vantaggi',
        'titolo' => 'Perché associarsi',
        'voci' => [
            ['Tiro alla targa e percorso 3D', 'al campo «Franco Cortignani» di Castel Viscardo, e tiro indoor nella stagione fredda.'],
            ['Corsi con istruttore', 'per adulti, ragazzi e bambini, dai primi tiri al perfezionamento.'],
            ['Gare', 'partecipazione ai circuiti di gara regionali e nazionali.'],
            ['Tessera CSEN e assicurazione', 'il tesseramento include la copertura infortuni base.'],
            ['Una compagnia, non solo un campo', 'agonismo e vita associativa, incontri, appuntamenti.'],
        ],
    ],

    'come' => [
        'occhiello' => 'Procedura',
        'titolo' => 'Come iscriversi',
        'passi' => [
            ['Compila la domanda', 'dati, codice fiscale e documento. Per il minore deve essere presentata da un genitore.'],
            ['Invia la domanda', "controlla il riepilogo e conferma le dichiarazioni: l'invio vale come firma. Ricevi per email la ricevuta in PDF."],
            ['Il Consiglio decide', "entro 5 giorni dall'invio. Riceverai l'esito via email."],
            ['Versa la quota', "l'email conterrà i dati per effettuare un bonifico bancario. Con il versamento della quota diventi socio."],
        ],
    ],

    'faq' => [
        ['Quanto tempo ci vuole?', "Compilare richiede pochi minuti. Il Consiglio Direttivo decide entro 5 giorni dall'invio della domanda."],
        ['Quando divento socio?', "Quando versi la quota, dopo l'accettazione. Le istruzioni arrivano con l'email di accoglimento."],
        ['Cosa comprende la quota?', "Tesseramento CSEN e assicurazione infortuni base per l'anno sociale (gennaio–dicembre). Il rinnovo si versa entro il 31 gennaio."],
        ['Serve il certificato medico?', 'Il certificato medico agonistico è richiesto per partecipare alle gare ufficiali di calendario.'],
        ['Posso iscrivere un minorenne?', 'Sì: la domanda è presentata da un genitore, con i propri dati e il proprio documento. Il minore diventa socio giovane e, compiuti 18 anni, socio ordinario senza nuova domanda.'],
        // Terzo elemento facoltativo: riferimento normativo in piccolo (Paolo, 04/10/2026).
        ['Mio figlio ha almeno 14 anni: cosa cambia?', "Prima dell'invio serve anche la sua dichiarazione: deve essere d'accordo con l'iscrizione e il tesseramento, che altrimenti non può avvenire.", 'Art. 16, c. 2, D.Lgs. 36/2021.'],
        ['E se la domanda non è accolta?', "Il diniego è motivato e ti viene comunicato entro 10 giorni. Puoi fare appello all'Assemblea entro 30 giorni."],
        ['Come trattate i miei dati?', "Solo per la gestione dell'iscrizione, del tesseramento e dell'assicurazione. I dettagli sono nell'informativa privacy."],
    ],
];
