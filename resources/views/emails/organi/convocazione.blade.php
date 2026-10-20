{{-- Convocazione della riunione del CD, prova e rinvio (v0.44.0, tavola CD-5). --}}
<x-mail::message>
<p class="occhiello">Consiglio Direttivo</p>

@if ($prova)
<x-mail::panel>
**Prova.** Questa email è arrivata solo a te: i membri del Consiglio la riceveranno con «Invia la convocazione».
</x-mail::panel>
@endif

@if ($motivoRinvio !== null)
# La riunione è rinviata

Ciao {{ $saluto }},

la riunione del Consiglio Direttivo prevista per {{ $s->data->locale('it')->isoFormat('dddd D MMMM YYYY') }} alle {{ substr((string) $s->ora_inizio, 0, 5) }} **è rinviata**. Riceverai la nuova convocazione.

**Motivo**<br>{{ $motivoRinvio }}
@else
# {{ $s->urgente ? 'Convocazione urgente' : 'Convocazione' }}

Ciao {{ $saluto }},

il Consiglio Direttivo è convocato per:

<x-mail::panel>
**Quando**<br>{{ ucfirst($s->data->locale('it')->isoFormat('dddd D MMMM YYYY')) }}, ore {{ substr((string) $s->ora_inizio, 0, 5) }}

@if ($s->luogo)
**Dove**<br>{{ $s->luogo }}

@endif
@if ($s->videoconferenza)
**In videoconferenza**<br>{{ $s->collegamento ?: 'il collegamento arriverà a parte' }}

@endif
@if ($s->urgente)
**Urgente**<br>{{ $s->motivo_urgenza }}
@endif
</x-mail::panel>

**Ordine del giorno**

@foreach ($s->punti() as $i => $p)
{{ $i + 1 }}. {{ $p }}
@endforeach

@if ($documenti->isNotEmpty())
**Documenti** ({{ $documenti->count() }}): {{ $documenti->pluck('nome')->implode(', ') }}. Li trovi nella scheda della riunione, nel gestionale.

<x-mail::button :url="$url">
Apri la riunione nel gestionale
</x-mail::button>
@endif
@endif

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
