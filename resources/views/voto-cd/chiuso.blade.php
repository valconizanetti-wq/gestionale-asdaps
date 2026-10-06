{{-- Votazione chiusa prima del voto, o link non valido (tavola "Voto 3"). --}}
@extends('layouts.pubblico')
@section('title', $voto ? 'Votazione chiusa' : 'Link non valido')
@section('stretto', '1')
@section('content')
@php
    $esito = $voto ? match (true) {
        (bool) $domanda->socio_id, $domanda->isApprovata(), $domanda->isDecaduta() => ['Accolta', 'ok'],
        $domanda->isRifiutata(), $domanda->isDaRifiutare() => ['Non accolta', 'ko'],
        $domanda->isInParita() => ['Decide il Presidente', 'warn'],
        default => ['In chiusura', 'neutral'],
    } : null;
@endphp
<div class="pubblico-colonna pubblico-centrato voto-pagina">
    <div class="esito-icona"><x-icona nome="lucchetto" /></div>
    @include('voto-cd._intestazione', ['titolo' => $voto ? 'Votazione chiusa' : 'Link non valido'])

    @if ($voto)
        <p class="testo-secondario">La votazione sulla domanda di <strong>{{ $domanda->nomeCognome() }}</strong> si è chiusa prima del tuo voto, che quindi non è stato registrato.</p>
        <section class="voto-scheda">
            <dl class="voto-dati">
                <x-dato label="Esito"><x-badge :variante="$esito[1]">{{ $esito[0] }}</x-badge></x-dato>
                <x-dato label="Chiusa il" :valore="$domanda->votazione_chiusa_il?->locale('it')->isoFormat('D MMMM YYYY, HH:mm')" />
            </dl>
        </section>
    @else
        <p class="testo-secondario">Il link non corrisponde a nessuna votazione. Controlla di aver aperto il collegamento completo dall'email; se il problema resta, scrivi alla segreteria.</p>
    @endif

    @if (config('sito.piede.email'))
        <a href="mailto:{{ config('sito.piede.email') }}" class="btn btn-secondary">Scrivi alla segreteria</a>
    @endif
</div>
@endsection
