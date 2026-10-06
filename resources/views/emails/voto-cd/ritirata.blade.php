{{-- Ai consiglieri: domanda ritirata dal richiedente durante la votazione. --}}
<x-mail::message>
<p class="occhiello">Consiglio Direttivo</p>

# Domanda ritirata

Ciao {{ $saluto }},

{{ $richiedente }} ha ritirato la domanda di adesione: la votazione è chiusa e il link che hai ricevuto non vale più. Non serve fare altro.

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
