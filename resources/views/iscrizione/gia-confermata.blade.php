{{-- Link di una domanda già confermata (riaperto, o pulsante premuto due volte). --}}
@extends('layouts.pubblico')
@section('title', 'Domanda già confermata')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona ok"><x-icona nome="ok" /></div>
    <x-page-header titolo="Domanda già" evidenza="confermata" />
    <p class="testo-secondario">Questa domanda è già stata confermata: non serve fare altro. L'esito arriverà per email.</p>
    <x-card>
        <dl class="dati-griglia">
            <x-dato label="Stato"><x-badge-stato dominio="domanda" :valore="$domanda->stato" /></x-dato>
        </dl>
    </x-card>
</div>
@endsection
