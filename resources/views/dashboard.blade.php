@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    @php
        $utente = auth()->user();
        // Scadenze dell'associazione (v0.38.0, scadenzario.md): avviso
        // sull'affiliazione e scadenze entro il preavviso o già passate.
        $vedeScadenze = $utente->puoAccedere('scadenzario');
        $avvisoAffiliazione = $vedeScadenze ? \App\Services\Scadenzario::avvisoAffiliazione() : null;
        $vicine = $vedeScadenze
            ? \App\Models\Scadenza::aperte()->orderBy('data_scadenza')->get()->filter(fn ($s) => $s->stato()['stato'] !== 'lontana')
            : collect();
    @endphp

    <div class="page-header">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-sub">Benvenuto, {{ $utente->name }}</p>
        </div>
    </div>

    @if ($avvisoAffiliazione)
        <div @class(['sc-avviso', 'sc-avviso-' . $avvisoAffiliazione['tipo']]) role="alert">
            <div>
                <strong>{{ $avvisoAffiliazione['titolo'] }}</strong>
                <span>{{ $avvisoAffiliazione['testo'] }}</span>
            </div>
            <a href="{{ route('scadenzario.index') }}" class="btn btn-primary">Vai alla scadenza</a>
        </div>
    @endif

    @if ($vicine->isNotEmpty())
        <x-card titolo="Scadenze dell'associazione" senza-padding>
            <x-slot:azioni><a href="{{ route('scadenzario.index') }}" class="btn btn-ghost btn-sm">Scadenzario</a></x-slot:azioni>
            <ul class="pr-storico">
                @foreach ($vicine as $s)
                    @php $st = $s->stato(); @endphp
                    <li>
                        <span><a href="{{ route('scadenzario.edit', $s) }}">{{ $s->titolo }}</a> <span class="td-sub">{{ $s->data_scadenza->format('d/m/Y') }}</span></span>
                        <x-badge :variante="$st['variante']">{{ $st['testo'] }}</x-badge>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    {{-- Provvisoria: si riempirà con i riepiloghi dei moduli man mano che
         vengono costruiti (quote da sollecitare, anomalie). --}}
    <div class="card">
        <div class="empty-state">
            I moduli compariranno nel menu a sinistra man mano che vengono attivati.
        </div>
    </div>
@endsection
