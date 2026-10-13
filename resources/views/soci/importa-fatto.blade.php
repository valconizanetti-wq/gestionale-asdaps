@extends('layouts.app')

@section('title', 'Soci importati')

@section('content')
    <x-page-header titolo="Importa soci" evidenza="fatto" :indietro="route('soci.index')" indietro-label="Soci" />

    <x-avviso tipo="ok" :titolo="'Importati ' . $esito['soci'] . ' soci.'">Il file è stato cancellato dal server. L'importazione è nell'Audit Log.</x-avviso>

    <div class="pn-colonne">
        <x-card titolo="Riepilogo">
            <dl class="dati-griglia">
                <x-dato label="Soci importati">@php $mag = $esito['soci'] - $esito['minorenni']; @endphp
                    {{ $esito['soci'] }} · {{ $mag }} {{ $mag === 1 ? 'maggiorenne' : 'maggiorenni' }}, {{ $esito['minorenni'] }} {{ $esito['minorenni'] === 1 ? 'minorenne' : 'minorenni' }}</x-dato>
                <x-dato label="Quote del vecchio gestionale dal {{ \App\Services\RinnoviQuota::annoAvvio() }}">{{ $esito['quote'] }}
                    <span class="norma dato-norma">Registrate senza ricevuta né movimento in Prima Nota</span></x-dato>
                <x-dato label="Privacy">Da raccogliere per tutti con il link personale</x-dato>
            </dl>
            <div class="form-actions">
                <a href="{{ route('soci.index', ['importati' => $esito['data'], 'stato' => 'tutti']) }}" class="btn btn-primary">Vedi i soci importati</a>
            </div>
        </x-card>
        <aside class="pn-colonna">
            <x-card titolo="Prossimi passi" class="pn-aiuto">
                <ol class="is-passi">
                    <li><strong>Link privacy</strong> a tutti: Soci › «Link privacy» (ai minori va al genitore).</li>
                    <li><strong>Tessere CSEN</strong>: Tesseramento › «Carica tessere già emesse» con il file del portale.</li>
                    <li>Cancella il foglio dal tuo computer: contiene dati personali.</li>
                </ol>
            </x-card>
        </aside>
    </div>
@endsection
