{{-- Link annullato da uno più recente, inesistente o di un socio non più attivo (tavola "Link privacy 2", secondo stato). --}}
@extends('layouts.pubblico')
@section('title', 'Link non valido')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato voto-pagina">
    <div class="esito-icona"><x-icona nome="lucchetto" /></div>
    <div class="iscr-titolo">
        <span class="iscr-occhiello">Informativa privacy</span>
        <h1>Link non valido</h1>
    </div>
    <p class="testo-secondario">Il link non è più valido: forse ti abbiamo mandato un'email più recente, che lo sostituisce. Cerca l'ultima email «Informativa privacy» della Compagnia o scrivi alla segreteria.</p>
    @if (config('sito.piede.email'))
        <a href="mailto:{{ config('sito.piede.email') }}" class="btn btn-primary">Scrivi alla segreteria</a>
    @endif
    <a href="{{ route('informativa') }}">Leggi l'informativa</a>
</div>
@endsection
