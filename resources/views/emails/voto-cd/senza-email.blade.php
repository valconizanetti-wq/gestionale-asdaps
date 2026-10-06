{{-- Al Presidente: consiglieri che non hanno ricevuto il link perché senza email. --}}
<x-mail::message>
<p class="occhiello">Consiglio Direttivo</p>

# Consiglieri senza email

È aperta la votazione sulla domanda di adesione di **{{ $domanda->nomeCognome() }}**. Questi consiglieri non hanno un indirizzo email nella scheda socio e non hanno ricevuto il link per votare:

@foreach ($nomi as $nome)
- {{ $nome }}
@endforeach

Contano nel quorum come non votanti. Per le prossime votazioni aggiungi l'indirizzo nella loro scheda socio.

<x-mail::button :url="$url">
Apri la domanda
</x-mail::button>

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
