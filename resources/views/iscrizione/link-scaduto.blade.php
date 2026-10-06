{{-- Link di conferma oltre le 48 ore: la domanda non vale e va ripresentata. --}}
@extends('layouts.pubblico')
@section('title', 'Link scaduto')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona warn"><x-icona nome="avviso" /></div>
    <x-page-header titolo="Link" evidenza="scaduto" />
    <p class="testo-secondario">Sono passate più di 48 ore dall'invio: la domanda non è stata confermata e i suoi dati saranno cancellati. Puoi presentarne una nuova.</p>
    <a href="{{ route('iscrizione.dati') }}" class="btn btn-primary">Presenta una nuova domanda</a>
</div>
@endsection
