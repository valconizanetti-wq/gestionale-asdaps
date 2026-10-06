{{-- Al Presidente: diniego ancora da inviare (art. 6.5). --}}
<x-mail::message>
<p class="occhiello">Consiglio Direttivo</p>

# Diniego da comunicare

Il Consiglio non ha accolto la domanda di adesione di **{{ $domanda->nomeCognome() }}** il {{ $domanda->esito_il?->format('d/m/Y') }}. La motivazione va comunicata al richiedente entro il **{{ $entro?->format('d/m/Y') }}** (statuto, art. 6.5): dalla scheda della domanda la scrivi e la invii.

<x-mail::button :url="$url">
Apri la domanda
</x-mail::button>

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
