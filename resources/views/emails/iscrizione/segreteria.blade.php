{{-- Avviso alla casella dell'associazione: solo il nominativo, il resto nel gestionale. --}}
<x-mail::message>
# Nuova domanda di adesione

È arrivata la domanda n. {{ $domanda->id }} di {{ $domanda->nomeCognome() }} ({{ $domanda->tipo_socio === 'minorenne' ? 'socio giovane' : 'socio ordinario' }}). È stata trasmessa ai consiglieri, che votano entro il {{ $domanda->scadenza_cd?->format('d/m/Y H:i') }}.

<x-mail::button :url="route('domande.show', $domanda)">
Apri la domanda
</x-mail::button>
</x-mail::message>
