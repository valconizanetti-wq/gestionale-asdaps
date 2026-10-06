{{-- Link di conferma o annullamento già usato, sbagliato o riferito a una domanda cancellata. --}}
@extends('layouts.pubblico')
@section('title', 'Link non valido')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona warn"><x-icona nome="avviso" /></div>
    <x-page-header titolo="Link non" evidenza="valido" />
    <p class="testo-secondario">Il link è già stato usato, oppure la domanda non è più in attesa di conferma. Se l'hai già confermata, riceverai l'esito per email.</p>
    <a href="{{ route('iscrizione.dati') }}" class="btn btn-secondary">Presenta una nuova domanda</a>
</div>
@endsection
