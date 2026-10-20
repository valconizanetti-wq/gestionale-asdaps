{{-- Link personale della consultazione telematica e sollecito (v0.44.0, CT-2). --}}
<x-mail::message>
<p class="occhiello">Consiglio Direttivo · consultazione telematica</p>

# {{ $sollecito ? 'La consultazione è ancora aperta' : $c->oggetto }}

Ciao {{ $saluto }},

@if ($sollecito)
il tuo voto sulla proposta «{{ $c->oggetto }}» non risulta ancora registrato.
@else
il Presidente chiede al Consiglio Direttivo di deliberare per via telematica sulla proposta qui sotto (statuto, art. 16.4).
@endif

<x-mail::panel>
**Termine per il voto**<br>{{ $c->termine->locale('it')->isoFormat('dddd D MMMM YYYY, [ore] HH:mm') }}
</x-mail::panel>

Dalla pagina leggi il testo e i documenti e voti: favorevole, contrario, astenuto, oppure chiedi di discuterne in riunione.

<x-mail::button :url="$url">
Apri la pagina della consultazione
</x-mail::button>

Il link è personale: non inoltrarlo.

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}

<p class="indirizzo">Indirizzo della pagina: {{ $url }}</p>
</x-mail::message>
