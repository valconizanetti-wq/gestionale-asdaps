{{-- Votazione chiusa: esito ai consiglieri (tavola "Email — esito della votazione ai consiglieri"). --}}
@php
    $chiusa = [
        'scadenza' => 'alla scadenza, il ' . $domanda->votazione_chiusa_il?->locale('it')->isoFormat('D MMMM YYYY [alle] HH:mm'),
        'tutti' => 'il ' . $domanda->votazione_chiusa_il?->locale('it')->isoFormat('D MMMM YYYY') . ', quando hanno votato tutti',
    ][$domanda->votazione_chiusura] ?? '';
    $esito = match ($domanda->stato) {
        'approvata' => $domanda->silenzio_assenso ? "Accolta d'ufficio: ha votato meno della maggioranza del Consiglio" : 'Accolta',
        'da_rifiutare', 'rifiutata' => 'Non accolta',
        'in_parita' => 'Voti pari: decide il Presidente',
        default => ucfirst(str_replace('_', ' ', $domanda->stato)),
    };
    $etichetta = fn ($v) => ['favorevole' => 'Favorevole', 'contrario' => 'Contrario'][$v->voto] ?? 'Non ha votato';
@endphp
<x-mail::message>
<p class="occhiello">Consiglio Direttivo</p>

# Votazione chiusa

Ciao {{ $saluto }},

la votazione sulla domanda di adesione di **{{ $domanda->nomeCognome() }}** si è chiusa {{ $chiusa }}.

<x-mail::panel>
**Esito**<br>{{ $esito }}

**Voti**<br>{{ $domanda->riepilogoVoti() }}
</x-mail::panel>

<x-mail::table>
| Consigliere | Voto |
|:------------|:-----|
@foreach ($domanda->voti as $voto)
| {{ $voto->consigliere_nome }}{{ $voto->presidente ? ' (Presidente)' : '' }} | {{ $etichetta($voto) }} |
@endforeach
</x-mail::table>

@if ($alPresidente)
**Serve il tuo intervento**: {{ $domanda->isInParita() ? 'i voti sono pari e non hai votato: decidi tu se accogliere la domanda (statuto, art. 16.4).' : 'scrivi la motivazione del diniego e inviala al richiedente entro il ' . $domanda->scadenzaDiniego()?->format('d/m/Y') . ' (10 giorni, statuto art. 6.5).' }}

<x-mail::button :url="$url">
Apri la domanda
</x-mail::button>

@endif
Le motivazioni dei voti contrari sono nella bozza di verbale del Consiglio.

La segreteria<br>
{{ \App\Services\ProfiloEnte::denominazione() }}
</x-mail::message>
