{{-- Ricevuta della quota associativa in allegato (rinnovi-quota.md §3). Al genitore per i soci minorenni. --}}
<x-mail::message>
<p class="occhiello">Quota associativa {{ $r->anno }}</p>

# {{ $annullata ? 'Ricevuta annullata' : 'Quota ricevuta, grazie' }}

Ciao {{ $saluto }},

@if ($annullata)
la ricevuta n. {{ $r->numero_ricevuta }} della quota associativa {{ $r->anno }}{{ $perGenitore ? ' di ' . $r->socio->nome : '' }} è stata annullata. Motivo: {{ $r->annullamento_motivo }}

La trovi in allegato con il timbro «annullata». Se serve, ti arriverà la ricevuta corretta.
@else
abbiamo ricevuto la quota associativa {{ $r->anno }}{{ $perGenitore ? ' di ' . $r->socio->nome : '' }}: in allegato la ricevuta n. {{ $r->numero_ricevuta }}.

<x-mail::panel>
**Importo**<br>{{ \App\Support\Importo::it($r->importo, true) }}

**Quota valida fino al**<br>31/12/{{ $r->anno }}
</x-mail::panel>
@endif

Per qualsiasi dubbio rispondi a questa email.

{{ $firma }}<br>
{{ \App\Services\ProfiloEnte::denominazione($r->data_pagamento) }}
</x-mail::message>
