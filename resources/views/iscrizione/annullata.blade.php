@extends('layouts.pubblico')
@section('title', 'Domanda annullata')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona"><x-icona nome="ok" /></div>
    <x-page-header titolo="Domanda" evidenza="annullata" />
    <p class="testo-secondario">La domanda è stata annullata e i dati inseriti sono stati cancellati.</p>
    <a href="{{ route('iscrizione.dati') }}" class="btn btn-secondary">Presenta una nuova domanda</a>
</div>
@endsection
