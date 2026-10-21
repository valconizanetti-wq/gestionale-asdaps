{{-- Cambio dell'email per entrare (v0.44.1, analisi-soci-utenti.md §2.3). --}}
<x-mail::message>
<p class="occhiello">Gestionale · accesso</p>

# È cambiata l'email per entrare

Ciao {{ explode(' ', trim($nome))[0] }},

@if ($vecchio)
nella tua scheda di socio della {{ \App\Services\ProfiloEnte::denominazione() }} l'email è stata cambiata in **{{ $nuovaMascherata }}**@if ($da), da {{ $da }}@endif. Da adesso **questo indirizzo non serve più per entrare** nel gestionale: si entra con quello nuovo, con la stessa password.
@else
nella tua scheda di socio della {{ \App\Services\ProfiloEnte::denominazione() }} c'è questa email@if ($da) (l'ha scritta {{ $da }})@endif. Da adesso è **l'email per entrare** nel gestionale, con la password di prima.
@endif

<x-mail::button :url="$url">
Apri il gestionale
</x-mail::button>

Se il cambio non l'hai chiesto tu, rispondi a questa email o avvisa il Presidente o il Segretario.
</x-mail::message>
