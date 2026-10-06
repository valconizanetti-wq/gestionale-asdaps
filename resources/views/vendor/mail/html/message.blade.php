{{--
    Struttura di tutte le email (tema "sito"): intestazione del sito,
    corpo, piè di pagina con i dati dell'associazione. Nessun riferimento
    al "gestionale": chi riceve deve riconoscere la Compagnia
    (domande-iscrizione.md §5, email e filtri antispam).
--}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('sito.url', config('app.url'))" />
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ \App\Services\ProfiloEnte::denominazione() }} · C.F. {{ config('sito.piede.codice_fiscale') }}<br>
{{ config('sito.piede.sede') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
