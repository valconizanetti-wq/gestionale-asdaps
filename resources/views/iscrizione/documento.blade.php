{{--
    Informativa o statuto, dal Markdown versionato in resources/content (Documenti::html).
    Con una domanda in corso ($ritorno): link per tornare alla domanda, in
    alto e in fondo (su telefono i testi si aprono qui: collaudo A8).
--}}
@extends('layouts.pubblico')
@section('title', $titolo)
@section('content')
@if ($ritorno ?? null)
    <a href="{{ $ritorno }}" class="documento-ritorno"><x-icona nome="indietro" width="14" height="14" /> Torna alla domanda</a>
@endif
<x-page-header :titolo="$titolo" :sottotitolo="'Versione ' . $versione" />
<x-card>
    <article class="documento-testo">{!! $html !!}</article>
</x-card>
@if ($ritorno ?? null)
    <a href="{{ $ritorno }}" class="btn btn-primary documento-ritorno-pulsante">Torna alla domanda</a>
@endif
@endsection
