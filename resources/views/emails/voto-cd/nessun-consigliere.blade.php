{{-- Alla presentazione di una domanda non c'è nessun consigliere in carica: votazione non aperta. --}}
<x-mail::message>
<p class="occhiello">Consiglio Direttivo</p>

# Votazione non aperta

È arrivata la domanda di adesione di **{{ $domanda->nomeCognome() }}**, ma nel gestionale non risulta nessun consigliere in carica: nessuno ha ricevuto il link per votare.

Inserisci i membri del Consiglio Direttivo nelle schede dei soci (carica in corso). La votazione si aprirà da sola entro un'ora e i consiglieri riceveranno il link. Finché non si apre, la domanda non viene né accolta né respinta alla scadenza.

<x-mail::button :url="$url">
Apri la domanda
</x-mail::button>

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
