{{-- Link personale di voto (tavola "Email — invito al voto e sollecito"). --}}
<x-mail::message>
<p class="occhiello">Consiglio Direttivo</p>

# {{ $sollecito ? 'La votazione è ancora aperta' : 'Nuova domanda di adesione' }}

Ciao {{ $saluto }},

@if ($sollecito)
il tuo voto sulla domanda qui sotto non risulta ancora registrato. La votazione resta aperta fino al {{ $domanda->scadenza_cd?->locale('it')->isoFormat('D MMMM') }}.
@else
è arrivata una domanda di adesione su cui il Consiglio Direttivo è chiamato a votare.
@endif

<x-mail::panel>
**Richiedente**<br>{{ $domanda->nomeCognome() }}, {{ $domanda->tipo_socio === 'minorenne' ? 'socio giovane' : 'socio ordinario' }}

**{{ $domanda->sesso === 'F' ? 'Nata' : 'Nato' }}**<br>{{ $domanda->data_nascita?->format('d/m/Y') }} a {{ $domanda->luogo_nascita }}

**Residente a**<br>{{ $domanda->citta }} ({{ $domanda->provincia }})

**Termine per il voto**<br>{{ $domanda->scadenza_cd?->locale('it')->isoFormat('D MMMM YYYY, [ore] HH:mm') }}
</x-mail::panel>

<x-mail::button :url="$url">
Apri la pagina di voto
</x-mail::button>

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}

<p class="indirizzo">Indirizzo della pagina di voto: {{ $url }}</p>
</x-mail::message>
