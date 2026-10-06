{{-- Iscrizioni chiuse al pubblico ma utente collegato all'area riservata: può provare il modulo, la domanda nasce di prova (profilo-ente.md, v0.32.0). --}}
@if ($prova ?? false)
    <x-avviso tipo="warn" titolo="Iscrizioni chiuse al pubblico.">Stai provando il modulo come utente dell'area riservata: la domanda che invii sarà di prova e non entrerà nel libro soci.</x-avviso>
@endif
