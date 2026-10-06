{{--
    Link per annullare una domanda che non si può più ritirare: già decisa,
    accolta con il termine per la quota passato (Paolo, 30/09/2026), o
    iscritta nel libro soci.
--}}
@extends('layouts.pubblico')
@php
    $accolta = $domanda->socio_id || $domanda->stato === 'approvata';
@endphp
@section('title', $accolta ? 'Iscrizione non annullabile' : 'Domanda già decisa')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona"><x-icona nome="info" /></div>
    @if ($domanda->socio_id)
        <x-page-header titolo="Iscrizione non" evidenza="annullabile" />
        <p class="testo-secondario">La domanda è stata accolta e l'iscrizione nel libro dei soci è già avvenuta: non è possibile annullarla. Se non vuoi più essere socio, puoi recedere scrivendo al Consiglio Direttivo, anche per email (statuto, art. 9).</p>
    @elseif ($accolta)
        <x-page-header titolo="Iscrizione non" evidenza="annullabile" />
        <p class="testo-secondario">La domanda è stata accolta e il termine per ritirarla è passato il {{ $domanda->quota_scadenza?->format('d/m/Y') }}: non è più possibile annullare l'iscrizione. Se non versi la quota, la domanda decade.</p>
    @else
        <x-page-header titolo="Domanda già" evidenza="decisa" />
        <p class="testo-secondario">Questa domanda non si può più ritirare: il Consiglio Direttivo ha già deciso e l'esito è stato comunicato per email.</p>
    @endif
    <p class="testo-secondario">Per qualsiasi dubbio scrivi a <a href="mailto:{{ config('sito.piede.email') }}">{{ config('sito.piede.email') }}</a>.</p>
    <x-card>
        <dl class="dati-griglia">
            <x-dato label="Stato"><x-badge-stato dominio="domanda" :valore="$domanda->socio_id ? 'iscritta' : $domanda->stato" /></x-dato>
        </dl>
    </x-card>
</div>
@endsection
