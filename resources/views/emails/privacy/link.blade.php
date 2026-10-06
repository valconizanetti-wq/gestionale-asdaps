{{-- Link personale per l'informativa privacy (tavola "Email — link personale per l'informativa"; privacy-link.md §3). --}}
<x-mail::message>
<p class="occhiello">Informativa privacy</p>

# {{ $aggiornamento ? 'Informativa aggiornata' : 'Conferma la lettura' }}

Gentile {{ $nome }},

@if ($aggiornamento)
abbiamo aggiornato l'informativa sul trattamento dei dati personali (versione {{ $versione }}). Dalla pagina personale qui sotto puoi:
@elseif ($perTutore)
per tenere in ordine il libro soci ti chiediamo, come genitore di {{ $nomeSocio }}, un minuto per l'informativa sul trattamento dei dati personali. Dalla pagina personale qui sotto puoi:
@else
per tenere in ordine il libro soci ti chiediamo un minuto per l'informativa sul trattamento dei dati personali. Dalla pagina personale qui sotto puoi:
@endif

- confermare di aver letto l'informativa (obbligatorio per il libro soci);
- dare, se vuoi, il consenso a foto e video durante le attività (facoltativo).

<x-mail::button :url="$url">
Apri la pagina
</x-mail::button>

Il link è personale e vale una volta sola. Se ti arriva un'altra email come questa, vale solo la più recente.

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}

<p class="indirizzo">Indirizzo della pagina: {{ $url }}</p>
</x-mail::message>
