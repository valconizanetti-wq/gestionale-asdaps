{{--
    Anticipi e note di rimborso (tavola AN-3, anticipi.md §3).
    In alto i numeri; poi le spese senza nota raggruppate per socio, con la
    scelta delle spese e i due pulsanti «Prepara la nota di rimborso» e
    «Rinuncia al rimborso (donazione)»; in fondo le note dell'anno scelto.
    Una spesa «da classificare» non va in una nota (pulsanti spenti).
    Variabili: $gruppi, $note, $anno, $anniNote, $kpi.
--}}
@extends('layouts.app')

@section('title', 'Anticipi e note di rimborso')

@use('App\Support\Importo')
@use('App\Models\PrimaNotaCategoria')

@section('content')
    @php $puoModificare = auth()->user()->puoModificare('prima_nota'); @endphp

    <x-page-header titolo="Anticipi e" evidenza="note di rimborso"
                   sottotitolo="Spese pagate di tasca propria dai soci, da rimborsare con una nota firmata."
                   :indietro="route('prima-nota.index')" indietro-label="Prima Nota">
        @if ($puoModificare)
            <x-slot:azioni>
                <a href="{{ route('prima-nota.create', ['anticipata' => 1]) }}" class="btn btn-primary"><x-icona nome="piu" width="14" height="14" /> Spesa anticipata</a>
            </x-slot:azioni>
        @endif
    </x-page-header>

    <x-sola-lettura sezione="prima_nota" />

    @if ($errors->any())
        <x-avviso tipo="ko" titolo="Operazione non eseguita.">{{ $errors->first() }}</x-avviso>
    @endif

    <div class="kpi-row">
        <x-kpi label="Da rimborsare" :valore="Importo::it($kpi['da_rimborsare'], true)" :variante="$kpi['da_rimborsare_n'] ? 'warn' : null"
               :sub="$kpi['da_rimborsare_n'] ? \App\Services\NoteRimborso::spese($kpi['da_rimborsare_n']) . ' di ' . $kpi['da_rimborsare_soci'] . ' ' . ($kpi['da_rimborsare_soci'] === 1 ? 'socio' : 'soci') : 'Nessuna spesa'" />
        <x-kpi label="Note da firmare" :valore="$kpi['da_firmare']->count()"
               :sub="$kpi['da_firmare']->isEmpty() ? 'Nessuna' : $kpi['da_firmare']->take(3)->pluck('numero')->implode(', ') . ($kpi['da_firmare']->count() > 3 ? ', …' : '')"
               :href="$kpi['da_firmare']->count() === 1 ? route('prima-nota.anticipi.show', $kpi['da_firmare']->first()) : null" />
        <x-kpi label="Note da pagare" :valore="$kpi['da_pagare']->count()"
               :sub="$kpi['da_pagare']->isEmpty() ? 'Nessuna' : $kpi['da_pagare']->take(3)->map(fn ($n) => $n->numero . ' · ' . Importo::it($n->totale, true))->implode(', ') . ($kpi['da_pagare']->count() > 3 ? ', …' : '')"
               :href="$kpi['da_pagare']->count() === 1 ? route('prima-nota.anticipi.show', $kpi['da_pagare']->first()) : null" />
        <x-kpi :label="'Rimborsato nel ' . $anno" :valore="Importo::it($kpi['rimborsato'], true)" :variante="$kpi['rimborsato_n'] ? 'ok' : null"
               :sub="$kpi['rimborsato_n'] . ' ' . ($kpi['rimborsato_n'] === 1 ? 'nota pagata' : 'note pagate')" />
    </div>

    <h2 class="an-titolo">Spese senza nota</h2>

    @forelse ($gruppi as $g)
        @php
            $socio = $g['socio'];
            $daClassificare = $g['spese']->filter(fn ($m) => $m->categoria?->codice === PrimaNotaCategoria::DA_CLASSIFICARE || ! $m->categoria_id)->pluck('id')->values();
            $importi = $g['spese']->mapWithKeys(fn ($m) => [$m->id => Importo::centesimi($m->importo)]);
            $idForm = 'nota-socio-' . ($socio?->id ?? 0);
        @endphp
        <div class="an-gruppo" x-data="{ scelte: {{ $puoModificare ? $g['spese']->pluck('id')->diff($daClassificare)->values()->toJson() : '[]' }}, importi: {{ $importi->toJson() }}, bloccate: {{ $daClassificare->toJson() }},
                          get totale() { return this.scelte.reduce((t, id) => t + (this.importi[id] || 0), 0) },
                          get bloccata() { return this.scelte.some(id => this.bloccate.includes(id)) },
                          euro(c) { return (c / 100).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €' } }">
        <x-card :titolo="$socio ? $socio->cognome . ' ' . $socio->nome . ($g['carica'] ? ' · ' . $g['carica'] : '') : 'Socio non indicato'" senza-padding>
            <div class="table-wrap">
                <table class="data-table responsive">
                    <thead>
                        <tr>
                            @if ($puoModificare)<th scope="col" class="an-spunta"><span class="sr-only">Scegli</span></th>@endif
                            <th scope="col">Data</th>
                            <th scope="col">Descrizione</th>
                            <th scope="col">Categoria</th>
                            <th scope="col">Giustificativo</th>
                            <th scope="col" class="td-num">Importo €</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($g['spese'] as $m)
                            @php
                                $nc = $m->categoria?->codice === PrimaNotaCategoria::DA_CLASSIFICARE || ! $m->categoria_id;
                                $doc = $m->documenti->first();
                                $foto = $m->scontrino?->file->first();
                            @endphp
                            <tr>
                                @if ($puoModificare)
                                    <td data-label="Scegli" class="an-spunta">
                                        <input type="checkbox" name="spese[]" value="{{ $m->id }}" form="{{ $idForm }}" x-model.number="scelte"
                                               aria-label="Scegli {{ $m->descrizione }} del {{ $m->dataSpesa()->format('d/m/Y') }}">
                                    </td>
                                @endif
                                <td data-label="Data"><strong>{{ $m->dataSpesa()->format('d/m/Y') }}</strong></td>
                                <td data-label="Descrizione" class="td-primario">
                                    <a href="{{ route('prima-nota.show', $m) }}">{{ $m->descrizione }}</a>
                                    @php $sotto = array_filter([$m->soggetto, $m->scontrino_id ? 'dal telefono' : null]); @endphp
                                    @if ($sotto)<span class="td-sub">{{ implode(' · ', $sotto) }}</span>@endif
                                </td>
                                <td data-label="Categoria">
                                    @if ($nc)<x-badge variante="warn">Da classificare</x-badge>@else{{ $m->categoria->nome }}@endif
                                </td>
                                <td data-label="Giustificativo">
                                    @if ($doc)
                                        <a href="{{ route('prima-nota.documenti.show', [$m, $doc]) }}" target="_blank" rel="noopener">{{ mb_strtolower(\App\Models\PrimaNota::TIPI_DOCUMENTO[$m->tipo_documento] ?? 'giustificativo') }}</a>
                                    @elseif ($foto && auth()->user()->puoAccedere('scontrini'))
                                        <a href="{{ route('scontrini.foto.show', [$m->scontrino, $foto]) }}" target="_blank" rel="noopener">scontrino</a>
                                    @elseif ($foto)
                                        scontrino
                                    @else
                                        <span class="testo-ko">manca</span>
                                    @endif
                                </td>
                                <td data-label="Importo €" class="td-num"><strong>{{ Importo::it($m->importo) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($puoModificare && $socio)
                <form method="GET" action="{{ route('prima-nota.anticipi.create') }}" id="{{ $idForm }}" class="an-azioni">
                    <input type="hidden" name="socio" value="{{ $socio->id }}">
                    <button type="submit" name="tipo" value="rimborso" class="btn btn-primary" x-bind:disabled="scelte.length === 0 || bloccata">Prepara la nota di rimborso</button>
                    <button type="submit" name="tipo" value="rinuncia" class="btn btn-secondary" x-bind:disabled="scelte.length === 0 || bloccata">Rinuncia al rimborso (donazione)</button>
                    <span class="an-scelte" x-show="! bloccata" x-text="scelte.length ? 'Scelte: ' + scelte.length + (scelte.length === 1 ? ' spesa' : ' spese') + ' · ' + euro(totale) : 'Scegli le spese da mettere nella nota'"></span>
                    <span class="an-scelte" x-show="bloccata" x-cloak>Prima va scelta la categoria: una spesa «da classificare» non va in una nota. Apri la spesa e classificala.</span>
                </form>
            @endif
        </x-card>
        </div>
    @empty
        <x-card>
            <x-vuoto>Nessuna spesa anticipata da mettere in una nota.</x-vuoto>
        </x-card>
    @endforelse

    <div class="an-titolo-riga">
        <h2 class="an-titolo">Note</h2>
        @if ($anniNote->count() > 1)
            <form method="GET" action="{{ route('prima-nota.anticipi.index') }}" class="an-anno" x-data>
                <label for="anno-note" class="sr-only">Anno delle note</label>
                <select id="anno-note" name="anno" class="form-select" x-on:change="$el.form.submit()">
                    @foreach ($anniNote as $a)
                        <option value="{{ $a }}" @selected($a === $anno)>{{ $a }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn btn-secondary btn-sm">Mostra</button></noscript>
            </form>
        @endif
    </div>

    <x-card senza-padding>
        @if ($note->isEmpty())
            <x-vuoto>Nessuna nota nel {{ $anno }}.</x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive">
                    <thead>
                        <tr>
                            <th scope="col">Numero</th>
                            <th scope="col">Data</th>
                            <th scope="col">Socio</th>
                            <th scope="col" class="td-num">Spese</th>
                            <th scope="col" class="td-num">Totale €</th>
                            <th scope="col">Stato</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($note as $n)
                            <tr class="riga-link" data-href="{{ route('prima-nota.anticipi.show', $n) }}">
                                <td data-label="Numero" class="td-primario">
                                    <a href="{{ route('prima-nota.anticipi.show', $n) }}"><strong>{{ $n->numero }}</strong></a>
                                    @if ($n->isRinuncia())<span class="td-sub">rinuncia (donazione)</span>@endif
                                </td>
                                <td data-label="Data">{{ $n->data_nota->format('d/m/Y') }}</td>
                                <td data-label="Socio">{{ $n->socioNome() }}</td>
                                <td data-label="Spese" class="td-num">{{ $n->numero_spese }}</td>
                                <td data-label="Totale €" class="td-num">{{ Importo::it($n->totale) }}</td>
                                <td data-label="Stato">
                                    <x-badge :variante="$n->statoVariante()">{{ $n->statoTesto() }}</x-badge>
                                    @if ($n->isAnnullata() && $n->motivo_annullamento)<span class="td-sub">{{ \Illuminate\Support\Str::limit($n->motivo_annullamento, 60) }}</span>@endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <p class="form-help an-piede">Una nota annullata resta nell'elenco con il motivo: il suo numero non si riusa. L'indicatore «Anticipi da rimborsare» della Prima Nota porta a questa pagina.</p>
@endsection
