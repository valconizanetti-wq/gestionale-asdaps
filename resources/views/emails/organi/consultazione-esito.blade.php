{{-- Esito o ritiro della consultazione telematica (v0.44.0, CT-3). --}}
<x-mail::message>
<p class="occhiello">Consiglio Direttivo · consultazione telematica</p>

# {{ $c->stato === 'ritirata' ? 'Consultazione ritirata' : \App\Models\Consultazione::ESITI[$c->esito][0] }}

Ciao {{ $saluto }},

@if ($c->stato === 'ritirata')
la consultazione «{{ $c->oggetto }}» è stata ritirata prima della chiusura: i voti non contano più.

**Motivo**<br>{{ $c->motivo_ritiro }}
@else
la consultazione «{{ $c->oggetto }}» si è chiusa il {{ $c->chiusa_il->locale('it')->isoFormat('D MMMM YYYY [alle] HH:mm') }}.

<x-mail::panel>
{{ $c->esito_testo }}
</x-mail::panel>

Il verbale entra nel libro del Consiglio Direttivo.
@endif

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
