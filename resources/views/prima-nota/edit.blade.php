@extends('layouts.app')

@section('title', "Movimento n. {$m->id}")

@section('content')
    <x-page-header titolo="Modifica" :evidenza="'movimento n. ' . $m->id" :sottotitolo="'Prima Nota ' . $m->annoMovimento()"
                   :indietro="route('prima-nota.show', $m)" indietro-label="Movimento" />

    @if ($m->rinnovo_id)
        <x-avviso tipo="info" titolo="Quota di un socio.">
            Il movimento è collegato alla quota {{ $m->rinnovo?->anno }}: se cambi importo o data, la quota non si aggiorna da sola.
        </x-avviso>
    @endif

    <form method="POST" action="{{ route('prima-nota.update', $m) }}" enctype="multipart/form-data" class="form-pagina" novalidate
          x-data="{ inviato: false }" x-on:submit="inviato = true">
        @csrf
        @method('PUT')
        @include('prima-nota._form')
        <div class="form-actions">
            <a href="{{ route('prima-nota.show', $m) }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">{{ $chiuso ? 'Salva la correzione' : 'Salva' }}</button>
        </div>
    </form>
@endsection
