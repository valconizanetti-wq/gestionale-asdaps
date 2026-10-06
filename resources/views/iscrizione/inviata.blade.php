{{-- Domanda presentata (tavola "Iscrizione — domanda inviata"): vale con l'invio e va subito al Consiglio. --}}
@extends('layouts.pubblico')
@section('title', 'Domanda inviata')
@section('stretto', '1')
@section('content')
@php
    $presentata = \Illuminate\Support\Carbon::parse($d['presentata'])->timezone(config('app.timezone'));
    $scadenza = $d['scadenza'] ? \Illuminate\Support\Carbon::parse($d['scadenza'])->timezone(config('app.timezone')) : null;
@endphp
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona ok"><x-icona nome="ok" /></div>
    <x-page-header titolo="Domanda" evidenza="inviata" />
    <p class="testo-secondario">Grazie {{ $d['saluto'] }}. La domanda è stata trasmessa al Consiglio Direttivo, come prevede lo statuto (art. 6).</p>
    <x-card>
        <dl class="dati-griglia">
            <x-dato label="Presentata il" :valore="$presentata->locale('it')->isoFormat('D MMMM YYYY, HH:mm')" />
            <x-dato label="Decisione del Consiglio entro" :valore="$scadenza?->locale('it')->isoFormat('D MMMM YYYY')" />
            <x-dato label="Ricevuta" :valore="'Inviata a ' . $d['email'] . ', in PDF'" />
        </dl>
    </x-card>
    <x-avviso tipo="info" titolo="E adesso?">Riceverai l'esito per email. Se la domanda è accolta, troverai anche come versare la quota.</x-avviso>
</div>
@endsection
