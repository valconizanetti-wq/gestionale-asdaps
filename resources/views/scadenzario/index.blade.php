{{--
    Scadenze dell'associazione (scadenzario.md, tavola SC-1): ricorrenti
    ogni anno (affiliazione, polizza RC, rendiconto…) e una tantum, con
    stato, ultimo pagamento e preavviso. «Rinnovata» registra il pagamento
    in Prima Nota e sposta la scadenza all'anno dopo; «Fatta» per gli
    adempimenti senza pagamento. Finestre in scadenzario/_rinnova.
--}}
@extends('layouts.app')

@section('title', "Scadenze dell'associazione")

@section('content')
    <x-page-header titolo="Scadenze" evidenza="dell'associazione"
                   sottotitolo="Affiliazione, polizza, adempimenti: ogni anno, con preavviso e pagamento in Prima Nota">
        @if ($puo)
            <x-slot:azioni>
                <a href="{{ route('scadenzario.create') }}" class="btn btn-primary"><x-icona nome="piu" width="14" height="14" /> Nuova scadenza</a>
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="scadenzario" />

    @if ($avviso)
        <div @class(['sc-avviso', 'sc-avviso-' . $avviso['tipo']]) role="alert">
            <div>
                <strong>{{ $avviso['titolo'] }}</strong>
                <span>{{ $avviso['testo'] }}</span>
            </div>
            @if ($puo)
                <button type="button" class="btn btn-primary" x-data x-on:click="$dispatch('apri-modale', 'rinnova-{{ $avviso['scadenza']->id }}')">Rinnovata</button>
            @endif
        </div>
    @endif

    <x-card titolo="Ricorrenti ogni anno" senza-padding>
        @if ($ricorrenti->isEmpty())
            <x-vuoto>Nessuna scadenza ricorrente.</x-vuoto>
        @else
            @include('scadenzario._tabella', ['scadenze' => $ricorrenti])
        @endif
    </x-card>

    @if ($unaTantum->isNotEmpty())
        <x-card titolo="Una tantum" senza-padding>
            @include('scadenzario._tabella', ['scadenze' => $unaTantum])
            <p class="table-footer">Quelle fatte restano qui per un anno; lo storico completo è nella scheda di ogni scadenza.</p>
        </x-card>
    @endif

    <p class="form-help sc-nota">
        Stato: <strong>in scadenza</strong> entro il preavviso di ogni scadenza, <strong>scaduta</strong> dal giorno dopo.
        Il promemoria «Scadenze dell'associazione» manda un'email a Presidente e Tesoriere prima di ogni scadenza (pagina
        @if (Route::has('promemoria.index') && auth()->user()->puoAccedere('promemoria'))<a href="{{ route('promemoria.index') }}">Promemoria</a>@else Promemoria @endif).
    </p>

    @if ($puo)
        @push('modals')
            @foreach ($ricorrenti->concat($unaTantum)->reject(fn ($s) => $s->completato) as $s)
                @include('scadenzario._rinnova', ['s' => $s])
            @endforeach
        @endpush
    @endif
@endsection
