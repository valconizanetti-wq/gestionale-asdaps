{{-- Link per scegliere la password (v0.40.0, tavole UT-3, UT-3b). --}}
<x-mail::message>
<p class="occhiello">Gestionale · accesso</p>

# Scegli la tua password

Ciao {{ explode(' ', trim($nome))[0] }},

con il pulsante qui sotto scegli la password per entrare nel gestionale della {{ \App\Services\ProfiloEnte::denominazione() }}.

<x-mail::button :url="$url">
Scegli la password
</x-mail::button>

Il link vale **{{ $ore }} ore** e si usa una volta sola. Se non l'hai chiesto tu, ignora questa email: la password attuale resta valida.

<p class="indirizzo">Indirizzo della pagina: {{ $url }}</p>
</x-mail::message>
