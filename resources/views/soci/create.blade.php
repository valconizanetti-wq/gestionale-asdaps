@extends('layouts.app')

@section('title', 'Nuovo socio')

@section('content')
    <x-page-header titolo="Nuovo" evidenza="socio" :indietro="route('soci.index')" indietro-label="Soci" />

    <form method="POST" action="{{ route('soci.store') }}" class="form-pagina" novalidate>
        @csrf
        @include('soci._form')
        <div class="form-actions">
            <a href="{{ route('soci.index') }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-primary">Salva socio</button>
        </div>
    </form>
@endsection
