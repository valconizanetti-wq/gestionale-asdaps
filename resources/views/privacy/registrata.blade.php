{{-- Conferma registrata (tavola "Link privacy 2"). Si vede anche riaprendo il link dopo l'uso. --}}
@extends('layouts.pubblico')
@section('title', 'Informativa privacy: conferma registrata')
@section('stretto', '1')
@section('content')
<div class="pubblico-colonna pubblico-centrato voto-pagina">
    <div class="esito-icona ok"><x-icona nome="ok" /></div>
    <div class="iscr-titolo">
        <span class="iscr-occhiello">Informativa privacy</span>
        <h1>Grazie, è registrato</h1>
    </div>
    @if ($link->perTutore() && $socio)
        <p class="testo-secondario">Abbiamo registrato la tua conferma per <strong>{{ $socio->nome }} {{ $socio->cognome }}</strong>.</p>
    @else
        <p class="testo-secondario">Abbiamo registrato la tua conferma.</p>
    @endif

    <section class="voto-scheda">
        <dl class="voto-dati">
            <x-dato label="Informativa letta" :valore="'Versione ' . $link->informativa_versione" />
            <x-dato label="Foto e video">
                <x-badge :variante="$link->foto_video ? 'ok' : 'neutral'">{{ $link->foto_video ? 'Consenso dato' : 'Consenso non dato' }}</x-badge>
            </x-dato>
            <x-dato label="Registrato il" :valore="$link->usato_il->locale('it')->isoFormat('D MMMM YYYY, HH:mm')" />
        </dl>
    </section>

    @if (config('sito.piede.email'))
        <p class="testo-secondario">Per revocare il consenso a foto e video, o per gli altri diritti descritti nell'informativa, scrivi a <a href="mailto:{{ config('sito.piede.email') }}">{{ config('sito.piede.email') }}</a>.</p>
    @endif
    <p class="testo-secondario">Puoi chiudere questa pagina. Se riapri il link vedrai di nuovo questo riepilogo.</p>
</div>
@endsection
