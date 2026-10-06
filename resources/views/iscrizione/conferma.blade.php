{{--
    Pagina del link di conferma. La conferma avviene solo con il pulsante
    (POST): i filtri antispam aprono da soli i link delle email.
--}}
@extends('layouts.pubblico')
@section('title', 'Conferma la domanda')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona"><x-icona nome="mail" /></div>
    <x-page-header titolo="Conferma la" evidenza="domanda" />
    <p class="testo-secondario">
        Domanda di adesione {{ $domanda->tipo_socio === 'minorenne' ? 'per ' . $domanda->nome . ' ' . $domanda->cognome : 'di ' . $domanda->nome . ' ' . $domanda->cognome }}.
        Con la conferma la domanda è valida e passa al Consiglio Direttivo.
    </p>
    <form method="POST" action="{{ route('iscrizione.conferma.esegui', $token) }}" x-data="{ inviato: false }" x-on:submit="inviato = true">
        @csrf
        <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Conferma la domanda</button>
    </form>
    <p class="testo-secondario">Non l'hai presentata tu? <a href="{{ route('iscrizione.annulla', $token) }}">Annulla la domanda</a></p>
</div>
@endsection
