@extends('layouts.app')

@section('title', "Modifica {$socio->cognome} {$socio->nome}")

@section('content')
    <x-page-header :titolo="$socio->cognome" :evidenza="$socio->nome" sottotitolo="Modifica dei dati"
                   :indietro="route('soci.show', $socio)" indietro-label="Scheda socio" />

    <form method="POST" action="{{ route('soci.update', $socio) }}" class="form-pagina" novalidate>
        @csrf
        @method('PUT')
        @include('soci._form')
        <div class="form-actions">
            <a href="{{ route('soci.show', $socio) }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-primary">Salva modifiche</button>
        </div>
    </form>
@endsection
