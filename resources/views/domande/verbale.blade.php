{{-- Bozza di verbale della consultazione telematica (VerbaleAmmissione). Il testo è generato dal gestionale, con i dati escapati. --}}
@extends('layouts.app')

@section('title', 'Verbale — ' . $domanda->cognome . ' ' . $domanda->nome)

@section('content')
    <x-page-header titolo="Verbale del" evidenza="Consiglio" :sottotitolo="$verbale->titolo"
                   :indietro="route('domande.show', $domanda)" indietro-label="Domanda">
        <div class="badge-riga">
            <x-badge :variante="$verbale->statoVariante()">{{ $verbale->statoTesto() }}</x-badge>
            @if ($verbale->codice())<x-badge variante="neutral">{{ $verbale->codice() }}</x-badge>@endif
        </div>
        @if (! $domanda->is_test && auth()->user()->puoAccedere('verbali'))
            <x-slot:azioni>
                <a href="{{ route('verbali.show', $verbale) }}" class="btn btn-secondary">Apri nel libro dei verbali</a>
            </x-slot:azioni>
        @endif
    </x-page-header>

    @if ($verbale->stato === 'bozza')
        <x-avviso tipo="info" titolo="Bozza.">Il testo si aggiorna da solo finché la domanda non è decisa (diniego inviato, decisione del Presidente). Poi il Segretario lo rende definitivo dal libro dei verbali del Consiglio, con il numero, e carica la copia firmata digitalmente dal Presidente e dal Segretario (statuto, art. 16.6).</x-avviso>
    @endif

    <x-card>
        <div class="documento-testo">
            {!! $verbale->corpo_html !!}
        </div>
    </x-card>
@endsection
