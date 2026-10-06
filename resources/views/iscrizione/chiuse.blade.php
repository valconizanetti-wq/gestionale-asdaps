{{--
    Modulo pubblico con le iscrizioni chiuse (impostazione "iscrizioni_aperte"
    spenta; profilo-ente.md, tavola "Iscrizioni online chiuse"). La vede solo
    chi non è collegato all'area riservata: chi è collegato trova il modulo
    con l'avviso di prova (iscrizione/_prova).
--}}
@extends('layouts.pubblico')

@section('title', 'Iscrizioni non ancora aperte')
@section('stretto', '1')

@section('content')
@php $email = config('sito.piede.email'); @endphp
<div class="pubblico-colonna">
    <div class="iscr-titolo">
        <span class="iscr-occhiello">Iscrizioni</span>
        <h1>Iscrizioni online non ancora aperte</h1>
    </div>
    <x-card>
        <div class="chiuse-corpo">
            <p>Le domande di adesione online apriranno a breve. Per informazioni sull'iscrizione scrivici: ti risponderemo il prima possibile.</p>
            <a href="mailto:{{ $email }}" class="btn btn-primary">Scrivi a {{ $email }}</a>
        </div>
    </x-card>
</div>
@endsection
