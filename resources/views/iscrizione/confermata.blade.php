{{-- Domanda confermata (tavola "Iscrizione 4"). --}}
@extends('layouts.pubblico')
@section('title', 'Domanda confermata')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato">
    <div class="esito-icona ok"><x-icona nome="ok" /></div>
    <x-page-header titolo="Domanda" evidenza="confermata" />
    <p class="testo-secondario">Grazie. La domanda {{ $domanda->tipo_socio === 'minorenne' ? 'per ' . $domanda->nome . ' ' . $domanda->cognome : 'di adesione' }} è arrivata al Consiglio Direttivo.</p>
    <x-card>
        <dl class="dati-griglia">
            <x-dato label="Decisione del Consiglio entro" :valore="$domanda->scadenza_cd?->locale('it')->isoFormat('D MMMM YYYY')" />
            <x-dato label="Ricevuta" :valore="$domanda->ricevuta_path ? 'Inviata per email in PDF' : 'Ti sarà inviata per email'" />
        </dl>
    </x-card>
    <x-avviso tipo="info" titolo="E adesso?">Riceverai l'esito per email. Se il Consiglio non si esprime entro la data indicata, la domanda è accolta. Dopo l'accoglimento troverai come versare la quota.</x-avviso>
</div>
@endsection
