{{--
    Dopo «Registra» e «I miei scontrini» (tavola SN-4): chi carica vede i
    suoi scontrini in sola lettura, con lo stato della verifica.
--}}
@extends('layouts.app')

@section('title', 'I miei scontrini')

@use('App\Support\Importo')

@section('content')
    @php $registrato = session('registrato') ? \App\Models\PrimaNotaScontrino::with('soggetto')->find(session('registrato')) : null; @endphp

    @if ($registrato)
        <x-avviso tipo="ok" titolo="Scontrino n. {{ $registrato->numero }} registrato.">
            {{ Importo::it($registrato->importo_totale, true) }} · {{ $registrato->fornitoreNome() }} · {{ strtolower($registrato->contoLabel()) }}.
            È in Prima Nota su «Uscite da classificare»: lo verifica il Tesoriere.
        </x-avviso>
    @endif

    @if ($puoCaricare)
        <a href="{{ route('scontrini.create') }}" class="btn btn-primary sn-pulsante sn-altro">
            <x-icona nome="fotocamera" width="16" height="16" /> {{ $registrato ? 'Registra un altro scontrino' : 'Nuovo scontrino' }}
        </a>
    @endif

    <x-page-header titolo="I miei" evidenza="scontrini" sottotitolo="Li vedi, non li modifichi: per una correzione scrivi al Tesoriere." />

    @if ($scontrini->isEmpty())
        <x-vuoto>Non hai ancora caricato scontrini.</x-vuoto>
    @else
        <ul class="sn-miei">
            @foreach ($scontrini as $s)
                <li>
                    <a href="{{ route('scontrini.show', $s) }}" class="sn-mio">
                        @if ($s->miniatura_path)
                            <img src="{{ route('scontrini.anteprima', [$s, 'mini' => 1]) }}" alt="" class="sn-mio-foto" loading="lazy">
                        @else
                            <span class="sn-mio-foto"><x-icona nome="scontrino" /></span>
                        @endif
                        <span class="sn-mio-testo">
                            <strong>{{ $s->fornitoreNome() ?? 'Fornitore da scrivere' }}</strong>
                            <span class="td-sub">n. {{ $s->numero }} · {{ $s->data_scontrino->format('d/m/Y') }}</span>
                        </span>
                        <span class="sn-mio-importo">
                            <strong>{{ Importo::it($s->importo_totale, true) }}</strong>
                            <x-badge :variante="$s->statoVariante()">{{ $s->statoTesto() }}</x-badge>
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
