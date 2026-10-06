{{-- Promemoria (promemoria.md): testo scritto nella pagina Promemoria, segnaposti già sostituiti.
     Il testo è dentro un blocco HTML: niente interpretazione Markdown dei caratteri scritti dall'utente. --}}
<x-mail::message>
<p class="occhiello">Promemoria</p>

<div class="testo-promemoria">{!! nl2br(e($testo), false) !!}</div>

<p class="indirizzo">{{ \App\Services\ProfiloEnte::denominazione() }}</p>
</x-mail::message>
