{{-- Voto registrato (tavola "Voto 2"). Si vede anche riaprendo il link dopo aver votato. --}}
@extends('layouts.pubblico')
@section('title', 'Voto registrato')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato voto-pagina">
    <div class="esito-icona ok"><x-icona nome="ok" /></div>
    @include('voto-cd._intestazione', ['titolo' => 'Voto registrato'])
    <p class="testo-secondario">Grazie {{ $voto->nomeProprio() }}. Il voto sulla domanda di <strong>{{ $domanda->nomeCognome() }}</strong> è registrato.</p>

    <section class="voto-scheda">
        <dl class="voto-dati">
            <x-dato label="Il tuo voto">
                <x-badge :variante="$voto->voto === 'favorevole' ? 'ok' : 'ko'">{{ $voto->voto === 'favorevole' ? 'Favorevole' : 'Contrario' }}</x-badge>
            </x-dato>
            @if ($voto->voto === 'contrario')
                <x-dato label="Motivazione" :valore="$voto->motivo_rifiuto" />
            @endif
            <x-dato label="Registrato il" :valore="$voto->votato_il->locale('it')->isoFormat('D MMMM YYYY, HH:mm')" />
        </dl>
    </section>

    @if ($domanda->isInAttesa())
        <x-avviso tipo="info"><strong>La votazione è aperta</strong> fino a {{ $domanda->scadenza_cd->locale('it')->isoFormat('dddd D MMMM, [ore] HH:mm') }}. Alla chiusura riceverai l'esito per email.</x-avviso>
    @else
        <x-avviso tipo="info"><strong>La votazione è chiusa.</strong> L'esito ti è stato inviato per email.</x-avviso>
    @endif

    <p class="testo-secondario">Puoi chiudere questa pagina. Se riapri il link vedrai di nuovo questo riepilogo.</p>
</div>
@endsection
