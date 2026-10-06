{{-- Domanda presentata (tavola "Email — domanda ricevuta"; testo di Paolo del 30/09/2026). Testo senza urgenze né minacce: vedi domande-iscrizione.md §5. --}}
<x-mail::message>
<p class="occhiello">Domanda di adesione</p>

# Domanda ricevuta

Ciao {{ $saluto }},

abbiamo ricevuto la domanda di adesione {{ $perMinore ? 'per ' . $domanda->nomeCognome() : 'a tuo nome' }}, inviata dal sito della Compagnia. Come prevede lo statuto (art. 6), l'abbiamo trasmessa al Consiglio Direttivo.
Riceverai l'esito per email. Se la domanda è accolta, troverai anche come versare la quota.

{{ $conRicevuta ? 'In allegato la ricevuta in PDF, con i dati e le dichiarazioni.' : 'La ricevuta in PDF, con i dati e le dichiarazioni, ti sarà inviata a parte.' }}

Se non hai inviato tu questa domanda, o hai cambiato idea, puoi [annullarla]({{ $urlAnnulla }}).

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
