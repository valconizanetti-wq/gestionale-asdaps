{{-- Diniego motivato (statuto art. 6.5, tavola "Email — diniego motivato"). --}}
<x-mail::message>
<p class="occhiello">Domanda di adesione</p>

# Esito della domanda

Gentile {{ $destinatario }},

il Consiglio Direttivo, con delibera del {{ ($domanda->decisione_presidente_il ?? $domanda->esito_il)?->format('d/m/Y') }}, non ha accolto la domanda di adesione {{ $perMinore ? 'per ' . $domanda->nomeCognome() : 'a tuo nome' }} presentata il {{ $domanda->created_at->format('d/m/Y') }}.

<x-mail::panel>
**Motivazione**<br>{!! nl2br(e($domanda->motivo_rifiuto)) !!}
</x-mail::panel>

**Puoi fare appello all'Assemblea dei soci** entro 30 giorni dal ricevimento di questa email, cioè entro il {{ $appelloEntro->format('d/m/Y') }}, rispondendo a questo messaggio. L'Assemblea decide nella prima riunione utile (statuto, art. 6.5).

I dati della domanda sono conservati per 12 mesi e poi cancellati; nel verbale del Consiglio restano nominativo, esito e motivazione (informativa privacy, §6).

Cordiali saluti,<br>
{{ $firma ? $firma . ', Presidente' : 'Il Presidente' }}<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
