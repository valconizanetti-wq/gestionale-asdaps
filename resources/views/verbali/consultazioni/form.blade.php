{{--
    Consultazione telematica in bozza (v0.44.0, tavola CT-1): oggetto, testo
    della proposta di delibera con l'editor, termine (almeno 3 giorni
    dall'avvio, art. 16.4 a), documenti in PDF. La prepara chi scrive i
    verbali; la avvia il Presidente dalla scheda (V16).
    Variabili: $c (Consultazione, nuova o in bozza), $documenti.
--}}
@extends('layouts.app')

@section('title', $c->exists ? 'Modifica la consultazione' : 'Nuova consultazione telematica')

@push('head')
    @vite('resources/js/editor.js')
@endpush

@section('content')
    <x-page-header :titolo="$c->exists ? 'Modifica la' : 'Nuova'" evidenza="consultazione telematica"
                   sottotitolo="Il Consiglio delibera senza riunirsi: ogni componente vota dal suo link personale (statuto, art. 16.4)."
                   :indietro="$c->exists ? route('verbali.consultazioni.show', $c) : route('verbali.index')" :indietro-label="$c->exists ? 'Consultazione' : 'Verbali'" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Consultazione non salvata.">{{ $errors->first() }}</x-avviso>
    @endif

    <x-avviso tipo="info">Non si usa per il <strong>rendiconto</strong> né per l'<strong>esclusione di un socio</strong>: si decidono in riunione (art. 16.5). Le domande di adesione hanno il loro voto, dalla pagina Domande.</x-avviso>

    <form method="POST" action="{{ $c->exists ? route('verbali.consultazioni.update', $c) : route('verbali.consultazioni.store') }}" enctype="multipart/form-data"
          class="vb-form" x-data="{ inviato: false }" x-on:submit="inviato = true">
        @csrf
        @if ($c->exists) @method('PUT') @endif

        <x-card titolo="Proposta di delibera">
            <x-campo name="oggetto" label="Oggetto" required :value="$c->oggetto" maxlength="255" aiuto="In breve: arriva nell'oggetto dell'email. Per esempio «Acquisto di 6 paglioni per il campo»." />
            <x-editor name="testo_html" label="Testo della proposta" :value="$c->testo_html" required :altezza="220"
                      aiuto="Le premesse e il testo da deliberare («Il Consiglio Direttivo delibera di …»)." />
        </x-card>

        <x-card titolo="Termine e documenti">
            <div class="form-grid">
                <x-campo name="termine" label="Termine per il voto" tipo="datetime-local" required
                         :value="old('termine', $c->termine?->format('Y-m-d\TH:i'))"
                         aiuto="Almeno 3 giorni dopo l'avvio: se il Presidente avvia più tardi, il termine si sposta." />
            </div>
            @if ($documenti->isNotEmpty())
                <ul class="ct-documenti">
                    @foreach ($documenti as $d)
                        <li><a href="{{ route('verbali.consultazioni.documenti.show', [$c, $d]) }}" target="_blank" rel="noopener"><x-icona nome="documento" width="16" height="16" /> {{ $d->nome }}</a>
                            <span class="testo-secondario">{{ $d->pesoTesto() }}</span></li>
                    @endforeach
                </ul>
            @endif
            <x-campo-giustificativi id="documenti-consultazione" label="Aggiungi documenti" accept=".pdf,application/pdf"
                                    aiuto="Solo PDF, fino a 8 MB ciascuno. I componenti li aprono dalla pagina del voto." :errori="$errors->get('documenti.*')" />
        </x-card>

        <div class="form-actions form-actions-fine">
            <a href="{{ $c->exists ? route('verbali.consultazioni.show', $c) : route('verbali.index') }}" class="btn btn-ghost">Annulla</a>
            <button type="submit" class="btn btn-primary" x-bind:disabled="inviato">Salva in bozza</button>
        </div>
    </form>
@endsection
