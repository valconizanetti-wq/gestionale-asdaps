{{--
    Troppe richieste dallo stesso indirizzo (limiti in AppServiceProvider).
    Oggi i limiti ci sono solo sulle pagine pubbliche, quindi il layout è
    quello del sito. Retry-After: secondi da attendere, se Laravel li dà.
--}}
@php
    $secondi = (int) ($exception->getHeaders()['Retry-After'] ?? 60);
    $attesa = $secondi <= 90 ? 'un minuto' : 'qualche minuto';
@endphp
@extends('layouts.pubblico')
@section('title', 'Troppe richieste')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona warn"><x-icona nome="avviso" /></div>
    <x-page-header titolo="Un attimo" evidenza="di pazienza" />
    <p class="testo-secondario">Sono arrivate molte richieste in poco tempo da questa connessione. Attendi {{ $attesa }} e riprova: i dati già inseriti non si perdono se torni indietro con il browser.</p>
    <a href="{{ route('iscrizione.dati') }}" class="btn btn-secondary">Torna al modulo</a>
</div>
@endsection
