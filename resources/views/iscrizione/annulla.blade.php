{{-- Pagina del link per ritirare la domanda: si annulla solo con il pulsante (POST). --}}
@extends('layouts.pubblico')
@section('title', 'Annulla la domanda')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona warn"><x-icona nome="avviso" /></div>
    <x-page-header titolo="Annulla la" evidenza="domanda" />
    <p class="testo-secondario">
        Stai ritirando la domanda di adesione {{ $domanda->tipo_socio === 'minorenne' ? 'per ' . $domanda->nomeCognome() : 'di ' . $domanda->nomeCognome() }}.
        I dati inseriti vengono cancellati subito{{ $domanda->isInAttesa() ? ' e il Consiglio Direttivo ne viene informato' : '' }}.
    </p>
    <form method="POST" action="{{ route('iscrizione.annulla.esegui', $token) }}" x-data="{ inviato: false }" x-on:submit="inviato = true">
        @csrf
        <button type="submit" class="btn btn-danger" x-bind:disabled="inviato">Annulla la domanda</button>
    </form>
    @if ($domanda->isInVerifica())
        <p class="testo-secondario">Hai cambiato idea di nuovo? <a href="{{ route('iscrizione.conferma', $token) }}">Conferma la domanda</a></p>
    @endif
</div>
@endsection
