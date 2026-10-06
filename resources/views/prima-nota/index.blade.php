@extends('layouts.app')

@section('title', 'Prima Nota')

@use('App\Support\Importo')

@section('content')
    @php
        $puoModificare = auth()->user()->puoModificare('prima_nota');
        $chiuso = $riga?->isChiuso();
        $parametri = request()->query();
    @endphp

    <x-page-header titolo="Prima" evidenza="Nota"
                   :sottotitolo="'Anno ' . $anno . ' · ' . ($chiuso ? 'chiuso' : 'aperto') . ' · registro per cassa delle entrate e delle uscite'">
        <x-slot:azioni>
            <a href="{{ route('prima-nota.export-pdf', $parametri + ['anno' => $anno]) }}" class="btn btn-secondary solo-desktop">
                <x-icona nome="download" width="14" height="14" /> PDF
            </a>
            <a href="{{ route('prima-nota.export-csv', $parametri + ['anno' => $anno]) }}" class="btn btn-secondary solo-desktop">
                <x-icona nome="download" width="14" height="14" /> CSV
            </a>
            <a href="{{ route('prima-nota.anni.index') }}" class="btn btn-secondary">Anni e saldi</a>
            <a href="{{ route('prima-nota.categorie.index') }}" class="btn btn-secondary">Categorie</a>
            @if ($puoModificare && ! $chiuso)
                <a href="{{ route('prima-nota.giroconto.create') }}" class="btn btn-secondary">Giroconto</a>
                <a href="{{ route('prima-nota.create') }}" class="btn btn-primary">
                    <x-icona nome="piu" width="14" height="14" /> Nuovo movimento
                </a>
            @endif
        </x-slot:azioni>
    </x-page-header>

    <x-sola-lettura sezione="prima_nota" />

    @if ($chiuso)
        <x-avviso tipo="info" titolo="Anno {{ $anno }} chiuso.">
            Chiuso il {{ $riga->chiuso_il?->format('d/m/Y') }}. Le correzioni chiedono una motivazione, registrata nell'Audit Log.
        </x-avviso>
    @elseif ($apertura['da_impostare'] && ($riga || $anno === now()->year))
        <x-avviso tipo="warn" titolo="Saldi di apertura {{ $anno }} da impostare.">
            Senza i saldi di partenza di cassa e banca i saldi qui sotto partono da zero.
            <a href="{{ route('prima-nota.anni.index') }}">Scrivili in Anni e saldi</a>.
        </x-avviso>
    @endif

    @if (! $chiuso && ($kpi['da_classificare'] || $kpi['anticipi_n']))
        <x-avviso tipo="warn" titolo="Da sistemare prima della chiusura dell'anno.">
            @if ($kpi['da_classificare'])
                <a href="{{ route('prima-nota.index', ['anno' => $anno, 'tipo' => 'da_classificare']) }}">{{ $kpi['da_classificare'] }} {{ $kpi['da_classificare'] === 1 ? 'movimento' : 'movimenti' }} da classificare</a>@if ($kpi['anticipi_n']) · @endif
            @endif
            @if ($kpi['anticipi_n'])
                <a href="{{ route('prima-nota.index', ['anno' => $anno, 'tipo' => 'anticipi']) }}">{{ $kpi['anticipi_n'] }} {{ $kpi['anticipi_n'] === 1 ? 'spesa anticipata' : 'spese anticipate' }} da rimborsare</a>
            @endif
        </x-avviso>
    @endif

    <div class="kpi-row kpi-row-5">
        <x-kpi label="Saldo cassa" :valore="Importo::it($finali['cassa'], true)" :variante="$finali['cassa'] < 0 ? 'ko' : null"
               :sub="'Apertura ' . Importo::it($apertura['cassa'], true)"
               :href="route('prima-nota.index', ['anno' => $anno, 'conto' => 'cassa'])" :attivo="$filtri['conto'] === 'cassa'" />
        <x-kpi label="Saldo banca" :valore="Importo::it($finali['banca'], true)" :variante="$finali['banca'] < 0 ? 'ko' : null"
               :sub="'Apertura ' . Importo::it($apertura['banca'], true)"
               :href="route('prima-nota.index', ['anno' => $anno, 'conto' => 'banca'])" :attivo="$filtri['conto'] === 'banca'" />
        <x-kpi label="Entrate" :valore="Importo::it($kpi['entrate'], true)" variante="ok"
               :sub="$kpi['entrate_n'] . ' ' . ($kpi['entrate_n'] === 1 ? 'movimento' : 'movimenti')"
               :href="route('prima-nota.index', ['anno' => $anno, 'tipo' => 'entrata'])" :attivo="$filtri['tipo'] === 'entrata'" />
        <x-kpi label="Uscite" :valore="Importo::it($kpi['uscite'], true)" variante="ko"
               :sub="$kpi['uscite_n'] . ' ' . ($kpi['uscite_n'] === 1 ? 'movimento' : 'movimenti')"
               :href="route('prima-nota.index', ['anno' => $anno, 'tipo' => 'uscita'])" :attivo="$filtri['tipo'] === 'uscita'" />
        <x-kpi label="Anticipi da rimborsare" :valore="Importo::it($kpi['anticipi'], true)" :variante="$kpi['anticipi'] > 0 ? 'warn' : null"
               :sub="$kpi['anticipi_n'] ? $kpi['anticipi_n'] . ' ' . ($kpi['anticipi_n'] === 1 ? 'spesa' : 'spese') : 'Nessuno'" />
    </div>

    <form method="GET" action="{{ route('prima-nota.index') }}" class="filter-bar pn-filtri" x-data="{ aperti: false }">
        <div class="form-field">
            <label class="form-label" for="f-anno">Anno</label>
            <select id="f-anno" name="anno" class="form-select">
                @foreach ($anniDisponibili as $a)
                    <option value="{{ $a }}" @selected($a === $anno)>{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-field filtro-cerca">
            <label class="form-label" for="f-q">Cerca</label>
            <input id="f-q" name="q" type="search" value="{{ $filtri['q'] }}" class="form-input"
                   placeholder="Descrizione, fornitore, numero" autocomplete="off">
        </div>

        <button type="button" class="btn btn-secondary filtri-toggle" @click="aperti = !aperti" :aria-expanded="aperti.toString()" aria-controls="filtri-extra">
            <x-icona nome="filtro" width="14" height="14" /> Filtri
        </button>

        <div id="filtri-extra" class="filtri-extra" :class="{ 'aperti': aperti }">
            <div class="form-field">
                <label class="form-label" for="f-conto">Conto</label>
                <select id="f-conto" name="conto" class="form-select">
                    <option value="">Tutti</option>
                    @foreach (\App\Models\PrimaNota::CONTI as $v => $l)
                        <option value="{{ $v }}" @selected($filtri['conto'] === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-tipo">Tipo</label>
                <select id="f-tipo" name="tipo" class="form-select">
                    <option value="">Tutti</option>
                    @foreach (\App\Http\Controllers\PrimaNotaController::TIPI_FILTRO as $v => $l)
                        <option value="{{ $v }}" @selected($filtri['tipo'] === $v)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="f-categoria">Categoria</label>
                <select id="f-categoria" name="categoria" class="form-select">
                    <option value="">Tutte</option>
                    @foreach ($categorie->groupBy('tipo') as $tipo => $gruppo)
                        <optgroup label="{{ $tipo === 'entrata' ? 'Entrate' : 'Uscite' }}">
                            @foreach ($gruppo as $c)
                                <option value="{{ $c->id }}" @selected($filtri['categoria'] === $c->id)>{{ $c->nome }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            @if ($tags->isNotEmpty())
                <div class="form-field">
                    <label class="form-label" for="f-tag">Tag</label>
                    <select id="f-tag" name="tag" class="form-select">
                        <option value="">Tutti</option>
                        @foreach ($tags as $t)
                            <option value="{{ $t->id }}" @selected($filtri['tag'] === $t->id)>{{ $t->nome }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <button type="submit" class="btn btn-primary">Filtra</button>
    </form>

    <x-card titolo="Movimenti" senza-padding>
        <x-slot:azioni>
            @if ($filtroAttivo)
                <a href="{{ route('prima-nota.index', ['anno' => $anno]) }}" class="btn btn-ghost btn-sm">Togli i filtri</a>
            @endif
        </x-slot:azioni>

        @if ($movimenti->isEmpty())
            <x-vuoto>
                @if ($filtroAttivo)
                    Nessun movimento corrisponde ai filtri. <a href="{{ route('prima-nota.index', ['anno' => $anno]) }}">Mostra tutti i movimenti del {{ $anno }}</a>
                @elseif ($puoModificare && ! $chiuso)
                    Ancora nessun movimento nel {{ $anno }}. <a href="{{ route('prima-nota.create') }}">Registra il primo</a>
                @else
                    Nessun movimento nel {{ $anno }}.
                @endif
            </x-vuoto>
        @else
            <div class="table-wrap">
                <table class="data-table responsive">
                    <thead>
                        <tr>
                            <th scope="col">N.</th>
                            <th scope="col">Data</th>
                            <th scope="col">Descrizione</th>
                            <th scope="col">Categoria</th>
                            <th scope="col">Conto</th>
                            <th scope="col" class="td-num td-solo-mobile">Importo €</th>
                            <th scope="col" class="td-num">Entrata €</th>
                            <th scope="col" class="td-num">Uscita €</th>
                            <th scope="col"><span class="sr-only">Stato</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($movimenti as $m)
                            @php
                                $giroconto = $m->isGiroconto();
                                $daClassificare = $m->categoria?->codice === \App\Models\PrimaNotaCategoria::DA_CLASSIFICARE;
                            @endphp
                            <tr class="riga-link" data-href="{{ route('prima-nota.show', $m) }}">
                                <td data-label="N." class="td-solo-desktop"><span class="pn-numero">{{ $m->id }}</span></td>
                                <td data-label="Data"><strong>{{ $m->data->format('d/m/Y') }}</strong></td>
                                <td class="td-primario">
                                    <a href="{{ route('prima-nota.show', $m) }}" class="pn-desc">{{ $m->descrizione }}</a>
                                    @if ($m->tag || $m->documenti_count)
                                        <span class="pn-etichette">
                                            @if ($m->tag)<a href="{{ route('prima-nota.index', ['anno' => $anno, 'tag' => $m->tag_id]) }}" class="badge-tag">{{ $m->tag->nome }}</a>@endif
                                            @if ($m->documenti_count)<x-badge variante="neutral">{{ $m->documenti_count }} {{ $m->documenti_count === 1 ? 'allegato' : 'allegati' }}</x-badge>@endif
                                        </span>
                                    @endif
                                    @php
                                        $sotto = array_filter([
                                            $m->soggetto,
                                            $m->socio ? $m->socio->cognome . ' ' . $m->socio->nome : null,
                                            trim(implode(' ', array_filter([\App\Models\PrimaNota::TIPI_DOCUMENTO[$m->tipo_documento] ?? null, $m->numero_documento]))) ?: null,
                                            $m->sospeso ? 'anticipata da ' . $m->anticipato_da : null,
                                        ]);
                                    @endphp
                                    @if ($sotto)<span class="td-sub">{{ implode(' · ', $sotto) }}</span>@endif
                                </td>
                                <td data-label="Categoria">
                                    @if ($giroconto)
                                        <x-badge variante="neutral">Giroconto</x-badge>
                                    @elseif ($daClassificare)
                                        <x-badge variante="warn">Da classificare</x-badge>
                                    @else
                                        {{ $m->categoria?->nome ?? '—' }}
                                    @endif
                                </td>
                                <td data-label="Conto">
                                    @if ($giroconto && $filtri['conto'] === '')
                                        <span class="pn-verso">{{ $m->contoLabel() }} → {{ $m->giroconto?->contoLabel() }}</span>
                                    @else
                                        {{ $m->contoLabel() }}
                                    @endif
                                </td>
                                {{-- Telefono: una sola riga "Importo" con il segno, al posto di Entrata e Uscita --}}
                                <td data-label="Importo €" class="td-num td-solo-mobile">
                                    <span class="{{ $giroconto ? 'importo-neutro' : ($m->isEntrata() ? 'importo-entrata' : 'importo-uscita') }}">{{ $giroconto ? '' : ($m->isEntrata() ? '+ ' : '− ') }}{{ Importo::it($m->importo) }}</span>
                                </td>
                                <td data-label="Entrata €" class="td-num td-solo-desktop">
                                    @if ($m->isEntrata())<span class="{{ $giroconto ? 'importo-neutro' : 'importo-entrata' }}">{{ Importo::it($m->importo) }}</span>@endif
                                </td>
                                <td data-label="Uscita €" class="td-num td-solo-desktop">
                                    @if (! $m->isEntrata())<span class="{{ $giroconto ? 'importo-neutro' : 'importo-uscita' }}">{{ Importo::it($m->importo) }}</span>@endif
                                </td>
                                <td data-label="Stato" @class(['td-solo-desktop' => ! $m->isAnticipoAperto()])>
                                    @if ($m->isAnticipoAperto())
                                        <x-badge variante="warn">Da rimborsare</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-paginazione :elementi="$movimenti" nome="movimenti" />
            <div class="table-footer">
                <span class="pn-totali">
                    Totali {{ $filtroAttivo ? 'del filtro' : "dell'anno" }}:
                    <strong class="entrate">entrate {{ Importo::it($totaliFiltro['entrate']) }}</strong> ·
                    <strong class="uscite">uscite {{ Importo::it($totaliFiltro['uscite']) }}</strong>
                    — giroconti e anticipi non ancora rimborsati non contano.
                </span>
            </div>
        @endif
    </x-card>
@endsection
