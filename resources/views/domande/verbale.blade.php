{{-- Bozza di verbale della consultazione telematica (VerbaleAmmissione). Il testo è generato dal gestionale, con i dati escapati. --}}
@extends('layouts.app')

@section('title', 'Verbale — ' . $domanda->cognome . ' ' . $domanda->nome)

@section('content')
    <x-page-header titolo="Verbale del" evidenza="Consiglio" :sottotitolo="$verbale->titolo"
                   :indietro="route('domande.show', $domanda)" indietro-label="Domanda">
        <div class="badge-riga">
            <x-badge variante="neutral">{{ ucfirst($verbale->stato) }}</x-badge>
        </div>
    </x-page-header>

    @if ($verbale->stato === 'bozza')
        <x-avviso tipo="info" titolo="Bozza.">Il testo si aggiorna da solo fino all'approvazione (diniego inviato, decisione del Presidente). Va sottoscritto dal Presidente e dal Segretario e trascritto nel libro dei verbali del Consiglio (statuto, art. 16.6).</x-avviso>
    @endif

    <x-card>
        <div class="documento-testo">
            {!! $verbale->corpo_html !!}
        </div>
    </x-card>
@endsection
