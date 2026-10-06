@extends('layouts.app')

@section('title', 'Nuovo movimento')

@section('content')
    <x-page-header titolo="Nuovo" evidenza="movimento" sottotitolo="Prima Nota" :indietro="route('prima-nota.index')" indietro-label="Prima Nota" />

    <form method="POST" action="{{ route('prima-nota.store') }}" enctype="multipart/form-data" class="form-pagina" novalidate
          x-data="{ inviato: false }" x-on:submit="inviato = true">
        @csrf
        {{-- Il valore non sta sul pulsante: un pulsante disattivato all'invio non lo manderebbe --}}
        <input type="hidden" name="salva_altro" value="0" x-ref="altro">
        @include('prima-nota._form')
        <div class="form-actions">
            <a href="{{ route('prima-nota.index') }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-secondary" x-on:click="$refs.altro.value = 1" x-bind:disabled="inviato">Salva e inserisci un altro</button>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Salva</button>
        </div>
    </form>
@endsection
