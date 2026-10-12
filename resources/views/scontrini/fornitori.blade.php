{{--
    Fornitori (tavola SN-7): partita IVA, comune, quante volte usati,
    ultimo uso, in elenco o tolti dalla lista; possibili doppioni da unire
    (stesso nome senza forme giuridiche e punteggiatura). Modifica, nuovo e
    «Unisci» in finestre. Presidente e Tesoriere ($puo); gli altri leggono.
--}}
@extends('layouts.app')

@section('title', 'Fornitori')

@section('content')
    <x-page-header titolo="Fornitori" sottotitolo="Riconosciuti dagli scontrini e dalla Prima Nota: correggi, unisci i doppioni, togli quelli non più usati"
                   :indietro="route('scontrini.index')" indietro-label="Scontrini">
        @if ($puo)
            <x-slot:azioni>
                <button type="button" class="btn btn-primary" x-data x-on:click="$dispatch('apri-modale', 'fornitore-nuovo')"><x-icona nome="piu" width="14" height="14" /> Nuovo fornitore</button>
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="scontrini" />
    @if (! $puo && ! auth()->user()->soloLettura('scontrini'))
        <x-avviso tipo="info" titolo="Sola lettura.">I fornitori li correggono il Presidente e il Tesoriere.</x-avviso>
    @endif

    @if ($errors->unisci->any())
        <x-avviso tipo="ko" titolo="Unione non eseguita.">{{ $errors->unisci->first() }}</x-avviso>
    @endif

    @foreach ($doppioni as $g)
        <div class="sc-avviso sc-avviso-warn" role="status">
            <div>
                <strong>Possibili doppioni</strong>
                <span>{{ $g->map(fn ($f) => '«' . $f->nome . '»')->implode(' e ') }} sembrano lo stesso negozio.@if ($puo) «Unisci»: i movimenti passano al primo, il secondo sparisce.@endif</span>
            </div>
            @if ($puo)
                <button type="button" class="btn btn-secondary" x-data x-on:click="$dispatch('apri-modale', 'unisci-{{ $g->first()->id }}')">Unisci</button>
            @endif
        </div>
    @endforeach

    <x-card senza-padding>
        <form method="GET" action="{{ route('scontrini.fornitori.index') }}" class="filter-bar sn-filtri">
            <div class="form-field filtro-cerca">
                <label class="form-label sr-only" for="f-q">Cerca</label>
                <input id="f-q" name="q" type="search" value="{{ $q }}" class="form-input" placeholder="Cerca nome o partita IVA" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-secondary">Cerca</button>
            @if ($q !== '')<a href="{{ route('scontrini.fornitori.index') }}" class="btn btn-ghost">Tutti</a>@endif
        </form>

        @if ($fornitori->isEmpty())
            <x-vuoto>{{ $q !== '' ? 'Nessun fornitore trovato.' : 'Ancora nessun fornitore: nascono con il primo scontrino.' }}</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive sn-fornitori">
                    <thead>
                        <tr>
                            <th scope="col">Fornitore</th>
                            <th scope="col">Partita IVA</th>
                            <th scope="col">Comune</th>
                            <th scope="col" class="td-num">Movimenti</th>
                            <th scope="col">Ultimo uso</th>
                            <th scope="col">Stato</th>
                            @if ($puo)<th scope="col"><span class="sr-only">Azioni</span></th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($fornitori as $f)
                            <tr>
                                <td class="td-primario"><strong>{{ $f->nome }}</strong>@if ($f->indirizzo)<span class="td-sub">{{ $f->indirizzo }}</span>@endif</td>
                                <td data-label="Partita IVA"><span class="code">{{ $f->partita_iva ?? '—' }}</span></td>
                                <td data-label="Comune">{{ $f->comune ?? '—' }}</td>
                                <td data-label="Movimenti" class="td-num">{{ $f->usi }}</td>
                                <td data-label="Ultimo uso">{{ $f->ultimo?->format('d/m/Y') ?? '—' }}</td>
                                <td data-label="Stato"><x-badge :variante="$f->attivo ? 'ok' : 'neutral'">{{ $f->attivo ? 'In elenco' : 'Tolto dalla lista' }}</x-badge></td>
                                @if ($puo)
                                    <td class="td-azioni">
                                        <button type="button" class="action-btn" x-data x-on:click="$dispatch('apri-modale', 'fornitore-{{ $f->id }}')" aria-label="Modifica {{ $f->nome }}"><x-icona nome="matita" /></button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="table-footer">{{ $fornitori->count() }} {{ $fornitori->count() === 1 ? 'fornitore' : 'fornitori' }}. «Movimenti»: quelli di Prima Nota con questo nome, di tutti gli anni.</p>
        @endif
    </x-card>

    @if ($puo)
        @push('modals')
            @include('scontrini._fornitore', ['f' => null, 'nome' => 'fornitore-nuovo', 'bag' => 'nuovo'])
            @foreach ($fornitori as $f)
                @include('scontrini._fornitore', ['f' => $f, 'nome' => 'fornitore-' . $f->id, 'bag' => 'fornitore-' . $f->id])
            @endforeach
            @foreach ($doppioni as $g)
                <x-modale nome="unisci-{{ $g->first()->id }}" titolo="Unisci i fornitori">
                    <form method="POST" action="{{ route('scontrini.fornitori.unisci') }}" id="form-unisci-{{ $g->first()->id }}" class="form-modale"
                          x-data="{ tieni: '{{ $g->first()->id }}' }">
                        @csrf
                        <p class="sc-modale-testa">Il fornitore che resta (con i dati da tenere):</p>
                        <div class="sn-opzioni">
                            @foreach ($g->take(2) as $f)
                                <label class="scelta sn-scelta">
                                    <input type="radio" value="{{ $f->id }}" x-model="tieni">
                                    <span class="scelta-testo">{{ $f->nome }}
                                        <span class="scelta-sub">{{ $f->partita_iva ? 'P.IVA ' . $f->partita_iva : 'senza partita IVA' }}{{ $f->comune ? ' · ' . $f->comune : '' }} · {{ (int) ($usi[$f->nome]->n ?? 0) }} {{ (int) ($usi[$f->nome]->n ?? 0) === 1 ? 'movimento' : 'movimenti' }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <input type="hidden" name="tieni" x-bind:value="tieni">
                        <input type="hidden" name="togli" x-bind:value="tieni === '{{ $g->get(0)->id }}' ? '{{ $g->get(1)->id }}' : '{{ $g->get(0)->id }}'">
                        <p class="form-help">I movimenti e gli scontrini passano tutti al fornitore scelto; nei movimenti il nome si aggiorna. I dati che gli mancano (partita IVA, indirizzo, comune) si prendono dall'altro. Nell'Audit Log resta la riga «FORNITORI_UNITI».</p>
                    </form>
                    <x-slot:piede>
                        <button type="button" class="btn btn-ghost" x-on:click="aperta = false">Annulla</button>
                        <button type="submit" form="form-unisci-{{ $g->first()->id }}" class="btn btn-primary">Unisci</button>
                    </x-slot:piede>
                </x-modale>
            @endforeach
        @endpush
    @endif
@endsection
